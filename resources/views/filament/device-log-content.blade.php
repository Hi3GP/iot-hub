<div class="space-y-3">
    <div class="grid grid-cols-2 gap-2 text-sm">
        <div><span class="text-gray-500">时间：</span>{{ $log->occurred_at?->format('Y-m-d H:i:s') }}</div>
        <div><span class="text-gray-500">设备：</span>{{ $log->device?->name ?? '-' }}</div>
        <div><span class="text-gray-500">方向：</span>{{ \App\Models\DeviceLog::directionLabels()[$log->direction] ?? $log->direction }}</div>
        <div><span class="text-gray-500">类型：</span>{{ \App\Models\DeviceLog::typeLabels()[$log->type] ?? $log->type }}</div>
        <div><span class="text-gray-500">结果：</span>{{ $log->result ? (\App\Models\DeviceLog::resultLabels()[$log->result] ?? $log->result) : '-' }}</div>
        <div><span class="text-gray-500">操作人：</span>{{ match($log->operator_type) { 'user' => $log->operator?->name ?? '未知', 'system' => '系统', 'device' => '设备', default => '-' } }}</div>
        <div><span class="text-gray-500">IP：</span>{{ $log->ip ?? '-' }}</div>
    </div>
    <div>
        <div class="text-gray-500 text-sm mb-1">标题：</div>
        <div>{{ $log->title }}</div>
    </div>
    @if($log->content)
        <div>
            <div class="text-gray-500 text-sm mb-1">详细内容：</div>
            <pre class="bg-gray-100 dark:bg-gray-900 p-3 rounded text-xs overflow-auto">{{ json_encode($log->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
</div>
