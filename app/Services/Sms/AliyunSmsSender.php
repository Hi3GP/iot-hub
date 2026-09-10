<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 阿里云短信发送（RPC 风格 API，HMAC-SHA1 签名，无需安装 SDK）
 *
 * 文档：dysmsapi.aliyuncs.com  Action=SendSms  Version=2017-05-25
 */
class AliyunSmsSender
{
    private const ENDPOINT = 'https://dysmsapi.aliyuncs.com';

    public function isConfigured(): bool
    {
        return filled($this->accessKeyId())
            && filled($this->accessKeySecret())
            && filled($this->signName())
            && filled($this->templateCode());
    }

    /**
     * 发送模板短信
     *
     * @param  array<string, string>  $templateParams  模板变量，如 ['code' => '123456']
     * @return array{ok: bool, code: ?string, message: string, raw: ?array}
     */
    public function send(string $phone, array $templateParams, ?string $templateCode = null, ?string $signName = null): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'code' => 'NOT_CONFIGURED', 'message' => '短信服务未配置完整（AccessKey / 签名 / 模板）', 'raw' => null];
        }

        $params = [
            'Action' => 'SendSms',
            'Version' => '2017-05-25',
            'RegionId' => 'cn-hangzhou',
            'AccessKeyId' => $this->accessKeyId(),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => bin2hex(random_bytes(16)),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Format' => 'JSON',
            'PhoneNumbers' => $phone,
            'SignName' => $signName ?? $this->signName(),
            'TemplateCode' => $templateCode ?? $this->templateCode(),
            'TemplateParam' => json_encode($templateParams, JSON_UNESCAPED_UNICODE),
        ];

        $params['Signature'] = $this->sign($params);

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->get(self::ENDPOINT, $params);
        } catch (\Throwable $e) {
            Log::warning('阿里云短信请求异常：'.$e->getMessage(), ['phone' => $phone]);

            return ['ok' => false, 'code' => 'NETWORK_ERROR', 'message' => '短信网关连接失败，请稍后重试', 'raw' => null];
        }

        $raw = $response->json();
        $code = is_array($raw) ? ($raw['Code'] ?? 'UNKNOWN') : 'UNKNOWN';
        $ok = $code === 'OK';

        if (! $ok) {
            Log::warning('阿里云短信发送失败', [
                'phone' => $phone,
                'code' => $code,
                'message' => is_array($raw) ? ($raw['Message'] ?? '') : '',
            ]);
        }

        return [
            'ok' => $ok,
            'code' => $code,
            'message' => $ok ? '短信已发送' : $this->humanMessage($code, is_array($raw) ? ($raw['Message'] ?? '') : ''),
            'raw' => is_array($raw) ? $raw : null,
        ];
    }

    /**
     * RPC 请求签名：参数排序 → 规范化查询串 → HMAC-SHA1(base64)
     */
    private function sign(array $params): string
    {
        ksort($params);

        $canonical = collect($params)
            ->map(fn ($v, $k) => $this->percentEncode((string) $k).'='.$this->percentEncode((string) $v))
            ->implode('&');

        $stringToSign = 'GET&'.$this->percentEncode('/').'&'.$this->percentEncode($canonical);

        return base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret().'&', true));
    }

    /** 阿里云 POP 协议百分号编码（RFC3986，+ 编码为 %20、* 编码为 %2A、~ 不编码） */
    private function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }

    /** 常见错误码转中文提示 */
    private function humanMessage(string $code, string $rawMessage): string
    {
        return match ($code) {
            'InvalidAccessKeyId.NotFound', 'SignatureDoesNotMatch', 'InvalidAccessKeyId.Malformed'
                => '短信 AccessKey 配置有误，请联系管理员检查',
            'isv.MOBILE_NUMBER_ILLEGAL' => '手机号格式不正确',
            'isv.BUSINESS_LIMIT_CONTROL' => '短信发送过于频繁，请稍后再试',
            'isv.SMS_SIGNATURE_ILLEGAL', 'isv.SMS_SIGN_NOT_EXISTS'
                => '短信签名配置有误，请联系管理员检查签名名称',
            'isv.SMS_TEMPLATE_ILLEGAL', 'isv.SMS_TEMPLATE_NOT_EXISTS'
                => '短信模板配置有误，请联系管理员检查模板 CODE',
            'isv.SMS_SIGN_TEMPLATE_ILLEGAL' => '短信签名与模板不匹配，请联系管理员检查',
            'isv.OUT_OF_SERVICE' => '短信业务已停机，请联系运营商/管理员',
            'isv.AMOUNT_NOT_ENOUGH' => '短信套餐余额不足，请联系管理员充值',
            'isv.BLACK_KEY_CONTROL_LIMIT' => '该手机号在运营商黑名单中',
            'NOT_CONFIGURED' => '短信服务未配置完整，请联系管理员',
            'NETWORK_ERROR' => '短信网关连接失败，请稍后重试',
            default => '短信发送失败'.($rawMessage ? "（{$rawMessage}）" : ''),
        };
    }

    private function accessKeyId(): string
    {
        return trim((string) SystemSetting::get('sms_access_key_id', ''));
    }

    private function accessKeySecret(): string
    {
        return trim((string) SystemSetting::get('sms_access_key_secret', ''));
    }

    private function signName(): string
    {
        return trim((string) SystemSetting::get('sms_sign_name', ''));
    }

    private function templateCode(): string
    {
        return trim((string) SystemSetting::get('sms_template_code', ''));
    }
}
