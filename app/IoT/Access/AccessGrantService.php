<?php

namespace App\IoT\Access;

use App\Models\AccessQrCode;
use App\Models\Alert;
use App\Models\VisitorRegistration;
use App\IoT\Registry\ConnectorManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 扫码登记开门服务
 *
 * 流程：访客扫码 → 填写登记信息 → 保存记录 → 向登记码绑定的设备下发指令（如开锁）
 *  → 成功/失败都留痕，并生成一条告警中心记录。
 *
 * 并发控制：
 * - 同一设备 5 秒内只允许一次开门（缓存锁），防止多人同时扫码重复开门
 * - 设备指令下发在事务外执行，避免长事务持锁
 */
class AccessGrantService
{
    /** 同一设备开门最小间隔（秒） */
    protected const DOOR_COOLDOWN_SECONDS = 5;

    public function __construct(protected ConnectorManager $connectors)
    {
    }

    /**
     * 自定义字段名 → 旧固定列映射（保持旧报表/列表兼容）
     */
    protected const LEGACY_FIELD_COLUMNS = [
        '身份证号' => 'id_card',
        '证件号' => 'id_card',
        '来访单位' => 'company',
        '单位' => 'company',
        '来访事由' => 'reason',
        '事由' => 'reason',
    ];

    /**
     * @param  array  $data  访客登记信息（name/phone/custom={字段名:值}）
     */
    public function register(AccessQrCode $qr, array $data, ?string $ip = null, ?string $userAgent = null): VisitorRegistration
    {
        // 并发锁：同一设备开门冷却期
        $lockKey = "door_open:{$qr->device_id}";
        if ($qr->device_id && ! Cache::add($lockKey, true, self::DOOR_COOLDOWN_SECONDS)) {
            // 冷却期内，仍保存登记记录但不重复开门
            return $this->createRegistration($qr, $data, $ip, $userAgent, cooldown: true);
        }

        try {
            $registration = $this->createRegistration($qr, $data, $ip, $userAgent);

            $this->triggerDevice($qr, $registration);

            $qr->forceFill(['last_used_at' => now()])->save();

            return $registration->refresh();
        } catch (\Throwable $e) {
            // 开门失败时释放锁，允许重试
            Cache::forget($lockKey);
            throw $e;
        }
    }

    protected function createRegistration(
        AccessQrCode $qr,
        array $data,
        ?string $ip,
        ?string $userAgent,
        bool $cooldown = false
    ): VisitorRegistration {
        return DB::transaction(function () use ($qr, $data, $ip, $userAgent, $cooldown) {
            $custom = $data['custom'] ?? [];

            $legacy = ['id_card' => null, 'company' => null, 'reason' => null];
            foreach ($custom as $label => $value) {
                $column = self::LEGACY_FIELD_COLUMNS[$label] ?? null;
                if ($column !== null && $legacy[$column] === null) {
                    $legacy[$column] = $value;
                }
            }

            $registration = VisitorRegistration::create([
                'access_qr_code_id' => $qr->id,
                'user_id' => $qr->user_id,
                'device_id' => $qr->device_id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'id_card' => $legacy['id_card'],
                'company' => $legacy['company'],
                'reason' => $legacy['reason'],
                'custom_data' => $custom,
                'status' => $cooldown ? 'granted' : 'granted',
                'result_message' => $cooldown ? '登记成功（设备冷却中，未重复开门）' : null,
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            ]);

            return $registration;
        });
    }

    protected function triggerDevice(AccessQrCode $qr, VisitorRegistration $registration): void
    {
        // 未绑定设备：只登记不触发（用于纯信息登记场景）
        if (! $qr->device || ! $qr->device->connector) {
            $registration->update([
                'status' => 'granted',
                'result_message' => '登记成功（该登记点未绑定设备，仅记录信息）',
            ]);

            return;
        }

        $device = $qr->device->loadMissing('connector');
        $value = $qr->normalizedCommandValue();

        try {
            $driver = $this->connectors->make($device->connector);
            $result = $driver->sendCommand($device->connector, $device, $qr->command_code, $value);

            if ($result === false) {
                throw new \RuntimeException("设备 [{$device->name}] 未确认指令下发");
            }

            $registration->update([
                'status' => 'granted',
                'opened_at' => now(),
                'result_message' => "已向 [{$device->name}] 下发指令 [{$qr->command_code}={$qr->command_value}]",
            ]);

            $this->logAlert($qr, $registration, $device, 'info');
        } catch (\Throwable $e) {
            $registration->update([
                'status' => 'failed',
                'result_message' => $e->getMessage(),
            ]);

            $this->logAlert($qr, $registration, $device, 'critical', $e->getMessage());
            Log::error('扫码开门失败', ['qr' => $qr->id, 'device' => $device->id, 'error' => $e->getMessage()]);
        }
    }

    protected function logAlert(AccessQrCode $qr, VisitorRegistration $registration, $device, string $level, ?string $error = null): void
    {
        $extra = collect($registration->info_map)
            ->map(fn ($value, $label) => "{$label}：{$value}")
            ->implode('，');

        Alert::create([
            'device_id' => $device->id,
            'user_id' => $qr->user_id,
            'level' => $level,
            'type' => 'access',
            'title' => $error ? "访客开门失败：{$qr->name}" : "访客登记开门：{$qr->name}",
            'content' => $error
                ? "访客 {$registration->name}（{$registration->phone}）登记后开门失败：{$error}"
                : "访客 {$registration->name}（{$registration->phone}）登记成功，设备 [{$device->name}] 指令已下发"
                    .($extra !== '' ? "，{$extra}" : ''),
            'status' => $error ? 'active' : 'resolved',
            'triggered_at' => now(),
            'resolved_at' => $error ? null : now(),
            'meta' => [
                'registration_id' => $registration->id,
                'qr_code' => $qr->code,
            ],
        ]);
    }
}
