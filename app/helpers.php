<?php

if (! function_exists('admin_path')) {
    /**
     * 后台管理入口路径段（不含前导斜杠），如 'lans-console'。
     *
     * 从 system_settings 读取（带缓存），可在「系统设置」中修改；
     * 数据库不可用或未配置时回退 config('app.admin_path') 或 'lans-console'。
     */
    function admin_path(): string
    {
        $fallback = (string) (config('app.admin_path') ?: 'lans-console');

        if (! class_exists(\App\Models\SystemSetting::class)) {
            return $fallback;
        }

        try {
            $value = \App\Models\SystemSetting::get('admin_path', $fallback);
        } catch (\Throwable) {
            return $fallback;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }
}

if (! function_exists('admin_url')) {
    /**
     * 后台完整 URL：admin_url('/devices/create') → /lans-console/devices/create
     */
    function admin_url(string $path = ''): string
    {
        $base = '/'.admin_path();
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }
}
