@php($uid = 'device-live-'.uniqid())

<iframe id="{{ $uid }}"
        src="/dashboard/camera/{{ $deviceId }}/play"
        allow="autoplay; fullscreen"
        class="w-full rounded-lg border-0 bg-black"
        style="aspect-ratio: 16/9;"></iframe>
<p class="mt-2 text-xs text-gray-400">
    若画面提示输入密码，请填写设备<strong>视频加密验证码</strong>（安装时设置，可能为任意位数/字符，非固定 6 位）。
    也可在设备编辑页「视频验证码」字段中保存，之后自动填充。
</p>
