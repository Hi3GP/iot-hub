<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * 系统设置（键值存储）
 *
 * 平台名称、侧栏底部信息等可在「系统管理 → 系统设置」中修改。
 * 读取走缓存，保存时自动刷新。
 */
class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    /** 默认值（未配置时使用，同时作为设置页的初始值） */
    public const DEFAULTS = [
        'platform_name' => '物联网中台',
        'platform_short' => 'IoT Hub',
        'login_subtitle' => '智慧园区设备统一接入与联动管理平台',
        'logo' => '',
        'favicon' => '',
        'footer_title' => '物联网中台 · IoT Hub',
        'footer_copyright' => '© 2026 智慧园区物联网平台',
        'footer_icp' => '',
        'support_phone' => '',
        'support_email' => '',

        // 后台管理入口路径（安全加固，不使用默认 /admin）
        'admin_path' => 'lans-console',

        // 支付宝实名认证（预留）
        'alipay_app_id' => '',
        'alipay_private_key' => '',
        'alipay_public_key' => '',
        'alipay_gateway' => 'https://openapi.alipay.com/gateway.do',

        // 微信开放平台（预留）
        'wechat_app_id' => '',
        'wechat_app_secret' => '',
        'wechat_mch_id' => '',
        'wechat_api_key' => '',

        // 短信服务（阿里云短信，用于访客登记手机号验证）
        'sms_enabled' => false,
        'sms_access_key_id' => '',
        'sms_access_key_secret' => '',
        'sms_sign_name' => '',
        'sms_template_code' => 'SMS_491315184',
        'sms_code_ttl' => '5',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $default ??= self::DEFAULTS[$key] ?? null;

        try {
            return Cache::rememberForever(
                "system_setting:{$key}",
                fn () => static::query()->find($key)?->value ?? $default
            );
        } catch (\Throwable) {
            // 迁移未执行/数据库不可用时回退默认值
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("system_setting:{$key}");
    }
}
