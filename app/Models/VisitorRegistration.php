<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorRegistration extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'access_qr_code_id', 'user_id', 'device_id', 'name', 'phone',
        'id_card', 'company', 'reason', 'custom_data', 'status', 'result_message',
        'auth_type', 'auth_status', 'auth_data',
        'opened_at', 'ip', 'user_agent',
    ];

    protected $casts = [
        'custom_data' => 'array',
        'auth_data' => 'array',
        'opened_at' => 'datetime',
    ];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(AccessQrCode::class, 'access_qr_code_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * 访客填写的全部附加信息（字段名 => 值）
     * 新数据取 custom_data；旧数据回退到固定列。
     *
     * @return array<string, string>
     */
    public function getInfoMapAttribute(): array
    {
        if (! empty($this->custom_data) && is_array($this->custom_data)) {
            return array_filter($this->custom_data, fn ($v) => trim((string) $v) !== '');
        }

        return array_filter([
            '身份证号' => $this->id_card,
            '来访单位' => $this->company,
            '来访事由' => $this->reason,
        ], fn ($v) => trim((string) $v) !== '');
    }
}
