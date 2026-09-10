<?php

namespace App\IoT\Connectors\Contracts;

use App\Models\Connector;
use App\Models\Device;

/**
 * 平台连接器契约 —— 所有第三方物联网平台的接入统一接口。
 *
 * 新增平台支持：实现本接口 + 在 ConnectorManager 注册即可，核心层无感知。
 */
interface DeviceConnector
{
    /** 驱动标识，与 connectors.driver 对应 */
    public function driver(): string;

    /** 平台显示名 */
    public function label(): string;

    /** 平台能力：pull(拉设备) status(拉状态) control(下发命令) video(视频) push(事件推送) */
    public function capabilities(): array;

    /** 该平台需要哪些凭证字段（用于面板动态渲染表单） */
    public function credentialFields(): array;

    /** 连接测试 */
    public function testConnection(Connector $connector): bool;

    /** 拉取平台设备列表 @return iterable<\App\IoT\Connectors\DTO\ExternalDevice> */
    public function pullDevices(Connector $connector): iterable;

    /** 拉取设备最新状态，返回 [统一属性码 => 值] */
    public function fetchStatus(Connector $connector, Device $device): array;

    /** 下发控制命令 */
    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool;
}
