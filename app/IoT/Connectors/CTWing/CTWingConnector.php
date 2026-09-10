<?php

namespace App\IoT\Connectors\CTWing;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\IoT\Connectors\DTO\ExternalDevice;
use App\Models\Connector;
use App\Models\Device;

/**
 * 天翼云 CTWing 连接器
 *
 * 一个 Connector 实例绑定一个天翼云账号（App Key/Secret 为租户级），
 * 可配置多个产品（每个产品有独立的 productId + MasterKey）。
 * 实时数据依赖 HTTP 消息推送（webhook: /webhooks/ctwing）。
 *
 * settings.products: [
 *   ['product_id' => '312542054', 'master_key' => 'xxxx', 'name' => '门禁1代', 'category' => 'door_lock'],
 * ]
 */
class CTWingConnector implements DeviceConnector
{
    public function driver(): string
    {
        return 'ctwing';
    }

    public function label(): string
    {
        return '天翼云 CTWing';
    }

    public function capabilities(): array
    {
        return ['pull', 'status', 'control', 'push'];
    }

    public function credentialFields(): array
    {
        return [
            'app_key' => ['label' => 'App Key', 'required' => true],
            'app_secret' => ['label' => 'App Secret', 'required' => true, 'secret' => true],
        ];
    }

    /**
     * 归一化产品配置：支持新版 products 数组与旧版单个 product_id/master_key
     */
    public function products(Connector $connector): array
    {
        $settings = $connector->settings ?? [];

        if (! empty($settings['products']) && is_array($settings['products'])) {
            return collect($settings['products'])
                ->filter(fn ($p) => ! empty($p['product_id']))
                ->map(fn ($p) => [
                    'product_id' => (string) $p['product_id'],
                    'master_key' => $p['master_key'] ?? null,
                    'name' => $p['name'] ?? null,
                    'category' => $p['category'] ?? $this->guessCategory($p['name'] ?? ''),
                ])
                ->values()
                ->all();
        }

        // 旧版配置兼容
        if (! empty($settings['product_id'])) {
            return [[
                'product_id' => (string) $settings['product_id'],
                'master_key' => $settings['master_key'] ?? null,
                'name' => null,
                'category' => $settings['category'] ?? 'other',
            ]];
        }

        return [];
    }

    public function testConnection(Connector $connector): bool
    {
        $products = $this->products($connector);

        if ($products === []) {
            throw new \App\IoT\ConnectorException('尚未配置任何产品，请在下方「产品列表」中添加 CTWing 产品（产品ID + MasterKey）');
        }

        foreach ($products as $product) {
            $this->client($connector)->queryDeviceList(
                $product['master_key'],
                $product['product_id'],
                1,
                1
            );
        }

        return true;
    }

    public function pullDevices(Connector $connector): iterable
    {
        $client = $this->client($connector);

        foreach ($this->products($connector) as $product) {
            $page = 1;
            $pageSize = 20;

            do {
                $resp = $client->queryDeviceList(
                    $product['master_key'],
                    $product['product_id'],
                    $page,
                    $pageSize
                );

                $list = $resp['result']['list'] ?? [];

                foreach ($list as $d) {
                    yield new ExternalDevice(
                        externalId: (string) ($d['deviceId'] ?? ''),
                        name: $d['deviceName'] ?? ($d['deviceId'] ?? '未命名设备'),
                        model: $d['deviceModel'] ?? null,
                        category: $product['category'],
                        online: $this->mapOnline($d),
                        externalKey: $product['product_id'],
                        meta: array_merge($d, [
                            'product_id' => $product['product_id'],
                            'product_name' => $product['name'],
                            'imei' => $d['deviceSn'] ?? $d['imei'] ?? null,
                        ]),
                    );
                }

                $page++;
            } while (count($list) === $pageSize);
        }
    }

