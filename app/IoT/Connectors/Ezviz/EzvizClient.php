<?php

namespace App\IoT\Connectors\Ezviz;

use App\IoT\ConnectorException;
use App\Models\Connector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 萤石云开放平台 API 客户端
 * 文档：https://open.ys7.com/help/5119
 */
class EzvizClient
{
    protected string $baseUrl = 'https://open.ys7.com';

    public function __construct(protected Connector $connector)
    {
    }

    public function token(): string
    {
        $cacheKey = "ezviz.token.{$this->connector->id}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $cred = $this->connector->credentials ?? [];

        $resp = Http::asForm()->post("{$this->baseUrl}/api/lapp/token/get", [
            'appKey' => $cred['appKey'] ?? '',
            'appSecret' => $cred['appSecret'] ?? '',
        ])->json();

        if (($resp['code'] ?? '') !== '200') {
            throw new ConnectorException("萤石获取token失败: ".($resp['msg'] ?? json_encode($resp)));
        }

        $token = $resp['data']['accessToken'];
        $expireMs = $resp['data']['expireTime'] ?? 0;

        // 提前 5 分钟过期
        $ttl = max(60, (int) (($expireMs / 1000) - time() - 300));
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public function request(string $path, array $params = []): array
    {
        $resp = Http::asForm()->post("{$this->baseUrl}{$path}", array_merge($params, [
            'accessToken' => $this->token(),
        ]))->json();

        // token 失效 → 清缓存重试一次
        if (($resp['code'] ?? '') === '10002') {
            Cache::forget("ezviz.token.{$this->connector->id}");

            $resp = Http::asForm()->post("{$this->baseUrl}{$path}", array_merge($params, [
                'accessToken' => $this->token(),
            ]))->json();
        }

        if (($resp['code'] ?? '') !== '200') {
            throw new ConnectorException("萤石接口 [{$path}] 调用失败: ".($resp['msg'] ?? json_encode($resp)));
        }

        return $resp;
    }

    /**
     * 全量设备列表（自动翻页）
     */
    public function deviceList(int $pageSize = 50): array
    {
        $devices = [];
        $page = 0;

        do {
            $resp = $this->request('/api/lapp/device/list', [
                'pageStart' => $page,
                'pageSize' => $pageSize,
            ]);

            $list = $resp['data'] ?? [];
            $devices = array_merge($devices, $list);
            $page++;
        } while (count($list) === $pageSize);

        return $devices;
    }

    public function deviceInfo(string $deviceSerial): array
    {
        return $this->request('/api/lapp/device/info', [
            'deviceSerial' => $deviceSerial,
        ])['data'] ?? [];
    }

    /**
     * 摄像头/视频通道列表（含 NVR 下挂通道，自动翻页）
     * 返回每条通道：deviceSerial / channelNo / cameraName / status 等
     */
    public function cameraList(int $pageSize = 50): array
    {
        $cameras = [];
        $page = 0;

        do {
            $resp = $this->request('/api/lapp/camera/list', [
                'pageStart' => $page,
                'pageSize' => $pageSize,
            ]);

            $list = $resp['data'] ?? [];
            $cameras = array_merge($cameras, $list);
            $page++;
        } while (count($list) === $pageSize);

        return $cameras;
    }

    /**
     * 获取实时播放地址（H5 可直接用 video/EZUIKit 播放）
     * protocol: 1-ezopen 2-hls 3-rtmp 4-flv 5-llhls
     */
    public function liveAddress(string $deviceSerial, int $channelNo = 1, int $protocol = 2): string
    {
        return $this->request('/api/lapp/v2/live/address/get', [
            'deviceSerial' => $deviceSerial,
            'channelNo' => $channelNo,
            'protocol' => $protocol,
        ])['data']['url'] ?? '';
    }

    public function setDefence(string $deviceSerial, bool $on): bool
    {
        $this->request('/api/lapp/device/defence/set', [
            'deviceSerial' => $deviceSerial,
            'isDefence' => $on ? 1 : 0,
        ]);

        return true;
    }
}
