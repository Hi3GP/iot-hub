<?php

namespace App\IoT\Connectors\MiHome;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\Models\Connector;
use App\Models\Device;

/**
 * 米家连接器（预留位）
 *
 * 小米 IoT 开放平台面向生态链/企业开发者，需企业资质获取 appKey + OAuth 授权后
 * 才能调用 OpenAPI（拉取设备/控制）。资质到位后按官方文档补全本类即可，
 * 核心层与面板无需改动。
 * 文档：https://iot.mi.com/v2/new/doc/home
 */
class MiHomeConnector implements DeviceConnector
{
    public function driver(): string
    {
        return 'mihome';
    }

    public function label(): string
    {
        return '米家（待资质）';
    }

    public function capabilities(): array
    {
        return []; // 资质到位后返回 ['pull','status','control','push']
    }

    public function credentialFields(): array
    {
        return [
            'app_id' => ['label' => 'AppID（企业资质）', 'required' => false],
            'app_key' => ['label' => 'AppKey', 'required' => false, 'secret' => true],
        ];
    }

    public function testConnection(Connector $connector): bool
    {
        throw new \App\IoT\ConnectorException('米家开放平台需要企业资质与 OAuth 授权，资质到位后接入');
    }

    public function pullDevices(Connector $connector): iterable
    {
        return [];
    }

    public function fetchStatus(Connector $connector, Device $device): array
    {
        return [];
    }

    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool
    {
        throw new \App\IoT\ConnectorException('米家开放平台需要企业资质与 OAuth 授权，资质到位后接入');
    }
}
