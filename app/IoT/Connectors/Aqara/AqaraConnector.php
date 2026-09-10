<?php

namespace App\IoT\Connectors\Aqara;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\IoT\Connectors\DTO\ExternalDevice;
use App\Models\Connector;
use App\Models\Device;

/**
 * Aqara（绿米）开放平台连接器
 *
 * 文档：https://opendoc.aqara.cn/
 *
 * 认证方式：Aqara 账号授权
 *   1. getAuthCode(account) → 短信/邮箱收到验证码
 *   2. getToken(authCode, account) → accessToken + refreshToken
 *   3. refreshToken(refreshToken) → 新 token
 *
 * settings 字段：
 *   - access_token: 访问令牌
 *   - refresh_token: 刷新令牌
 *   - base_url: 可选，自定义网关
 *
 * credentials 字段：
 *   - app_id: 应用 ID
 *   - key_id: 应用 Key ID
 *   - app_key: 应用 AppKey（用于签名）
 */
class AqaraConnector implements DeviceConnector
{
    /**
     * 统一属性码 → Aqara resourceId 默认映射
     * 不同型号设备的 resourceId 可能不同，优先从产品 thing_model 中读取自定义映射
     */
    protected const DEFAULT_RESOURCE_MAP = [
        'switch' => '8.1.85',
        'switch_2' => '8.2.85',
        'switch_3' => '8.3.85',
        'door_contact' => '3.1.85',
        'temperature' => '0.1.85',
        'humidity' => '0.2.85',
        'battery' => '14.1.85',
        'illumination' => '4.1.85',
        'motion' => '13.1.85',
        'smoke' => '2.1.85',
        'water_leak' => '2.2.85',
        'button' => '1.1.85',
    ];

    public function driver(): string
    {
        return 'aqara';
    }

    public function label(): string
    {
        return 'Aqara 绿米';
    }

    public function capabilities(): array
    {
        return ['pull', 'status', 'control', 'push'];
    }

    public function credentialFields(): array
    {
        return [
            'app_id' => ['label' => 'App ID', 'required' => true],
            'key_id' => ['label' => 'Key ID', 'required' => true],
            'app_key' => ['label' => 'App Key', 'required' => true, 'secret' => true],
        ];
    }

    public function testConnection(Connector $connector): bool
    {
        $client = $this->client($connector);
        // 尝试查询第一页设备列表
        $client->queryDeviceInfo([], '', 1, 1);

        return true;
    }

    public function pullDevices(Connector $connector): iterable
    {
        $client = $this->client($connector);
        $page = 1;
        $pageSize = 100;

        do {
            $resp = $client->queryDeviceInfo([], '', $page, $pageSize);
            $list = $resp['data'] ?? [];

            foreach ($list as $d) {
                $model = $d['model'] ?? null;

                yield new ExternalDevice(
                    externalId: (string) ($d['did'] ?? ''),
                    name: $d['deviceName'] ?? ($d['did'] ?? '未命名设备'),
                    model: $model,
                    category: $this->guessCategory($model ?? ''),
                    parentId: $d['parentDid'] ?: null,
                    online: isset($d['state']) ? (int) $d['state'] === 1 : null,
                    externalKey: $model,
                    meta: $d,
                );
            }

            $page++;
        } while (count($list) === $pageSize);
    }

    public function fetchStatus(Connector $connector, Device $device): array
    {
        $client = $this->client($connector);

        try {
            // 查询设备所有开放资源的当前值
            $resp = $client->queryResourceValue([
                ['subjectId' => $device->external_id],
            ]);

            $items = $resp[0]['data'] ?? [];

            $props = [];
            foreach ($items as $item) {
                $resourceId = $item['resourceId'] ?? null;
                $value = $item['value'] ?? null;

                if ($resourceId === null || $value === null) {
                    continue;
                }

                // resourceId → 统一属性码
                $code = $this->resourceIdToCode($resourceId);
                $props[$code ?? $resourceId] = $value;
            }

            // 在线状态
            if (isset($device->meta['state'])) {
                $props['online'] = (int) $device->meta['state'] === 1;
            }

            return $props;
        } catch (\Throwable) {
            return [];
        }
    }

    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool
    {
        $client = $this->client($connector);

        // 统一属性码 → resourceId
        $resourceId = $this->codeToResourceId($code);

        if (! $resourceId) {
            throw new \App\IoT\ConnectorException("Aqara 设备不支持的属性 [{$code}]，无法映射到 resourceId");
        }

        // bool 值转换：Aqara 开关用 true/false
        if (is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true)) {
            $value = (bool) $value;
        }

        $client->writeResource([
            [
                'subjectId' => $device->external_id,
                'resourceId' => $resourceId,
                'value' => $value,
            ],
        ]);

        return true;
    }

    /* ============ 辅助方法 ============ */

    protected function codeToResourceId(string $code): ?string
    {
        return self::DEFAULT_RESOURCE_MAP[$code] ?? null;
    }

    protected function resourceIdToCode(string $resourceId): ?string
    {
        return array_search($resourceId, self::DEFAULT_RESOURCE_MAP, true) ?: null;
    }

    /**
     * 根据设备 model 猜测品类
     */
    protected function guessCategory(string $model): string
    {
        $m = strtolower($model);

        return match (true) {
            str_contains($m, 'lock') || str_contains($m, '门锁') => 'door_lock',
            str_contains($m, 'gateway') || str_contains($m, 'hub') => 'gateway',
            str_contains($m, 'switch') || str_contains($m, 'ctrl') => 'switch',
            str_contains($m, 'plug') || str_contains($m, 'outlet') => 'switch',
            str_contains($m, 'light') || str_contains($m, 'bulb') => 'light',
            str_contains($m, 'curtain') => 'curtain',
            str_contains($m, 'sensor') || str_contains($m, 'magnet') => 'sensor_env',
            str_contains($m, 'camera') || str_contains($m, 'cam') => 'camera',
            str_contains($m, 'thermostat') || str_contains($m, 'temp') => 'thermostat',
            default => 'other',
        };
    }

    protected function client(Connector $connector): AqaraClient
    {
        return new AqaraClient($connector);
    }
}
