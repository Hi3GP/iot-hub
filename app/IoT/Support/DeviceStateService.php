<?php

namespace App\IoT\Support;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\DeviceProperty;
use App\Models\DevicePropertyHistory;
use Carbon\CarbonInterface;

/**
 * 设备状态/属性/事件的统一写入入口。
 * webhook 推送、轮询刷新、连接器同步都汇聚到这里，保证联动和告警有单一数据源。
 */
class DeviceStateService
{
    /**
     * 上报一组统一属性
     */
    public function reportProperties(Device $device, array $props, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        foreach ($props as $code => $value) {
            if ($value === null) {
                continue;
            }

            $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;

            $existing = DeviceProperty::query()
                ->where('device_id', $device->id)
                ->where('code', $code)
                ->first();

            if ($existing && (string) $existing->value === (string) $value) {
                $existing->update(['reported_at' => $at]);

                continue;
            }

            DeviceProperty::updateOrCreate(
                ['device_id' => $device->id, 'code' => $code],
                ['value' => $value, 'reported_at' => $at]
            );

            DevicePropertyHistory::create([
                'device_id' => $device->id,
                'code' => $code,
                'value' => $value,
                'reported_at' => $at,
            ]);

            DeviceEvent::create([
                'device_id' => $device->id,
                'type' => 'property_changed',
                'data' => ['code' => $code, 'value' => $value],
                'occurred_at' => $at,
            ]);
        }

        // 记录数据上报日志（排除 raw_payload 这种原始数据，避免冗余）
        $loggable = array_filter($props, fn ($k) => $k !== 'raw_payload', ARRAY_FILTER_USE_KEY);
        if ($loggable) {
            app(DeviceLogService::class)->data($device, $loggable);
        }
    }

    /**
     * 上报在线状态（含离线自动告警 / 恢复自动解除）
     */
    public function reportStatus(Device $device, ?bool $online, ?CarbonInterface $at = null): void
    {
        if ($online === null) {
            return;
        }

        $status = $online ? 'online' : 'offline';

        if ($device->status === $status) {
            return;
        }

        $device->update([
            'status' => $status,
            'status_updated_at' => $at ?? now(),
        ]);

        $this->recordEvent($device, $online ? 'online' : 'offline', occurredAt: $at);

        // 记录状态变化日志
        app(DeviceLogService::class)->status($device, $status);

        if ($online) {
            // 恢复上线 → 自动解除离线告警
            Alert::where('device_id', $device->id)
                ->where('type', 'offline')
                ->where('status', 'active')
                ->update(['status' => 'resolved', 'resolved_at' => now()]);
        } else {
            Alert::create([
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'level' => 'warning',
                'type' => 'offline',
                'title' => "设备离线：{$device->name}",
                'content' => "设备 [{$device->name}] ({$device->external_id}) 与平台失去连接",
                'triggered_at' => $at ?? now(),
            ]);
        }
    }

    /**
     * 仅更新某个通信通道（WIFI/4G）行的在线状态，不产生事件/日志/告警。
     * 用于 WIFI/4G 双通道合并产品：副通道行保留各自在线状态用于监测，
     * 事件、属性、告警统一归属主通道设备（reportStatus 作用于主设备）。
     */
    public function markChannelStatus(Device $channel, bool $online): void
    {
        $status = $online ? 'online' : 'offline';

        if ($channel->status === $status) {
            return;
        }

        $channel->update([
            'status' => $status,
            'status_updated_at' => now(),
        ]);
    }

    /**
     * 记录设备事件（移动侦测、门开、报警触发等）
     */
    public function recordEvent(Device $device, string $type, array $data = [], ?CarbonInterface $occurredAt = null): DeviceEvent
    {
        $event = DeviceEvent::create([
            'device_id' => $device->id,
            'type' => $type,
            'data' => $data ?: null,
            'occurred_at' => $occurredAt ?? now(),
        ]);

        $this->safeLinkage(fn ($engine) => $engine->evaluateEvent($device, $type));

        return $event;
    }

    /**
     * 联动评估不阻塞数据上报，异常单独上报
     */
    protected function safeLinkage(callable $callback): void
    {
        try {
            $callback(app(\App\IoT\Automation\LinkageEngine::class));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