    public function fetchStatus(Connector $connector, Device $device): array
    {
        $client = $this->client($connector);
        $product = $this->resolveProduct($connector, $device);

        try {
            $resp = $client->queryDeviceStatusLatest(
                $product['master_key'] ?? null,
                $product['product_id'] ?? $device->meta['product_id'] ?? '',
                $device->external_id
            );

            $status = $resp['result'] ?? [];

            $props = [];
            if (isset($status['deviceStatus'])) {
                $props['online'] = in_array($status['deviceStatus'], [1, '1', 'online'], true);
            }

            $props['raw_payload'] = $status['deviceData'] ?? null;

            return array_filter($props, fn ($v) => $v !== null);
        } catch (\Throwable) {
            return [];
        }
    }

    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool
    {
        $product = $this->resolveProduct($connector, $device);
        $client = $this->client($connector);
        $masterKey = $product['master_key'] ?? null;
        $productId = $product['product_id'] ?? '';

        // 1. 决定下发内容（content）
        $content = $this->resolveCommandContent($connector, $device, $code, $value);

        // 2. 候选设备 ID 列表：当前设备优先，配对网络类型（WIFI↔4G）作为重试；
        //    并实时查询在线状态，把在线的设备排到最前（API 对离线设备也会返回「指令已保存」，不报错，故需主动排序）
        $deviceIds = $this->orderOnlineFirst($client, $masterKey, $productId, $this->candidateDeviceIds($device));

        $lastError = null;
        $logService = app(\App\IoT\Support\DeviceLogService::class);

        foreach ($deviceIds as $deviceId) {
            try {
                $resp = $client->createCommand($masterKey, $productId, $deviceId, $content);

                $result = $resp['result'] ?? [];

                $logService->command($device, $code, $value, true, null, [
                    'target_device_id' => $deviceId,
                    'product_id' => $productId,
                    'content' => $content,
                    'command_id' => $result['commandId'] ?? null,
                    'platform_status' => $result['commandStatus'] ?? null,
                ]);

                return true;
            } catch (\Throwable $e) {
                $lastError = $e;
                // 继续尝试下一个候选设备 ID
            }
        }

        $logService->command($device, $code, $value, false, $lastError?->getMessage(), [
            'tried_device_ids' => $deviceIds,
            'product_id' => $productId,
            'content' => $content,
        ]);

        throw new \App\IoT\ConnectorException(
            "CTWing 指令下发失败（已尝试 " . count($deviceIds) . " 个设备 ID）: " . ($lastError?->getMessage() ?? '未知错误')
        );
    }

    /**
     * 根据设备单元 code 和值解析下发内容。
     *
     * CTWing MQTT 透传设备的 content 格式为 {"payload": {...实际指令...}}
     *
     * 优先级：
     * 1. 连接器 settings.commands[code] 配置的命令映射模板
     * 2. 门禁类预设：door_lock / unlock → {"io": 1, "to": 10}
     * 3. 通用物模型：{code: value}
     */
    protected function resolveCommandContent(Connector $connector, Device $device, string $code, mixed $value): array
    {
        // 预设命令映射
        $mapping = $connector->settings['commands'][$code] ?? null;

        if ($mapping) {
            $payload = $mapping;
            // 支持 {value} 占位符替换
            array_walk_recursive($payload, function (&$v) use ($value) {
                if (is_string($v) && $v === '{value}') {
                    $v = $value;
                }
            });

            return ['payload' => $payload];
        }

        // 门禁开门/关门预设：开启 → {"io":1,"to":N}（开门 N 秒）；关闭 → {"io":0}（立即关门）
        if (in_array($code, ['door_lock', 'unlock', 'door_unlock'], true)) {
            $isClose = in_array($value, [0, '0', false, 'off', 'close', 'false'], true);

            if ($isClose) {
                return ['payload' => ['io' => 0]];
            }

            // to=N 表示开门保持秒数，可通过 settings.door_unlock_seconds 配置
            $seconds = (int) ($connector->settings['door_unlock_seconds'] ?? 10);

            return ['payload' => ['io' => 1, 'to' => $seconds]];
        }

        // 通用物模型属性
        return ['payload' => [$code => $value]];
    }

