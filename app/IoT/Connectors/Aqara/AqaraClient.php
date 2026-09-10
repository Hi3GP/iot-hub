<?php

namespace App\IoT\Connectors\Aqara;

use App\IoT\ConnectorException;
use App\Models\Connector;
use Illuminate\Support\Facades\Http;

/**
 * Aqara 开放平台 API 客户端（v3.0 intent 模式）
 *
 * 文档：https://opendoc.aqara.cn/
 *
 * 请求格式：
 *   POST https://open-cn.aqara.com/v3.0/open/api
 *   Body: {"intent": "...", "data": {...}}
 *
 * 签名规则：
 *   1. 按 Accesstoken, Appid, Keyid, Nonce, Time 顺序拼接
 *      "Accesstoken=xxx&Appid=xxx&Keyid=xxx&Nonce=xxx&Time=xxx"
 *   2. 末尾拼接 AppKey
 *   3. 全部小写
 *   4. MD5 32 位
 */
class AqaraClient
{
    public function __construct(protected Connector $connector)
    {
    }

    protected function baseUrl(): string
    {
        $base = trim((string) ($this->connector->settings['base_url'] ?? ''));

        if ($base && ! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.$base;
        }

        return $base ?: 'https://open-cn.aqara.com';
    }

    /**
     * 调用 Aqara API（intent 模式）
     *
     * @param string      $intent 接口意图，如 query.device.info
     * @param array       $data   请求数据
     * @param string|null $accessToken 访问令牌（部分接口不需要）
     */
    public function call(string $intent, array $data = [], ?string $accessToken = null): array
    {
        $cred = $this->connector->credentials ?? [];
        $appId = trim((string) ($cred['app_id'] ?? ''));
        $keyId = trim((string) ($cred['key_id'] ?? ''));
        $appKey = trim((string) ($cred['app_key'] ?? ''));

        if (! $appId || ! $keyId || ! $appKey) {
            throw new ConnectorException('Aqara 连接器缺少 app_id / key_id / app_key');
        }

        $accessToken ??= $this->connector->settings['access_token'] ?? null;

        $time = (string) (int) (microtime(true) * 1000);
        $nonce = bin2hex(random_bytes(8));

        $sign = $this->sign($accessToken, $appId, $keyId, $nonce, $time, $appKey);

        $headers = [
            'Appid' => $appId,
            'Keyid' => $keyId,
            'Nonce' => $nonce,
            'Time' => $time,
            'Sign' => $sign,
            'Lang' => 'zh',
            'Content-Type' => 'application/json',
        ];

        if ($accessToken) {
            $headers['Accesstoken'] = $accessToken;
        }

        $body = ['intent' => $intent, 'data' => $data];

        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->post($this->baseUrl().'/v3.0/open/api', $body);

        $result = $response->json();

        if (! is_array($result)) {
            throw new ConnectorException("Aqara 接口 [{$intent}] 返回非 JSON: ".$response->body());
        }

        $code = $result['code'] ?? -1;
        if ($code !== 0) {
            throw new ConnectorException("Aqara 接口 [{$intent}] 错误 #{$code}: ".($result['message'] ?? '未知错误'));
        }

        return $result['result'] ?? [];
    }

    /**
     * 生成 Aqara 请求签名
     */
    protected function sign(?string $accessToken, string $appId, string $keyId, string $nonce, string $time, string $appKey): string
    {
        $parts = [];

        if ($accessToken) {
            $parts[] = "Accesstoken={$accessToken}";
        }

        $parts[] = "Appid={$appId}";
        $parts[] = "Keyid={$keyId}";
        $parts[] = "Nonce={$nonce}";
        $parts[] = "Time={$time}";

        $signStr = implode('&', $parts).$appKey;

        return md5(strtolower($signStr));
    }

    /* ============ 认证相关 ============ */

    /**
     * 通过账号获取授权验证码（短信/邮箱下发）
     */
    public function getAuthCode(string $account, int $accountType = 0, string $validity = '30d'): array
    {
        return $this->call('config.auth.getAuthCode', [
            'account' => $account,
            'accountType' => $accountType,
            'accessTokenValidity' => $validity,
        ]);
    }

    /**
     * 用 authCode 换取 accessToken
     */
    public function getToken(string $authCode, string $account, int $accountType = 0): array
    {
        return $this->call('config.auth.getToken', [
            'authCode' => $authCode,
            'account' => $account,
            'accountType' => $accountType,
        ]);
    }

    /**
     * 刷新 accessToken
     */
    public function refreshToken(string $refreshToken): array
    {
        return $this->call('config.auth.refreshToken', [
            'refreshToken' => $refreshToken,
        ]);
    }

    /* ============ 设备管理 ============ */

    /**
     * 查询设备列表
     *
     * @param array  $dids       设备 ID 列表（空则查全部）
     * @param string $positionId 位置 ID
     * @param int    $pageNum    页码
     * @param int    $pageSize   每页数量
     */
    public function queryDeviceInfo(array $dids = [], string $positionId = '', int $pageNum = 1, int $pageSize = 100): array
    {
        $data = ['pageNum' => $pageNum, 'pageSize' => $pageSize];

        if ($dids) {
            $data['dids'] = $dids;
        }
        if ($positionId) {
            $data['positionId'] = $positionId;
        }

        return $this->call('query.device.info', $data);
    }

    /**
     * 查询网关下子设备
     */
    public function querySubDevice(string $did): array
    {
        return $this->call('query.device.subInfo', ['did' => $did]);
    }

    /**
     * 查询设备资源（物模型属性）详情
     */
    public function queryResourceInfo(string $model, ?string $resourceId = null): array
    {
        $data = ['model' => $model];
        if ($resourceId) {
            $data['resourceId'] = $resourceId;
        }

        return $this->call('query.resource.info', $data);
    }

    /**
     * 查询设备资源当前值
     *
     * @param array $resources [['subjectId' => 'did', 'resourceIds' => ['8.1.85']], ...]
     */
    public function queryResourceValue(array $resources): array
    {
        return $this->call('query.resource.value', ['resources' => $resources]);
    }

    /**
     * 控制设备（resource 模式）
     *
     * @param array $resources [['subjectId' => 'did', 'resourceId' => '8.1.85', 'value' => true], ...]
     */
    public function writeResource(array $resources): array
    {
        return $this->call('write.resource.device', ['resources' => $resources]);
    }

    /**
     * 控制设备（trait 模式，新版）
     *
     * @param array $traits [['did' => 'did', 'endpointId' => 2, 'functionCode' => 'Output', 'traitCode' => 'OnOff', 'value' => true], ...]
     */
    public function writeTrait(array $traits): array
    {
        return $this->call('spec.write.trait', ['traits' => $traits]);
    }

    /**
     * 查询非 Matter 设备的功能定义（用于获取 endpointId/functionCode/traitCode）
     */
    public function queryDeviceSpec(string $model): array
    {
        return $this->call('spec.query.qlinkmodel.config', ['model' => $model]);
    }
}
