<?php

namespace App\IoT\Connectors\Local;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\IoT\Support\DeviceStateService;
use App\Models\Connector;
use App\Models\Device;

/**
 * 本地设备连接器 —— 园区自有设备的手动登记管理。
 * 设备通过面板手动绑定，无平台 API 交互。
 */
class LocalConnector implements DeviceConnector
{
    public function driver(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return '本地设备';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function credentialFields(): array
    {
        return [];
    }

    public function testConnection(Connector $connector): bool
    {
        return true;
    }

    public function pullDevices(Connector $connector): iterable
    {
        // 本地设备由用户手动创建，不走平台拉取
        return [];
    }

    public function fetchStatus(Connector $connector, Device $device): array
    {
        return [];
    }

    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool
    {
        // 本地/手动设备：无平台下行通道，将指令结果直接落库（属性变更 + 指令事件），
        // 便于扫码开门等场景在无真实网关时也能完整闭环；后续可替换为自有 TCP/MQTT 网关。
        $states = app(DeviceStateService::class);
        $states->reportProperties($device, [$code => $value]);
        $states->recordEvent($device, 'command', ['code' => $code, 'value' => $value]);

        return true;
    }
}