    /**
     * 获取候选设备 ID 列表（当前设备 + 配对网络类型设备）。
     *
     * 天翼云门禁设备通常有 WIFI 和 4G 两个 ID（前缀相同，后缀 -WIFI/-4G），
     * WIFI 设备不在线时需要用 4G 设备重试。
     */
    protected function candidateDeviceIds(Device $device): array
    {
        $currentId = $device->external_id;
        $ids = [$currentId];

        // 识别网络类型后缀
        if (preg_match('/^(.+)-(WIFI|4G)$/i', $currentId, $m)) {
            $base = $m[1];
            $currentNet = strtoupper($m[2]);
            $pairNet = $currentNet === 'WIFI' ? '4G' : 'WIFI';
            $pairId = "{$base}-{$pairNet}";

            // 查找配对设备
            $pair = Device::query()
                ->where('external_id', $pairId)
                ->where('id', '!=', $device->id)
                ->first();

            if ($pair) {
                $ids[] = $pairId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 查询平台侧实时在线状态，将在线的设备 ID 排到最前。
     * 注意：命令下发 API 对离线设备同样返回成功（「指令已保存」排队），不会报错，
     * 因此不能只靠失败重试，必须主动选择在线的网络类型（WIFI/4G）。
     * 排序规则：在线优先；同为在线/离线时 WIFI 优先于 4G。
     */
    protected function orderOnlineFirst(CTWingClient $client, ?string $masterKey, string $productId, array $deviceIds): array
    {
        try {
            $online = [];
            $page = 1;

            do {
                $resp = $client->queryDeviceList($masterKey, $productId, $page, 200);
                $list = $resp['result']['list'] ?? [];

                foreach ($list as $d) {
                    $id = $d['deviceId'] ?? null;
                    if ($id && in_array($id, $deviceIds, true)) {
                        $status = $d['deviceStatus'] ?? null;
                        $net = $d['netStatus'] ?? null;
                        if ($status === 1 || $status === '1' || $status === 'online' || $net === 1 || $net === '1') {
                            $online[$id] = true;
                        }
                    }
                }

                $page++;
            } while (count($list) === 200 && $page < 10);

            $wifiRank = fn (string $id): int => preg_match('/-WIFI$/i', $id) ? 1 : 0;

            usort($deviceIds, function ($a, $b) use ($online, $wifiRank) {
                $oa = $online[$a] ?? false;
                $ob = $online[$b] ?? false;

                if ($oa !== $ob) {
                    return $ob <=> $oa; // 在线优先
                }

                return $wifiRank($b) <=> $wifiRank($a); // 同状态 WIFI 优先
            });
        } catch (\Throwable) {
            // 状态查询失败时：WIFI 优先（静态排序兜底）
            usort($deviceIds, fn ($a, $b) => (preg_match('/-WIFI$/i', $b) ? 1 : 0) <=> (preg_match('/-WIFI$/i', $a) ? 1 : 0));
        }

        return $deviceIds;
    }

    protected function resolveProduct(Connector $connector, Device $device): array
    {
        $productId = $device->meta['product_id'] ?? null;

        foreach ($this->products($connector) as $product) {
            if ($product['product_id'] === $productId) {
                return $product;
            }
        }

        return $this->products($connector)[0] ?? ['product_id' => '', 'master_key' => null];
    }

    /**
     * 按产品名称猜测设备品类（门锁/门禁等）
     */
    protected function guessCategory(?string $productName): string
    {
        $name = (string) $productName;

        return match (true) {
            str_contains($name, '锁') => 'door_lock',
            str_contains($name, '门禁') || str_contains($name, '门') => 'access_control',
            str_contains($name, '摄像') || str_contains($name, '监控') => 'camera',
            str_contains($name, '水表') || str_contains($name, '电表') || str_contains($name, '表') => 'meter',
            str_contains($name, '开关') || str_contains($name, '插座') => 'switch',
            str_contains($name, '灯') || str_contains($name, '照明') => 'light',
            str_contains($name, '传感') => 'sensor_env',
            default => 'other',
        };
    }

    /**
     * CTWing 列表在线状态字段（不同协议版本字段名可能不同）
     */
    protected function mapOnline(array $device): ?bool
    {
        foreach (['onlineStatus', 'status', 'deviceStatus'] as $field) {
            if (! isset($device[$field])) {
                continue;
            }

            $v = $device[$field];

            if (in_array($v, [1, '1', 'online', 'ONLINE', true], true)) {
                return true;
            }
            if (in_array($v, [0, '0', 'offline', 'OFFLINE', false], true)) {
                return false;
            }
        }

        return null;
    }

    protected function client(Connector $connector): CTWingClient
    {
        return new CTWingClient($connector);
    }
}
