<?php

namespace App\IoT\Connectors\Ezviz;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\IoT\Connectors\DTO\ExternalDevice;
use App\Models\Connector;
use App\Models\Device;

class EzvizConnector implements DeviceConnector
{
    public function driver(): string
    {
        return 'ezviz';
    }

    public function label(): string
    {
        return '萤石云';
    }

    public function capabilities(): array
    {
        return ['pull', 'status', 'control', 'video', 'push'];
    }

    public function credentialFields(): array
    {
        return [
            'appKey' => ['label' => 'AppKey', 'required' => true],
            'appSecret' => ['label' => 'AppSecret', 'required' => true, 'secret' => true],
        ];
    }

    public function testConnection(Connector $connector): bool
    {
        (new EzvizClient($connector))->token();

        return true;
    }

    public function pullDevices(Connector $connector): iterable
    {
        $client = new EzvizClient($connector);

        // 1. 主设备（NVR / 摄像头 / 主机）
        $mainDevices = $client->deviceList();

        foreach ($mainDevices as $d) {
            yield new ExternalDevice(
                externalId: $d['deviceSerial'],
                name: $d['deviceName'] ?: ($d['deviceSerial']),
                model: $d['model'] ?? ($d['deviceType'] ?? null),
                category: 'camera',
                online: ($d['status'] ?? 0) == 1,
                externalKey: $d['deviceType'] ?? null,
                meta: $d,
            );
        }

        // 2. 视频通道（NVR 下挂的各路摄像头）→ 子设备
        try {
            $cameras = $client->cameraList();
        } catch (\Throwable $e) {
            // 老账号/无权限时接口可能不可用，不影响主设备同步
            $cameras = [];
        }

        // 按序列号分组：通道数 > 1 的设备，其全部通道都作为子设备拉取；
        // 单通道设备（普通 IPC）主设备本身即是通道 1，不重复拉取
        $channelsBySerial = [];
        foreach ($cameras as $cam) {
            $serial = $cam['deviceSerial'] ?? '';
            if ($serial !== '') {
                $channelsBySerial[$serial][] = $cam;
            }
        }

        foreach ($channelsBySerial as $serial => $channels) {
            if (count($channels) <= 1) {
                continue;
            }

            foreach ($channels as $cam) {
                $channelNo = (int) ($cam['channelNo'] ?? 1);
                $channelName = $cam['cameraName'] ?? null;

                yield new ExternalDevice(
                    externalId: $this->channelExternalId($serial, $channelNo),
                    name: $channelName !== null && $channelName !== ''
                        ? $channelName
                        : ($cam['deviceName'] ?? $serial)." 通道{$channelNo}",
                    model: $cam['deviceType'] ?? null,
                    category: 'camera',
                    parentId: $serial,
                    online: ($cam['status'] ?? 0) == 1,
                    externalKey: 'camera_channel',
                    meta: array_merge($cam, [
                        'device_serial' => $serial,
                        'channel_no' => $channelNo,
                        'is_channel' => true,
                    ]),
                );
            }
        }
    }

    public function fetchStatus(Connector $connector, Device $device): array
    {
        $client = new EzvizClient($connector);
        [$serial, $channelNo] = $this->resolveSerialChannel($device);

        // 通道设备：通道在线状态已在同步时写入，这里查主设备信息补充
        $info = $client->deviceInfo($serial);

        return [
            'online' => ($info['status'] ?? 0) == 1,
            'defence' => ($info['defence'] ?? 0) == 1,
            'signal' => $info['signal'] ?? null,
            'channel_no' => $channelNo,
        ];
    }

    public function sendCommand(Connector $connector, Device $device, string $code, mixed $value): bool
    {
        $client = new EzvizClient($connector);
        [$serial] = $this->resolveSerialChannel($device);

        return match ($code) {
            'defence' => $client->setDefence($serial, (bool) $value),
            default => throw new \App\IoT\ConnectorException("萤石暂不支持控制属性 [{$code}]"),
        };
    }

    /** 获取实时视频播放地址（通道设备自动带 channelNo） */
    public function liveUrl(Connector $connector, Device $device, ?int $channelNo = null, int $protocol = 2): string
    {
        $client = new EzvizClient($connector);
        [$serial, $deviceChannel] = $this->resolveSerialChannel($device);

        return $client->liveAddress($serial, $channelNo ?? $deviceChannel, $protocol);
    }

    /**
     * 获取 EZUIKit 播放信息（支持加密设备：播放器内输入设备验证码）
     *
     * @return array{url:string, token:string, serial:string, channel:int, encrypted:bool}
     */
    public function streamInfo(Connector $connector, Device $device): array
    {
        $client = new EzvizClient($connector);
        [$serial, $channelNo] = $this->resolveSerialChannel($device);

        // 验证码优先级：通道单独设置 > 主机统一设置
        // 加密设备验证码使用 ezopen://{code}@ 协议格式
        // （?code= 查询参数格式会被官方 iframe 播放器判定为 "ezopen协议格式有误"）
        $verifyCode = $this->resolveVerifyCode($device);
        $url = $verifyCode !== ''
            ? 'ezopen://'.rawurlencode($verifyCode)."@open.ys7.com/{$serial}/{$channelNo}.live"
            : "ezopen://open.ys7.com/{$serial}/{$channelNo}.live";

        return [
            'url' => $url,
            'token' => $client->token(),
            'serial' => $serial,
            'channel' => $channelNo,
            'encrypted' => (string) ($device->meta['encryptStatus'] ?? '') === '1' || $verifyCode !== '',
            'verify_code_source' => $verifyCode !== '' ? $this->verifyCodeSource($device) : null,
        ];
    }

    /**
     * 解析验证码：通道自身 > 主机统一设置
     */
    protected function resolveVerifyCode(Device $device): string
    {
        // 通道设备自身有验证码 → 优先使用
        $own = (string) ($device->meta['verify_code'] ?? '');
        if ($own !== '') {
            return $own;
        }

        // 通道设备 → 查主机（external_parent_id）的验证码
        if (! empty($device->meta['is_channel']) || str_contains((string) $device->external_id, '#CH')) {
            $parent = Device::where('connector_id', $device->connector_id)
                ->where('external_id', $device->external_parent_id ?? $device->meta['device_serial'] ?? '')
                ->first();
            if ($parent) {
                return (string) ($parent->meta['verify_code'] ?? '');
            }
        }

        // 主设备自身
        return $own;
    }

    /**
     * 标记验证码来源（调试/UI 展示用）
     */
    protected function verifyCodeSource(Device $device): string
    {
        if (! empty($device->meta['verify_code'])) {
            return 'channel';
        }

        return 'host';
    }

    /**
     * 通道设备 external_id：{serial}#CH{channelNo}
     */
    public static function channelExternalId(string $serial, int $channelNo): string
    {
        return $serial.'#CH'.$channelNo;
    }

    /**
     * 从设备解析萤石序列号与通道号
     * 通道设备：meta.device_serial / meta.channel_no；普通设备：external_id / 通道 1
     *
     * @return array{0:string,1:int}
     */
    protected function resolveSerialChannel(Device $device): array
    {
        $meta = $device->meta ?? [];

        if (! empty($meta['is_channel'])) {
            return [$meta['device_serial'], (int) ($meta['channel_no'] ?? 1)];
        }

        // 兼容 external_id 中携带 #CHn 的情况
        if (str_contains((string) $device->external_id, '#CH')) {
            [$serial, $ch] = explode('#CH', (string) $device->external_id, 2);

            return [$serial, (int) $ch];
        }

        return [(string) $device->external_id, 1];
    }
}
