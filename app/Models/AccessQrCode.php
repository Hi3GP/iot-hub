<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AccessQrCode extends Model
{
    use SoftDeletes;
    /**
     * 新建登记点时的默认字段（姓名、手机号始终必填，不在此列）
     * 结构：label 字段名 / type text|textarea / enabled 是否在扫码页显示 / required 是否必填
     */
    public const DEFAULT_FIELDS = [
        ['label' => '身份证号', 'type' => 'text', 'enabled' => false, 'required' => true],
        ['label' => '来访单位', 'type' => 'text', 'enabled' => false, 'required' => true],
        ['label' => '来访事由', 'type' => 'textarea', 'enabled' => true, 'required' => true],
    ];

    protected $fillable = [
        'user_id', 'code', 'name', 'device_id', 'command_code', 'command_value',
        'need_id_card', 'need_company', 'need_reason', 'form_fields',
        'success_message', 'verify_mode', 'verify_code', 'verify_question', 'verify_answer', 'verify_hint',
        'sms_verify', 'open_cooldown_minutes', 'daily_open_limit',
        'is_active', 'last_used_at',
    ];

    protected $casts = [
        'need_id_card' => 'boolean',
        'need_company' => 'boolean',
        'need_reason' => 'boolean',
        'form_fields' => 'array',
        'sms_verify' => 'boolean',
        'open_cooldown_minutes' => 'integer',
        'daily_open_limit' => 'integer',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $qr) {
            if (empty($qr->code)) {
                do {
                    $code = Str::lower(Str::random(8));
                } while (self::where('code', $code)->exists());
                $qr->code = $code;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(VisitorRegistration::class);
    }

    /** 扫码访问地址 */
    public function publicUrl(): string
    {
        return url("/q/{$this->code}");
    }

    /** 下发给设备的归一化值 */
    public function normalizedCommandValue(): mixed
    {
        // 优先按产品物模型的设备单元定义解析（bool 映射/枚举/JSON/数字）
        $def = \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($this->device_id, $this->command_code);
        $raw = $this->command_value;

        if ($def) {
            return match ($def['type'] ?? 'string') {
                'bool' => \App\IoT\ThingModel::castValue(['type' => 'bool'], $raw) ? $def['on_value'] ?? '1' : $def['off_value'] ?? '0',
                'int', 'float' => \App\IoT\ThingModel::castValue($def, $raw),
                'json' => json_decode((string) $raw, true) ?? $raw,
                default => $raw,
            };
        }

        // 无产品定义时保持旧兼容逻辑（1/0/数字/文本）
        return match ($raw) {
            '1', 'true', true => true,
            '0', 'false', false => false,
            default => is_numeric($raw)
                ? (str_contains((string) $raw, '.') ? (float) $raw : (int) $raw)
                : $raw,
        };
    }

    /**
     * 扫码登记表单中实际展示的字段（启用的自定义字段）
     *
     * @return array<int, array{label: string, type: string, required: bool}>
     */
    public function enabledFields(): array
    {
        $fields = $this->form_fields;

        // 兼容未配置 form_fields 的旧数据：回退到三个固定开关
        if (empty($fields) || ! is_array($fields)) {
            $fields = [
                ['label' => '身份证号', 'type' => 'text', 'enabled' => $this->need_id_card, 'required' => true],
                ['label' => '来访单位', 'type' => 'text', 'enabled' => $this->need_company, 'required' => true],
                ['label' => '来访事由', 'type' => 'textarea', 'enabled' => $this->need_reason, 'required' => true],
            ];
        }

        return collect($fields)
            ->filter(fn ($f) => is_array($f) && ! empty($f['label']) && (bool) ($f['enabled'] ?? true))
            ->map(fn ($f) => [
                'label' => trim((string) $f['label']),
                'type' => (($f['type'] ?? 'text') === 'textarea') ? 'textarea' : 'text',
                'required' => (bool) ($f['required'] ?? true),
            ])
            ->values()
            ->all();
    }

    /**
     * 校验访客输入的口令/答案是否匹配
     * 规则：忽略大小写与所有空白；多个等价答案用 | 分隔（如「3栋2单元|三栋二单元」）
     */
    public static function answerMatches(string $input, ?string $expected): bool
    {
        $normalize = static fn (string $s): string => mb_strtolower((string) preg_replace('/\s+/u', '', $s));

        $inputNorm = $normalize($input);
        if ($inputNorm === '') {
            return false;
        }

        foreach (explode('|', (string) $expected) as $candidate) {
            $candidate = $normalize(trim($candidate));
            if ($candidate !== '' && $candidate === $inputNorm) {
                return true;
            }
        }

        return false;
    }

    /**
     * 扫码页底部防刷提示语（无任何限制时返回 null）
     */
    public function securityTip(): ?string
    {
        $parts = [];

        if ($this->verify_mode !== 'none') {
            $parts[] = '需通过验证才能提交';
        }
        if ($this->sms_verify) {
            $parts[] = '手机号需短信验证';
        }
        if (($this->daily_open_limit ?? 0) > 0) {
            $parts[] = "同一手机号每日限 {$this->daily_open_limit} 次";
        }
        if (($this->open_cooldown_minutes ?? 0) > 0) {
            $parts[] = "两次开门间隔不少于 {$this->open_cooldown_minutes} 分钟";
        }

        return empty($parts) ? null : '安全提示：'.implode('，', $parts).'，防止恶意刷门。';
    }
}
