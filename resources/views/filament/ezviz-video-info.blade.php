@php
    /** @var \App\Models\Device $record */
    $meta = $record->meta ?? [];
    $isChannel = ! empty($meta['is_channel']) || str_contains((string) $record->external_id, '#CH');
    $serial = $meta['device_serial'] ?? (str_contains((string) $record->external_id, '#CH')
        ? explode('#CH', (string) $record->external_id)[0]
        : $record->external_id);
    $channelNo = $isChannel ? (int) ($meta['channel_no'] ?? 1) : 1;

    $ownCode = (string) ($meta['verify_code'] ?? '');
    $hostCode = '';
    $hostDevice = null;
    if ($isChannel) {
        $hostDevice = \App\Models\Device::where('connector_id', $record->connector_id)
            ->where('external_id', $serial)
            ->first();
        $hostCode = (string) ($hostDevice?->meta['verify_code'] ?? '');
    }
    $effectiveCode = $ownCode !== '' ? $ownCode : $hostCode;
    $codeSource = $ownCode !== '' ? 'channel' : ($hostCode !== '' ? 'host' : null);

    $childChannels = (! $isChannel)
        ? \App\Models\Device::where('connector_id', $record->connector_id)
            ->where('external_parent_id', $record->external_id)
            ->orderBy('external_id')
            ->get()
        : collect();
    $isNVR = ! $isChannel && $childChannels->isNotEmpty();
@endphp

