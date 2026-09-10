<?php

namespace App\IoT\Connectors\LifeSmart;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\IoT\Connectors\DTO\ExternalDevice;
use App\Models\Connector;
use App\Models\Device;

/**
 * LifeSmart 云起连接器
 *
 * 凭证：appkey / apptoken / usertoken / userid（通过授权页获取，svrurl 可选）
 */
class LifeSmartConnector implements DeviceConnector
{
    /** devtype → 统一品类 粗映射 */
    protected array $categoryMap = [
        'SLP' => 'switch', 'SLT' => 'switch', 'SPOT' => 'switch',
        'SLR' => 'switch', 'SLSC' => 'sensor_security', 'SLP2' => 'switch',
        'ODNE' => 'sensor_env', 'SLEN' => 'sensor_env',
    ];

    public function driver(): string
    {
        return 'lifesmart';
    }

    public function label(): string
    {
        return 'LifeSmart 云起';
    }

    public function capabilities(): array
    {
        return ['pull', 'status', 'control', 'push'];
    }

    public function credentialFields(): array
    {
        return [
            'appkey' => ['label' => 'AppKey', 'required' => true],
            'apptoken' => ['label' => 'AppToken', 'required' => true, 'secret' => true],
            'usertoken' => ['label' => 'UserToken（授权后填写）', 'required' => true, 'secret' => true],
            'userid' => ['label' => 'UserId（授权后填写）', 'required' => true],
            'svrurl' => ['label' => '服务地址（可选，默认官方）', 'required' => false],
        ];
    }

    public function testConnection(Connector $connector): bool
    {
        (new LifeSmartClient($connector))->epGetAll(0);

        return true;
    }

    public function pullDevices(Connector $connector): iterable
    {
        $devices = (new LifeSmartClient($connector))->epGetAll(1);

        foreach ($devices as $d) {
            $agt = $d['agt'] ?? '';
            $me = (string) ($d['me'] ?? '');

            yield new ExternalDevice(
                externalId: "{$agt}:{$me}",
                name: $d['name'] ?? "{$agt}:{$me}",
                model: $d['devtype'] ?? null,
                category: $this->categoryMap[$d['devtype'] ?? ''] ?? 'other',
                parentId: $agt,
                online: ($d['stat'] ?? 0) == 1,
                externalKey: $d['devtype'] ?? null,
                meta: $d,
            );
        }
    }

    public function fetchStatus(Connector $connector, Device $device): array
    {
        [$agt, $me] = explode(':', $device->external_id);

        $list = (new LifeSmartClient($connector))->epGetAll(1);

        foreach ($list as $d) {
            if (($d['agt'] ?? '') === $agt && (string) ($d['me'] ?? '') === $me) {
                return $this->mapDataToProperties($d);
            }
        }

        return [];
    }

    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool
    {
        [$agt, $me] = explode(':', $device->external_id);
        $client = new LifeSmartClient($connector);

        // 命令参数优先从产品 thing_model.commands[code] 读取；缺省按开关处理
        $spec = $device->product?->thing_model['commands'][$code] ?? ['idx' => 'io', 'type' => 129];

        $client->epSet($agt, $me, array_merge($spec, ['val' => is_bool($value) ? (int) $value : $value]));

        return true;
    }

    /**
     * data.IO 信息 → 统一属性
     */
    protected function mapDataToProperties(array $d): array
    {
        $props = ['online' => ($d['stat'] ?? 0) == 1];

        $data = $d['data'] ?? [];
        if (is_array($data)) {
            if (array_key_exists('io', $data)) {
                $props['switch'] = (bool) $data['io'];
            }
            if (array_key_exists('dim', $data)) {
                $props['brightness'] = $data['dim'];
            }
            if (array_key_exists('battery', $data)) {
                $props['battery'] = $data['battery'];
            }
        }

        return $props;
    }
}
