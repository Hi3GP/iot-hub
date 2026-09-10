{{-- 登录页底部版权信息：内容在「系统管理 → 系统设置」中配置 --}}
@php
    use App\Models\SystemSetting;
@endphp
<div class="fi-auth-footer mt-8 text-center">
    <p class="text-sm font-medium text-gray-500">
        {{ SystemSetting::get('footer_title', '物联网中台 · IoT Hub') }}
    </p>
    <p class="mt-1.5 text-xs text-gray-400">
        {{ SystemSetting::get('footer_copyright', '© 2026 智慧园区物联网平台') }}
    </p>
    @if ($icp = SystemSetting::get('footer_icp'))
        <p class="mt-1 text-xs text-gray-400">{{ $icp }}</p>
    @endif
    @if (($phone = trim((string) SystemSetting::get('support_phone'))) || ($email = trim((string) SystemSetting::get('support_email'))))
        <p class="mt-2 text-xs text-gray-400">
            @if ($phone) 技术支持：{{ $phone }} @endif
            @if ($phone && $email) · @endif
            @if ($email) {{ $email }} @endif
        </p>
    @endif
</div>
