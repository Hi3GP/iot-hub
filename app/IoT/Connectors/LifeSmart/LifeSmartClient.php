<?php

namespace App\IoT\Connectors\LifeSmart;

use App\IoT\ConnectorException;
use App\Models\Connector;
use Illuminate\Support\Facades\Http;

/**
 * LifeSmart 云起开放平台 API 客户端
 * 文档：LifeSmartApi.pdf
 *
 * 请求格式：POST {svrurl}/api.{Method}，JSON body:
 * {"id":1,"method":"EpGetAll","system":{"ver":"1.0","lang":"zh-cn","userid":..,"appkey":..,"time":..,"sign":..},"params":{}}
 * 签名：MD5("method:X,<params按升序 k:v 逗号连接>,time:T,userid:U,usertoken:UT,appkey:AK,apptoken:AT")
 */
class LifeSmartClient
{
    public function __construct(protected Connector $connector)
    {
    }

    protected function baseUrl(): string
    {
        // 授权返回的 svrurl（按用户区域），默认国内主站
        return rtrim($this->connector->credentials['svrurl'] ?? 'https://api.ilifesmart.com/app', '/');
    }

    public function call(string $method, array $params = []): array
    {
        $cred = $this->connector->credentials ?? [];

        foreach (['appkey', 'apptoken', 'usertoken', 'userid'] as $field) {
            if (empty($cred[$field])) {
                throw new ConnectorException("LifeSmart 连接器缺少凭证 [{$field}]");
            }
        }

        $time = time();

        // 签名原始串：method + params(升序) + time + userid + usertoken + appkey + apptoken
        $parts = ["method:{$method}"];
        $sorted = $params;
        ksort($sorted);
        foreach ($sorted as $k => $v) {
            $parts[] = "{$k}:{$v}";
        }
        $parts[] = "time:{$time}";
        $parts[] = "userid:{$cred['userid']}";
        $parts[] = "usertoken:{$cred['usertoken']}";
        $parts[] = "appkey:{$cred['appkey']}";
        $parts[] = "apptoken:{$cred['apptoken']}";

        $sign = md5(implode(',', $parts));

        $resp = Http::asJson()->post("{$this->baseUrl()}/api.{$method}", [
            'id' => random_int(1, 999999),
            'method' => $method,
            'system' => [
                'ver' => '1.0',
                'lang' => 'zh-cn',
                'userid' => $cred['userid'],
                'appkey' => $cred['appkey'],
                'time' => $time,
                'sign' => $sign,
            ],
            'params' => $params ?: (object) [],
        ])->json();

        if (($resp['code'] ?? '') !== 'success') {
            throw new ConnectorException("LifeSmart 接口 [{$method}] 调用失败: ".($resp['msg'] ?? json_encode($resp)));
        }

        return $resp['data'] ?? [];
    }

    /**
     * 获取所有授权设备（含网关下子设备）
     */
    public function epGetAll(int $degree = 1): array
    {
        return $this->call('EpGetAll', ['degree' => $degree]);
    }

    /**
     * 控制设备
     * 例：EpSet(['agt'=>..,'me'=>..,'idx'=>'io','type'=>129,'val'=>1])
     * idx/type 按《LifeSmart智慧设备规格属性说明》，可在产品 thing_model.commands 配置
     */
    public function epSet(string $agt, string $me, array $extra = []): array
    {
        return $this->call('EpSet', array_merge(['agt' => $agt, 'me' => $me], $extra));
    }

    /**
     * 生成用户授权跳转 URL（用户登录授权后回调携带 userid/usertoken）
     */
    public static function authUrl(string $appkey, string $apptoken, string $callbackUrl, int $time = 0): string
    {
        $time = $time ?: time();

        // 签名原始串（除 apptoken 外按字母排序，apptoken 拼在末尾一并参与 MD5）：
        // appkey=***&auth_callback=***&time=***&apptoken=***
        $raw = "appkey={$appkey}&auth_callback=".urlencode($callbackUrl)."&time={$time}&apptoken={$apptoken}";
        $sign = md5($raw);

        return 'https://api.ilifesmart.com/app/auth?'.http_build_query([
            'appkey' => $appkey,
            'auth_callback' => $callbackUrl,
            'time' => $time,
            'sign' => $sign,
        ]);
    }
}
