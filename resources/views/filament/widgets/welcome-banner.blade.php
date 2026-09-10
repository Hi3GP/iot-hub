<x-filament-widgets::widget class="dash-plain">
    <div class="wb-banner">
        <div class="wb-text">
            <p class="wb-hello">{{ $userName }} 您好，欢迎回到{{ \App\Models\SystemSetting::get('platform_name') }} 👋</p>
            <p class="wb-sub">今天是 {{ now()->format('Y年m月d日') }} 星期{{ $weekday }} · 登录时间 {{ $loginTime }} · 祝您工作顺利</p>
        </div>
        <div class="wb-actions">
            <a href="{{ admin_url('/devices/create') }}" class="wb-btn wb-btn-solid">
                <x-filament::icon icon="heroicon-o-plus-circle" />
                添加设备
            </a>
            <a href="{{ admin_url('/connectors') }}" class="wb-btn wb-btn-ghost">
                <x-filament::icon icon="heroicon-o-link" />
                接入平台
            </a>
            <a href="{{ admin_url('/access-qr-codes/create') }}" class="wb-btn wb-btn-ghost">
                <x-filament::icon icon="heroicon-o-qr-code" />
                登记二维码
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
