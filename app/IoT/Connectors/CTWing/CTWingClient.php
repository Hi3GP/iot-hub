<?php

namespace App\IoT\Connectors\CTWing;

use App\IoT\ConnectorException;
use App\Models\Connector;
use Illuminate\Support\Facades\Http;

/**
 * 天翼云 CTWing (AEP) API 客户端
 * 文档：https://www.ctwing.cn/dyts/103
 *
 * 签名算法（官方 SDK 实现）：
 * 1. 取 headers + query（去掉 timestamp/version），ksort 排序
 * 2. 拼接 "application:KEY\ntimestamp:TS\n" + "k:v\n" × N + body + "\n"
 * 3. signature = base64(hmac_sha1(string, appSecret))
 */
class CTWingClient
{
    public function __construct(protected Connector $connector)
    {
    }

    protected function baseUrl(): string
    {
        $base = trim((string) ($this->connector->settings['base_url'] ?? ''));

        // 允许填写不带协议的域名
        if ($base && ! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.$base;
        }

        // 仅允许官方开放网关；误填（租户号/控制台地址）时回退默认
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        if ($base === '' || $host !== 'ag-api.ctwing.cn') {
            $base = 'https://ag-api.ctwing.cn';
        }

        return rtrim($base, '/');
    }

    public function get(string $path, array $query = [], array $headers = [], ?string $version = null): array
    {
        return $this->request('GET', $path, $query, [], $headers, $version);
    }

    public function post(string $path, array $json = [], array $query = [], array $headers = [], ?string $version = null): array
    {
        return $this->request('POST', $path, $query, $json, $headers, $version);
    }

    protected function request(string $method, string $path, array $query, array $json, array $headers, ?string $version): array
    {
        $cred = $this->connector->credentials ?? [];
        $appKey = trim((string) ($cred['app_key'] ?? ''));
        $appSecret = trim((string) ($cred['app_secret'] ?? ''));

        if (! $appKey || ! $appSecret) {
            throw new ConnectorException('CTWing 连接器缺少 app_key / app_secret');
        }

        $version ??= '20190507012134';
        // 平台校验毫秒级时间戳（允许偏差 30s），秒级会被判 Timestamp expired
        $timestamp = (string) (int) (microtime(true) * 1000);

        $body = $json ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        // 签名：headers（除 timestamp/version）+ query 参与排序
        $signParams = $headers + $query;
        unset($signParams['timestamp'], $signParams['version']);

        ksort($signParams);

        // 与官方 SDK 一致：每行 k:v\n；body 非空时才追加 body+"\n"（GET 空 body 结尾只有一个 \n）
        $string = "application:{$appKey}\ntimestamp:{$timestamp}\n";
        foreach ($signParams as $k => $v) {
            $string .= "{$k}:{$v}\n";
        }
        if ($body !== '') {
            $string .= $body."\n";
        }

        $signature = base64_encode(hash_hmac('sha1', $string, $appSecret, true));

        $resp = Http::withHeaders(array_merge([
            'application' => $appKey,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'version' => $version,
            'Accept' => 'application/json',
        ], $headers, $body !== '' ? ['Content-Type' => 'application/json; charset=UTF-8'] : []))
            ->send($method, "{$this->baseUrl()}{$path}".($query ? '?'.http_build_query($query) : ''), $body !== '' ? ['body' => $body] : [])
            ->json();

        if (isset($resp['code']) && $resp['code'] != 0) {
            $this->fail($path, (string) ($resp['msg'] ?? json_encode($resp, JSON_UNESCAPED_UNICODE)));
        }

        // 网关鉴权/签名错误返回 {error_code, error_desc}（如 401 Signature not matched）
        if (isset($resp['error_code'])) {
            $this->fail($path, (string) ($resp['error_desc'] ?? json_encode($resp, JSON_UNESCAPED_UNICODE)));
        }

        return $resp;
    }

    protected function fail(string $path, string $msg): never
    {
        // 未配置产品 MasterKey 导致的授权失败，给出可操作提示
        if (stripos($msg, 'MasterKey') !== false) {
            $msg .= '（提示：产品授权需要 MasterKey，请在 CTWing 控制台「产品管理 → 产品详情」点钥匙图标查看，并填入连接器产品列表）';
        }

        throw new ConnectorException("CTWing 接口 [{$path}] 调用失败: {$msg}");
    }

    /**
     * 查询产品下设备列表
     */
    public function queryDeviceList(?string $masterKey, string $productId, int $pageNow = 1, int $pageSize = 20): array
    {
        return $this->get('/aep_device_management/devices', [
            'productId' => $productId,
            'searchValue' => '',
            'pageNow' => $pageNow,
            'pageSize' => $pageSize,
        ], $this->masterHeaders($masterKey), '20190507012134');
    }

    /**
     * 查询单设备详情
     */
    public function queryDevice(?string $masterKey, string $productId, string $deviceId): array
    {
        return $this->get('/aep_device_management/device', [
            'productId' => $productId,
            'deviceId' => $deviceId,
        ], $this->masterHeaders($masterKey), '20181031202139');
    }

    /**
     * 查询设备最新状态数据
     */
    public function queryDeviceStatusLatest(?string $masterKey, string $productId, string $deviceId): array
    {
        return $this->get('/aep_device_status/deviceStatusLatest', [
            'productId' => $productId,
            'deviceId' => $deviceId,
        ], $this->masterHeaders($masterKey), '20201124162412');
    }

    /**
     * 下发设备命令
     *
     * @param string|null $masterKey 产品 MasterKey
     * @param string      $productId 产品 ID
     * @param string      $deviceId  设备 ID
     * @param array       $content   命令内容（MQTT透传JSON格式直接传，如 ['io'=>1,'to'=>10]）
     * @param int         $ttl       命令有效期（秒），默认 7200
     * @param string|null $version   API 版本号，默认从连接器 settings.command_api_version 读取，找不到时用 20190712225145
     */
    public function createCommand(?string $masterKey, string $productId, string $deviceId, array $content, int $ttl = 7200, ?string $version = null): array
    {
        $version ??= $this->connector->settings['command_api_version'] ?? '20190712225145';

        return $this->post('/aep_device_command/command', [
            'content' => $content,
            'deviceId' => $deviceId,
            'operator' => 'iot-hub',
            'productId' => $productId,
            'ttl' => $ttl,
            'level' => 1,
        ], [], $this->masterHeaders($masterKey), $version);
    }

    /**
     * 产品 MasterKey 可选：未配置时不发送该头（部分应用授权方式仅凭 App 签名即可）
     */
    protected function masterHeaders(?string $masterKey): array
    {
        return ($masterKey !== null && $masterKey !== '') ? ['MasterKey' => $masterKey] : [];
    }
}
