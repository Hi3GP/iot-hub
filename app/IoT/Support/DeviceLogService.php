<?php

namespace App\IoT\Support;

use App\Models\Connector;
use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * 设备交互日志统一写入入口。
 * 记录设备上下行、状态变化、命令下发、告警等完整交互轨迹，用于审计与回溯。
 */
class DeviceLogService
{
    /**
     * 记录一条设备日志
     */
    public function log(
        ?Device $device,
        string $direction,
        string $type,
        string $title,
        mixed $content = null,
        ?string $result = null,
        ?Connector $connector = null,
    ): DeviceLog {
        $operator = Auth::user();

        return DeviceLog::create([
            'device_id' => $device?->id,
            'connector_id' => $connector?->id ?? $device?->connector_id,
            'direction' => $direction,
            'type' => $type,
            'title' => $title,
            'content' => is_array($content) ? $content : ($content !== null ? ['message' => (string) $content] : null),
            'result' => $result,
            'operator_id' => $operator?->id,
            'operator_type' => $operator ? 'user' : 'system',
            'ip' => Request::ip(),
            'occurred_at' => now(),
        ]);
    }

    /** 命令下发 */
    public function command(Device $device, string $code, mixed $value, bool $success, ?string $error = null, array $extra = []): DeviceLog
    {
        return $this->log(
            device: $device,
            direction: DeviceLog::DIRECTION_OUT,
            type: DeviceLog::TYPE_COMMAND,
            title: "下发指令：{$code}",
            content: array_filter(array_merge([
                'code' => $code,
                'value' => $value,
            ], $extra, [
                'error' => $error,
            ]), fn ($v) => $v !== null && $v !== []),
            result: $success ? DeviceLog::RESULT_SUCCESS : DeviceLog::RESULT_FAILED,
        );
    }

    /** 数据上报 */
    public function data(Device $device, array $data): DeviceLog
    {
        return $this->log(
            device: $device,
            direction: DeviceLog::DIRECTION_IN,
            type: DeviceLog::TYPE_DATA,
            title: '设备数据上报',
            content: $data,
            result: DeviceLog::RESULT_SUCCESS,
        );
    }

    /** 状态变化 */
    public function status(Device $device, string $status): DeviceLog
    {
        return $this->log(
            device: $device,
            direction: DeviceLog::DIRECTION_SYSTEM,
            type: DeviceLog::TYPE_STATUS,
            title: $status === 'online' ? '设备上线' : '设备离线',
            result: DeviceLog::RESULT_SUCCESS,
        );
    }

    /** 设备同步 */
    public function sync(Connector $connector, int $count, bool $success, ?string $error = null): DeviceLog
    {
        return $this->log(
            device: null,
            direction: DeviceLog::DIRECTION_SYSTEM,
            type: DeviceLog::TYPE_SYNC,
            title: "同步设备（{$connector->name}）",
            content: ['count' => $count, 'error' => $error],
            result: $success ? DeviceLog::RESULT_SUCCESS : DeviceLog::RESULT_FAILED,
            connector: $connector,
        );
    }
}