<div style="display:flex; flex-direction:column; gap:16px;">
    @if ($isNVR)
        {{-- ========== 录像机（NVR）界面 ========== --}}
        {{-- 顶部信息条 --}}
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; padding:10px 16px;">
            <span style="display:inline-flex; align-items:center; gap:4px; background:#1f2937; color:#fff; border-radius:6px; padding:3px 10px; font-size:12px; font-weight:500;">
                录像机 NVR
            </span>
            <span style="font-size:12px; color:#6b7280;">序列号</span>
            <code style="background:#fff; border-radius:4px; padding:2px 8px; font-size:12px; color:#374151;">{{ $serial }}</code>
            @if ($record->status === 'online')
                <span style="color:#059669; font-size:12px; font-weight:500;">● 在线</span>
            @elseif ($record->status === 'offline')
                <span style="color:#dc2626; font-size:12px; font-weight:500;">● 离线</span>
            @else
                <span style="color:#9ca3af; font-size:12px;">● 未知</span>
            @endif
            @if ($record->model)
                <span style="color:#d1d5db; font-size:12px;">|</span>
                <span style="font-size:12px; color:#6b7280;">{{ $record->model }}</span>
            @endif
            <span style="margin-left:auto; font-size:12px; color:#9ca3af;">
                通道 {{ $childChannels->where('status','online')->count() }}/{{ $childChannels->count() }} 在线
            </span>
        </div>

        {{-- 验证码 + 直播 --}}
        <div style="display:flex; flex-wrap:wrap; gap:16px;">
            {{-- 验证码 --}}
            <div style="flex:0 0 280px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; padding:16px;">
                <div style="font-size:14px; font-weight:600; color:#374151; margin-bottom:12px;">视频加密验证码</div>
                @if ($effectiveCode !== '')
                    <div style="background:#ecfdf5; border-radius:6px; padding:8px 12px; color:#059669; font-size:13px; font-weight:500; margin-bottom:8px;">✓ 已配置</div>
                    <div style="font-size:12px; color:#6b7280;">来源：主机统一设置</div>
                    <div style="font-size:11px; color:#9ca3af; margin-top:4px;">所有未单独设置的通道自动继承</div>
                @else
                    <div style="background:#fffbeb; border-radius:6px; padding:8px 12px; color:#b45309; font-size:13px; font-weight:500; margin-bottom:8px;">⚠ 未配置</div>
                    <div style="font-size:12px; color:#6b7280;">在上方「视频验证码」字段设置</div>
                    <div style="font-size:11px; color:#9ca3af; margin-top:4px;">填写后所有通道自动继承</div>
                @endif
            </div>

            {{-- 主机预览 --}}
            <div style="flex:1; min-width:300px; border:1px solid #1f2937; border-radius:8px; overflow:hidden; background:#000;">
                <div style="display:flex; justify-content:space-between; align-items:center; background:#1f2937; padding:8px 16px;">
                    <span style="color:#fff; font-size:13px; font-weight:500;">主机预览</span>
                    <a href="/dashboard/camera/{{ $record->id }}/play" target="_blank" style="color:#22d3ee; font-size:12px; text-decoration:none;">新窗口 ↗</a>
                </div>
                <iframe src="/dashboard/camera/{{ $record->id }}/play" allow="autoplay; fullscreen"
                        style="width:100%; aspect-ratio:16/9; border:0; background:#000;"></iframe>
            </div>
        </div>

        {{-- 通道列表 --}}
        <div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; background:#fff;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f3f4f6; background:#f9fafb; padding:10px 16px;">
                <span style="font-size:14px; font-weight:600; color:#374151;">视频通道（{{ $childChannels->count() }} 路）</span>
                <span style="font-size:12px; color:#9ca3af;">
                    {{ $childChannels->where('status','online')->count() }} 在线 · {{ $childChannels->where('status','offline')->count() }} 离线
                </span>
            </div>
            @foreach ($childChannels as $ch)
                @php($chOwnCode = (string) ($ch->meta['verify_code'] ?? ''))
                <div style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-bottom:1px solid #f9fafb;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
                    <div style="width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; background:{{ $ch->status === 'online' ? '#ecfdf5' : '#f3f4f6' }}; color:{{ $ch->status === 'online' ? '#059669' : '#9ca3af' }}; font-size:12px; font-weight:700; flex-shrink:0;">
                        {{ $ch->meta['channel_no'] ?? '?' }}
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13px; font-weight:500; color:#1f2937; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $ch->name }}</div>
                        <div style="font-size:11px; color:#9ca3af; margin-top:2px;">
                            @if ($ch->status === 'online')<span style="color:#059669;">● 在线</span>@elseif ($ch->status === 'offline')<span style="color:#dc2626;">● 离线</span>@else<span style="color:#9ca3af;">● 未知</span>@endif
                            @if ($chOwnCode !== '')<span style="margin-left:8px; background:#eff6ff; color:#2563eb; border-radius:4px; padding:1px 6px;">单独验证码</span>@elseif ($ownCode !== '')<span style="margin-left:8px; color:#9ca3af;">继承主机</span>@endif
                        </div>
                    </div>
                    <a href="/dashboard/camera/{{ $ch->id }}/play" target="_blank" style="background:#f3f4f6; color:#4b5563; border-radius:6px; padding:4px 12px; font-size:12px; text-decoration:none; flex-shrink:0;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">直播</a>
                    <a href="{{ route('filament.admin.resources.devices.edit', ['record' => $ch->id]) }}" style="border:1px solid #e5e7eb; color:#6b7280; border-radius:6px; padding:4px 12px; font-size:12px; text-decoration:none; flex-shrink:0;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">编辑</a>
                </div>
            @endforeach
        </div>

    @elseif ($isChannel)
        {{-- ========== 摄像机通道界面 ========== --}}
        {{-- 顶部信息条 --}}
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; padding:10px 16px;">
            <span style="display:inline-flex; align-items:center; gap:4px; background:#eff6ff; color:#1d4ed8; border-radius:6px; padding:3px 10px; font-size:12px; font-weight:500;">
                摄像机 · 通道 {{ $channelNo }}
            </span>
            <span style="font-size:12px; color:#6b7280;">序列号</span>
            <code style="background:#fff; border-radius:4px; padding:2px 8px; font-size:12px; color:#374151;">{{ $serial }}</code>
            @if ($record->status === 'online')
                <span style="color:#059669; font-size:12px; font-weight:500;">● 在线</span>
            @elseif ($record->status === 'offline')
                <span style="color:#dc2626; font-size:12px; font-weight:500;">● 离线</span>
            @else
                <span style="color:#9ca3af; font-size:12px;">● 未知</span>
            @endif
        </div>

        {{-- 直播 + 验证码 --}}
        <div style="display:flex; flex-wrap:wrap; gap:16px;">
            {{-- 直播 --}}
            <div style="flex:1; min-width:300px; border:1px solid #1f2937; border-radius:8px; overflow:hidden; background:#000;">
                <div style="display:flex; justify-content:space-between; align-items:center; background:#1f2937; padding:8px 16px;">
                    <span style="color:#fff; font-size:13px; font-weight:500;">实时预览 · {{ $record->name }}</span>
                    <a href="/dashboard/camera/{{ $record->id }}/play" target="_blank" style="color:#22d3ee; font-size:12px; text-decoration:none;">新窗口 ↗</a>
                </div>
                <iframe src="/dashboard/camera/{{ $record->id }}/play" allow="autoplay; fullscreen"
                        style="width:100%; aspect-ratio:16/9; border:0; background:#000;"></iframe>
            </div>

            {{-- 验证码 --}}
            <div style="flex:0 0 260px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; padding:16px;">
                <div style="font-size:14px; font-weight:600; color:#374151; margin-bottom:12px;">视频加密验证码</div>
                @if ($effectiveCode !== '')
                    <div style="background:#ecfdf5; border-radius:6px; padding:8px 12px; color:#059669; font-size:13px; font-weight:500; margin-bottom:8px;">✓ 已配置</div>
                    @if ($codeSource === 'channel')
                        <div style="font-size:12px; color:#2563eb;">来源：本通道单独设置</div>
                    @else
                        <div style="font-size:12px; color:#6b7280;">来源：继承主机统一验证码</div>
                    @endif
                    <div style="font-size:11px; color:#9ca3af; margin-top:4px;">播放时自动填充</div>
                @else
                    <div style="background:#fffbeb; border-radius:6px; padding:8px 12px; color:#b45309; font-size:13px; font-weight:500; margin-bottom:8px;">⚠ 未配置</div>
                    <div style="font-size:12px; color:#6b7280;">在上方「视频验证码」字段设置</div>
                    @if ($hostDevice)
                        <a href="{{ route('filament.admin.resources.devices.edit', ['record' => $hostDevice->id]) }}" style="font-size:12px; color:#0e7490; text-decoration:underline; display:inline-block; margin-top:4px;">设置主机验证码 →</a>
                    @endif
                    <div style="font-size:11px; color:#9ca3af; margin-top:4px;">加密设备播放时也可手动输入</div>
                @endif
            </div>
        </div>

    @else
        {{-- ========== 独立摄像机（IPC，无通道） ========== --}}
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; padding:10px 16px;">
            <span style="display:inline-flex; align-items:center; gap:4px; background:#f3f4f6; color:#374151; border-radius:6px; padding:3px 10px; font-size:12px; font-weight:500;">
                摄像机 IPC
            </span>
            <span style="font-size:12px; color:#6b7280;">序列号</span>
            <code style="background:#fff; border-radius:4px; padding:2px 8px; font-size:12px; color:#374151;">{{ $serial }}</code>
            @if ($record->status === 'online')
                <span style="color:#059669; font-size:12px; font-weight:500;">● 在线</span>
            @elseif ($record->status === 'offline')
                <span style="color:#dc2626; font-size:12px; font-weight:500;">● 离线</span>
            @else
                <span style="color:#9ca3af; font-size:12px;">● 未知</span>
            @endif
            @if ($record->model)
                <span style="color:#d1d5db; font-size:12px;">|</span>
                <span style="font-size:12px; color:#6b7280;">{{ $record->model }}</span>
            @endif
        </div>

        {{-- 直播 --}}
        <div style="border:1px solid #1f2937; border-radius:8px; overflow:hidden; background:#000;">
            <div style="display:flex; justify-content:space-between; align-items:center; background:#1f2937; padding:8px 16px;">
                <span style="color:#fff; font-size:13px; font-weight:500;">实时预览 · {{ $record->name }}</span>
                <a href="/dashboard/camera/{{ $record->id }}/play" target="_blank" style="color:#22d3ee; font-size:12px; text-decoration:none;">新窗口 ↗</a>
            </div>
            <iframe src="/dashboard/camera/{{ $record->id }}/play" allow="autoplay; fullscreen"
                    style="width:100%; aspect-ratio:16/9; border:0; background:#000;"></iframe>
        </div>

        {{-- 验证码 --}}
        <div style="border:1px solid #e5e7eb; border-radius:8px; background:#fff; padding:16px;">
            <div style="font-size:14px; font-weight:600; color:#374151; margin-bottom:12px;">视频加密验证码</div>
            @if ($effectiveCode !== '')
                <div style="background:#ecfdf5; border-radius:6px; padding:8px 12px; color:#059669; font-size:13px; font-weight:500;">✓ 已配置 — 播放时自动填充</div>
            @else
                <div style="background:#fffbeb; border-radius:6px; padding:8px 12px; color:#b45309; font-size:13px; font-weight:500; margin-bottom:8px;">⚠ 未配置</div>
                <div style="font-size:12px; color:#6b7280;">在上方「视频验证码」字段设置，播放时自动填充</div>
                <div style="font-size:11px; color:#9ca3af; margin-top:4px;">加密设备播放时也可手动输入验证码</div>
            @endif
        </div>
    @endif
</div>
