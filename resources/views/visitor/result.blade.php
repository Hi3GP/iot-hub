@extends('visitor.layout')
@section('title', '登记结果')

@section('content')
    <div class="header">
        <div class="brand">{{ \App\Models\SystemSetting::get('platform_name', '物联网中台') }} · 访客通行</div>
        <h1>{{ $qr->name }}</h1>
    </div>

    <div class="card">
        @php $ok = $registration->status === 'granted' && $registration->opened_at; @endphp

        <div class="result-icon {{ $registration->status === 'granted' ? 'ok' : 'fail' }}">
            {{ $registration->status === 'granted' ? '✓' : '✕' }}
        </div>
        <div class="result-title">
            {{ $registration->opened_at ? '登记成功，门已打开' : ($registration->status === 'granted' ? '登记成功' : '登记已提交，开门失败') }}
        </div>
        <div class="result-msg">
            @if ($registration->opened_at)
                {{ $qr->success_message ?: '请快速通行，门会在数秒后自动关闭' }}
            @elseif ($registration->status === 'granted')
                {{ $registration->result_message }}
            @else
                设备指令下发失败，请联系工作人员<br><span style="color:#94a3b8;font-size:12px;">{{ $registration->result_message }}</span>
            @endif
        </div>

        <div style="margin-top:20px;">
            <div class="info-row"><span>登记人</span><span>{{ $registration->name }}</span></div>
            <div class="info-row"><span>手机号</span><span>{{ $registration->phone }}</span></div>
            @foreach ($registration->info_map as $label => $value)
                <div class="info-row"><span>{{ $label }}</span><span>{{ $value }}</span></div>
            @endforeach
            <div class="info-row"><span>状态</span>
                <span><span class="badge {{ $registration->status === 'granted' ? 'ok' : 'fail' }}">
                    {{ $registration->status === 'granted' ? '已通行' : '开门失败' }}
                </span></span>
            </div>
            <div class="info-row"><span>时间</span><span>{{ $registration->created_at->format('Y-m-d H:i') }}</span></div>
        </div>
    </div>
@endsection
