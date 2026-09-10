<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 访客手机号短信验证码
 *
 * 安全规则：
 * - 验证码仅存服务端缓存，永不下发前端；
 * - 默认 5 分钟过期（与短信模板文案一致）；
 * - 同一手机号 60 秒内只能发送一次，每天最多 10 次；同一 IP 每小时最多 20 次；
 * - 验证码最多可尝试 5 次，超限即作废，需重新获取。
 */
class PhoneVerificationService
{
    public function __construct(private readonly AliyunSmsSender $sender)
    {
    }

    public function enabled(): bool
    {
        return (bool) SystemSetting::get('sms_enabled', false) && $this->sender->isConfigured();
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) SystemSetting::get('sms_code_ttl', 5));
    }

    /**
     * 发送验证码
     *
     * @throws \RuntimeException 发送失败时抛出（消息可直接展示给用户）
     */
    public function sendCode(string $phone, ?string $ip = null): void
    {
        $digits = $this->normalizePhone($phone);

        if (! preg_match('/^1[3-9]\d{9}$/', $digits) && strlen($digits) < 6) {
            throw new \RuntimeException('手机号格式不正确，请检查后重试。');
        }

        if (! $this->enabled()) {
            throw new \RuntimeException('短信验证暂未开放，请联系物业/管理员。');
        }

        // 频率限制：同号 60 秒一次
        if (RateLimiter::tooManyAttempts($this->rateKey('phone-min', $digits), 1)) {
            $seconds = RateLimiter::availableIn($this->rateKey('phone-min', $digits));
            throw new \RuntimeException("发送过于频繁，请 {$seconds} 秒后再获取验证码。");
        }

        // 频率限制：同号每天 10 次
        if (RateLimiter::tooManyAttempts($this->rateKey('phone-day', $digits), 10)) {
            throw new \RuntimeException('该手机号今日获取验证码次数已达上限，请明天再试。');
        }

        // 频率限制：同 IP 每小时 20 次（防刷接口）
        if ($ip && RateLimiter::tooManyAttempts($this->rateKey('ip-hour', $ip), 20)) {
            throw new \RuntimeException('请求过于频繁，请稍后再试。');
        }

        $code = (string) random_int(100000, 999999);

        $result = $this->sender->send($digits, ['code' => $code]);

        if (! $result['ok']) {
            throw new \RuntimeException($result['message'] ?: '验证码发送失败，请稍后重试。');
        }

        // 发送成功才计数、才存验证码
        RateLimiter::hit($this->rateKey('phone-min', $digits), 60);
        RateLimiter::hit($this->rateKey('phone-day', $digits), 86400);
        if ($ip) {
            RateLimiter::hit($this->rateKey('ip-hour', $ip), 3600);
        }

        Cache::put($this->codeKey($digits), [
            'code' => $code,
            'attempts' => 0,
        ], now()->addMinutes($this->ttlMinutes()));

        Log::info('访客短信验证码已发送', ['phone' => $this->maskPhone($digits)]);
    }

    /**
     * 校验验证码（成功或超过最大尝试次数后立即作废）
     */
    public function verify(string $phone, ?string $code): bool
    {
        $digits = $this->normalizePhone($phone);
        $key = $this->codeKey($digits);
        $payload = Cache::get($key);

        if (! is_array($payload) || empty($payload['code'])) {
            return false;
        }

        $input = trim((string) $code);
        if ($input === '' || ! hash_equals((string) $payload['code'], $input)) {
            $payload['attempts'] = (int) ($payload['attempts'] ?? 0) + 1;

            if ($payload['attempts'] >= 5) {
                Cache::forget($key);
            } else {
                Cache::put($key, $payload, now()->addMinutes($this->ttlMinutes()));
            }

            return false;
        }

        // 验证通过即作废，防止同一码重复使用
        Cache::forget($key);

        return true;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function codeKey(string $digits): string
    {
        return "sms:verify-code:{$digits}";
    }

    private function rateKey(string $scope, string $id): string
    {
        return "sms:{$scope}:".sha1($id);
    }

    private function maskPhone(string $digits): string
    {
        return strlen($digits) >= 11
            ? substr($digits, 0, 3).'****'.substr($digits, -4)
            : $digits;
    }
}
