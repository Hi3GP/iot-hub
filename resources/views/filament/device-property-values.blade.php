<style>
    .iot-dpv { font-size: 14px; color: #1f2937; }
    .iot-dpv * { box-sizing: border-box; }
    .iot-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; overflow: hidden; margin-bottom: 12px; }
    .iot-card-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; border-bottom: 1px solid #f3f4f6; background: #f9fafb; padding: 9px 16px; font-size: 12px; font-weight: 500; color: #6b7280; }
    .iot-card-head .iot-sub { font-size: 11px; font-weight: 400; color: #9ca3af; }
    .iot-empty { padding: 32px 16px; text-align: center; font-size: 13px; color: #9ca3af; }

    /* 门锁状态卡 */
    .iot-status { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px; padding: 16px; }
    .iot-status-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .iot-status-icon { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; }
    .iot-status-icon.is-open { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .iot-status-icon.is-closed { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
    .iot-status-icon.is-unknown { background: #f9fafb; color: #d1d5db; border: 1px solid #e5e7eb; }
    .iot-status-title { font-size: 16px; font-weight: 600; line-height: 1.3; }
    .iot-status-title.is-open { color: #047857; }
    .iot-status-title.is-closed { color: #374151; }
    .iot-status-title.is-unknown { color: #9ca3af; }
    .iot-status-sub { margin-top: 2px; font-size: 12px; color: #9ca3af; }
    .iot-status-time { font-size: 12px; color: #9ca3af; }
    .iot-channels { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; font-size: 12px; }
    .iot-channels .iot-ch-label { color: #9ca3af; }
    .iot-ch { display: inline-flex; align-items: center; gap: 6px; }
    .iot-ch-dot { font-size: 10px; line-height: 1; }
    .iot-ch-name { font-weight: 500; color: #4b5563; }
    .iot-ch-state { font-size: 12px; }
    .iot-ch-state.is-on { color: #059669; }
    .iot-ch-state.is-off { color: #dc2626; }
    .iot-status-foot { border-top: 1px solid #f3f4f6; background: rgba(249,250,251,.7); padding: 8px 16px; font-size: 12px; line-height: 1.5; color: #9ca3af; }
    @media (min-width: 640px) { .iot-channels { margin-left: auto; } }

    /* 单元列表 */
    .iot-unit-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
    .iot-unit-row:last-child { border-bottom: 0; }
    .iot-unit-label { width: 160px; flex: 0 0 auto; }
    .iot-unit-name { font-size: 14px; font-weight: 500; color: #1f2937; }
    .iot-unit-code { margin-top: 4px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .iot-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; color: #9ca3af; }
    .iot-tag { display: inline-block; border-radius: 4px; background: #f3f4f6; padding: 2px 6px; font-size: 10px; line-height: 1.4; color: #6b7280; }
    .iot-tag-blue { background: #f0f9ff; color: #0284c7; }
    .iot-unit-value { min-width: 0; flex: 1 1 200px; }
    .iot-waiting { font-size: 13px; color: #d1d5db; }
    .iot-badge { display: inline-flex; align-items: center; border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 500; line-height: 1.4; border: 1px solid transparent; }
    .iot-badge.is-on { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .iot-badge.is-off { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
    .iot-chip { display: inline-block; max-width: 100%; word-break: break-all; border-radius: 6px; background: #f9fafb; border: 1px solid #f3f4f6; padding: 4px 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; color: #1f2937; }
    .iot-raw-btn { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; border-radius: 6px; background: #f9fafb; border: 1px solid #f3f4f6; padding: 4px 10px; font-size: 12px; color: #6b7280; list-style: none; }
    .iot-raw-btn::-webkit-details-marker { display: none; }
    .iot-raw-btn:hover { background: #f3f4f6; }
    .iot-raw-btn svg { transition: transform .15s ease; }
    details[open] .iot-raw-btn svg { transform: rotate(90deg); }
    .iot-raw-pre { margin-top: 8px; max-height: 224px; overflow: auto; border-radius: 8px; background: #f9fafb; border: 1px solid #f3f4f6; padding: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; line-height: 1.6; color: #4b5563; white-space: pre-wrap; word-break: break-all; }
    .iot-unit-time { flex: 0 0 auto; font-size: 12px; color: #9ca3af; font-variant-numeric: tabular-nums; }

    /* 交互时间线 */
    .iot-log-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; }
    .iot-log-row:last-child { border-bottom: 0; }
    .iot-log-icon { width: 24px; height: 24px; flex: 0 0 24px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; margin-top: 1px; }
    .iot-log-icon.dir-out { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
    .iot-log-icon.dir-in { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
    .iot-log-icon.dir-system { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
    .iot-log-body { min-width: 0; flex: 1; }
    .iot-log-title { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; font-size: 13px; color: #374151; }
    .iot-result { font-size: 11px; font-weight: 500; }
    .iot-result.is-success { color: #059669; }
    .iot-result.is-failed { color: #dc2626; }
    .iot-result.is-pending { color: #d97706; }
    .iot-ch-badge { display: inline-block; border-radius: 4px; background: #f3f4f6; padding: 2px 6px; font-size: 10px; line-height: 1.4; color: #6b7280; }
    .iot-detail { margin-top: 4px; }
    .iot-detail-summary { cursor: pointer; font-size: 11px; color: #9ca3af; list-style: none; display: inline-flex; align-items: center; gap: 4px; }
    .iot-detail-summary::-webkit-details-marker { display: none; }
    .iot-detail-summary:hover { color: #4b5563; }
    .iot-detail-summary svg { transition: transform .15s ease; }
    .iot-detail[open] .iot-detail-summary svg { transform: rotate(90deg); }
    .iot-detail-pre { margin-top: 6px; max-height: 176px; overflow: auto; border-radius: 8px; background: #f9fafb; border: 1px solid #f3f4f6; padding: 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; line-height: 1.6; color: #6b7280; white-space: pre-wrap; word-break: break-all; }
    .iot-log-time { flex: 0 0 auto; font-size: 12px; color: #9ca3af; font-variant-numeric: tabular-nums; }
</style>

@php
    use App\IoT\ThingModel;
    use App\Models\DeviceLog;

    $product = $record->product;
    $productDefs = collect(ThingModel::productProperties($product))->keyBy('code');

    $reported = $record->properties()->latest('reported_at')->get();
    $values = $reported->pluck('value', 'code');
    $reportedAt = $reported->pluck('reported_at', 'code');

    $hasRawUnit = isset($productDefs['raw']);

    // 展示的单元 = 产品定义的设备单元 + 实际有上报值的单元。
    // 指令型门禁设备只列真实存在/上报的单元，不再堆砌温度/湿度等无关标准单元。
    $extraNames = [
        'raw_payload' => '原始上报数据',
        'door_lock' => '门锁状态',
    ];

    $defs = $productDefs->all();
    foreach ($reported as $dp) {
        // 产品已定义 raw 单元时，raw_payload 合并进 raw 展示
        if ($dp->code === 'raw_payload') {
            if ($hasRawUnit) {
                $values['raw'] = $dp->value;
                $reportedAt['raw'] = $dp->reported_at;
            }

            continue;
        }
        if (isset($defs[$dp->code])) {
            continue;
        }

        $v = trim((string) $dp->value);
        $isJson = str_starts_with($v, '{') || str_starts_with($v, '[');

        $defs[$dp->code] = [
            'code' => $dp->code,
            'name' => $extraNames[$dp->code] ?? ThingModel::propertyName($dp->code),
            'type' => $isJson ? 'json' : 'string',
            'unit' => null,
            'options' => [],
            'writable' => false,
            'source' => 'reported',
        ];
    }

    $isCtwingDoor = $record->connector?->driver === 'ctwing'
        && in_array($record->category, ['door_lock', 'access_control'], true);

    // 门锁当前状态：true=已开，false=已关，null=未上报
    $doorRaw = $values['door_lock'] ?? null;
    $doorOpen = $doorRaw === null ? null : ThingModel::castValue(['type' => 'bool'], $doorRaw);
    $doorAt = $reportedAt['door_lock'] ?? null;

    // 双通道状态（WIFI/4G 合并产品）
    $channels = $record->isMergedChannelProduct() ? $record->channelStatuses() : [];

    // 最近交互记录：设备日志（含下发/上报方向），数据型日志已在“设备单元”中体现，不重复展示
    $logs = $record->logs()
        ->where('type', '!=', DeviceLog::TYPE_DATA)
        ->latest('occurred_at')
        ->limit(8)
        ->get();

    $dirMeta = [
        DeviceLog::DIRECTION_OUT => [
            'cls' => 'dir-out',
            'svg' => 'M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18',
        ],
        DeviceLog::DIRECTION_IN => [
            'cls' => 'dir-in',
            'svg' => 'M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3',
        ],
        DeviceLog::DIRECTION_SYSTEM => [
            'cls' => 'dir-system',
            'svg' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
        ],
    ];

    $resultMeta = [
        DeviceLog::RESULT_SUCCESS => ['成功', 'is-success'],
        DeviceLog::RESULT_FAILED => ['失败', 'is-failed'],
        DeviceLog::RESULT_PENDING => ['进行中', 'is-pending'],
    ];
@endphp

<div class="iot-dpv">
    {{-- ============ 门禁状态摘要卡 ============ --}}
    @if ($isCtwingDoor)
        <div class="iot-card">
            <div class="iot-status">
                <div class="iot-status-main">
                    @if ($doorOpen === true)
                        <span class="iot-status-icon is-open">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        </span>
                        <div>
                            <div class="iot-status-title is-open">已开锁</div>
                            <div class="iot-status-sub">门锁处于开启状态</div>
                        </div>
                    @elseif ($doorOpen === false)
                        <span class="iot-status-icon is-closed">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        </span>
                        <div>
                            <div class="iot-status-title is-closed">已关锁</div>
                            <div class="iot-status-sub">门锁处于关闭状态</div>
                        </div>
                    @else
                        <span class="iot-status-icon is-unknown">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
                        </span>
                        <div>
                            <div class="iot-status-title is-unknown">等待上报</div>
                            <div class="iot-status-sub">暂无门锁状态数据</div>
                        </div>
                    @endif
                </div>

                <div class="iot-status-time">
                    状态更新：{{ $doorAt ? \Illuminate\Support\Carbon::parse($doorAt)->format('Y-m-d H:i') : '—' }}
                </div>

                @if (count($channels) > 0)
                    <div class="iot-channels">
                        <span class="iot-ch-label">通信通道</span>
                        @foreach ($channels as $ch)
                            <span class="iot-ch">
                                <span class="iot-ch-dot" style="color: {{ $ch['online'] ? '#16a34a' : '#dc2626' }};">●</span>
                                <span class="iot-ch-name">{{ $ch['network'] }}</span>
                                <span class="iot-ch-state {{ $ch['online'] ? 'is-on' : 'is-off' }}">{{ $ch['online'] ? '在线' : '离线' }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="iot-status-foot">
                点击上方「远程开门」下发指令（自动选择在线通道，WIFI 优先）；开关门结果经订阅推送实时更新。
            </div>
        </div>
    @endif

    {{-- ============ 设备单元 ============ --}}
    <div class="iot-card">
        <div class="iot-card-head">
            <span>设备单元</span>
            <span class="iot-sub">来源：订阅上报</span>
        </div>
        @if (count($defs) === 0)
            <div class="iot-empty">
                暂无上报数据。该设备为指令型设备，状态由订阅地址（webhook）推送后实时更新。
            </div>
        @else
            <div>
                @foreach ($defs as $code => $def)
                    @php
                        $raw = $values[$code] ?? null;
                        $type = $def['type'] ?? 'string';
                        $writable = ($def['writable'] ?? true) !== false;
                    @endphp
                    <div class="iot-unit-row">
                        <div class="iot-unit-label">
                            <span class="iot-unit-name">{{ $def['name'] }}</span>
                            <div class="iot-unit-code">
                                <span class="iot-mono">{{ $code }}</span>
                                @if (! $writable)
                                    <span class="iot-tag">只读</span>
                                @endif
                                @if (($def['source'] ?? 'product') === 'reported')
                                    <span class="iot-tag iot-tag-blue">上报</span>
                                @endif
                            </div>
                        </div>

                        <div class="iot-unit-value">
                            @if ($raw === null)
                                <span class="iot-waiting">等待上报</span>
                            @elseif ($type === 'bool')
                                @php
                                    $on = ThingModel::castValue(['type' => 'bool'], $raw);
                                    $badgeText = ($code === 'door_lock')
                                        ? ($on ? '已开锁' : '已关锁')
                                        : ($on ? '开启' : '关闭');
                                @endphp
                                <span class="iot-badge {{ $on ? 'is-on' : 'is-off' }}">{{ $badgeText }}</span>
                            @elseif ($type === 'json')
                                @php
                                    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                                    $pretty = json_encode($decoded ?? $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                @endphp
                                <details>
                                    <summary class="iot-raw-btn">
                                        查看原始数据
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                                    </summary>
                                    <pre class="iot-raw-pre">{{ $pretty }}</pre>
                                </details>
                            @elseif ($type === 'enum')
                                @php $opt = collect($def['options'] ?? [])->firstWhere('value', (string) $raw); @endphp
                                <span class="iot-chip">{{ $opt['label'] ?? (string) $raw }}</span>
                            @else
                                <span class="iot-chip">{{ $raw }}@if (! empty($def['unit'])) {{ $def['unit'] }}@endif</span>
                            @endif
                        </div>

                        <div class="iot-unit-time">
                            {{ ($reportedAt[$code] ?? null) ? \Illuminate\Support\Carbon::parse($reportedAt[$code])->format('m-d H:i') : '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============ 最近交互记录 ============ --}}
    <div class="iot-card">
        <div class="iot-card-head">最近交互记录</div>
        @if ($logs->isEmpty())
            <div class="iot-empty">暂无交互记录</div>
        @else
            <div>
                @foreach ($logs as $log)
                    @php
                        $dir = $dirMeta[$log->direction] ?? $dirMeta[DeviceLog::DIRECTION_SYSTEM];
                        [$resultLabel, $resultCls] = $resultMeta[$log->result] ?? [null, null];
                        $channel = is_array($log->content) ? ($log->content['channel'] ?? null) : null;
                    @endphp
                    <div class="iot-log-row">
                        <span class="iot-log-icon {{ $dir['cls'] }}">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $dir['svg'] }}"/></svg>
                        </span>

                        <div class="iot-log-body">
                            <div class="iot-log-title">
                                <span>{{ $log->title }}</span>
                                @if ($resultLabel)
                                    <span class="iot-result {{ $resultCls }}">{{ $resultLabel }}</span>
                                @endif
                                @if ($channel)
                                    <span class="iot-ch-badge">{{ $channel }}</span>
                                @endif
                            </div>
                            @if (is_array($log->content) && count($log->content) > 0)
                                <details class="iot-detail">
                                    <summary class="iot-detail-summary">
                                        查看明细
                                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                                    </summary>
                                    <pre class="iot-detail-pre">{{ json_encode($log->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </div>

                        <span class="iot-log-time">{{ $log->occurred_at?->format('m-d H:i:s') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
