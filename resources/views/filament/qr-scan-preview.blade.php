{{-- 登记二维码：手机扫码页实时预览（样式与 visitor/register.blade.php 保持一致，scoped 类名） --}}
@php
    /** @var \Filament\Schemas\Components\Utilities\Get $get */
    $qrName = trim((string) ($get('name') ?? ''));
    $allFields = collect($get('form_fields') ?? [])
        ->filter(fn ($f) => is_array($f) && ! empty($f['label']));
    $fields = $allFields
        ->filter(fn ($f) => (bool) ($f['enabled'] ?? true))
        ->map(fn ($f) => [
            'label' => trim((string) $f['label']),
            'type' => (($f['type'] ?? 'text') === 'textarea') ? 'textarea' : 'text',
            'required' => (bool) ($f['required'] ?? true),
        ])
        ->values()
        ->all();
    $disabled = $allFields
        ->reject(fn ($f) => (bool) ($f['enabled'] ?? true))
        ->map(fn ($f) => trim((string) $f['label']))
        ->filter()
        ->values()
        ->all();
    $hasDevice = (bool) $get('device_id');
    $verifyMode = (string) ($get('verify_mode') ?? 'none');
    $verifyQuestion = trim((string) ($get('verify_question') ?? ''));
    $verifyHint = trim((string) ($get('verify_hint') ?? ''));
    $smsVerify = (bool) ($get('sms_verify') ?? false);
    $cooldown = (int) ($get('open_cooldown_minutes') ?? 0);
    $dailyLimit = (int) ($get('daily_open_limit') ?? 0);
    $success = trim((string) ($get('success_message') ?? '')) ?: '登记成功，门已打开，请通行';
    $platform = \App\Models\SystemSetting::get('platform_name', '物联网中台');
@endphp

<div class="qrp-wrap">
    <div class="qrp-phone">
        <div class="qrp-header">
            <div class="qrp-brand">{{ $platform }} · 访客通行</div>
            <div class="qrp-title">{{ $qrName !== '' ? $qrName : '登记点名称（未填写）' }}</div>
            <div class="qrp-sub">请填写登记信息，提交后{{ $hasDevice ? '门禁将自动开启' : '完成登记' }}</div>
        </div>
        <div class="qrp-card">
            <div class="qrp-field">
                <label>姓名<span class="qrp-req">*</span></label>
                <div class="qrp-input">请输入真实姓名</div>
            </div>
            <div class="qrp-field">
                <label>手机号<span class="qrp-req">*</span></label>
                <div class="qrp-input">请输入手机号</div>
            </div>
            @if ($smsVerify)
                <div class="qrp-field">
                    <label>短信验证码<span class="qrp-req">*</span></label>
                    <div class="qrp-sms-row">
                        <div class="qrp-input qrp-locked qrp-sms-input">请输入 6 位验证码</div>
                        <div class="qrp-sms-btn">获取验证码</div>
                    </div>
                </div>
            @endif
            @foreach ($fields as $field)
                <div class="qrp-field">
                    <label>{{ $field['label'] }}@if ($field['required'])<span class="qrp-req">*</span>@endif</label>
                    @if ($field['type'] === 'textarea')
                        <div class="qrp-input qrp-textarea">请输入{{ $field['label'] }}</div>
                    @else
                        <div class="qrp-input">请输入{{ $field['label'] }}</div>
                    @endif
                </div>
            @endforeach
            @if ($verifyMode === 'code')
                <div class="qrp-field">
                    <label>通行口令<span class="qrp-req">*</span></label>
                    <div class="qrp-input qrp-locked">请输入本单元通行口令（向业主获取）</div>
                </div>
            @elseif ($verifyMode === 'question')
                <div class="qrp-field">
                    <label>{{ $verifyQuestion !== '' ? $verifyQuestion : '验证问题（未填写）' }}<span class="qrp-req">*</span></label>
                    <div class="qrp-input qrp-locked">{{ $verifyHint !== '' ? $verifyHint : '请输入答案（向业主/物业获取）' }}</div>
                </div>
            @endif
            <div class="qrp-btn">提交登记{{ $hasDevice ? ' · 一键开门' : '' }}</div>
        </div>
    </div>

    <div class="qrp-meta">
        访客扫码后将看到 <b>{{ 2 + count($fields) + ($verifyMode === 'none' ? 0 : 1) + ($smsVerify ? 1 : 0) }}</b> 个填写项（姓名、手机号固定必填）
        @if (count($disabled) > 0)
            <div class="qrp-off">已停用、不在扫码页显示：{{ implode('、', $disabled) }}</div>
        @endif
        @if ($smsVerify)
            <div class="qrp-guard">已开启短信验证：访客须填写手机收到的验证码才能提交，防止乱填手机号恶搞开门</div>
        @endif
        @if ($verifyMode !== 'none')
            <div class="qrp-guard">已开启{{ $verifyMode === 'code' ? '通行口令' : '验证问题' }}验证：{{ $verifyMode === 'code' ? '口令错误' : '答案错误' }}无法提交开门</div>
        @endif
        @if ($dailyLimit > 0 || $cooldown > 0)
            <div class="qrp-guard">防刷限制：@if ($dailyLimit > 0)同一手机号每日限 {{ $dailyLimit }} 次@endif @if ($cooldown > 0)两次开门间隔不少于 {{ $cooldown }} 分钟@endif</div>
        @endif
        <div class="qrp-success">提交成功提示：{{ $success }}</div>
    </div>
</div>

<style>
    .qrp-wrap { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 4px 0 8px; }
    .qrp-phone {
        width: 100%; max-width: 320px; border-radius: 22px; overflow: hidden;
        background: #f1f5f9; border: 1px solid #e2e8f0;
        box-shadow: 0 12px 32px -16px rgba(15, 23, 42, .25);
    }
    .qrp-header { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; padding: 18px 18px 26px; }
    .qrp-brand { font-size: 11px; opacity: .85; margin-bottom: 6px; }
    .qrp-title { font-size: 16px; font-weight: 700; line-height: 1.35; }
    .qrp-sub { font-size: 12px; opacity: .9; margin-top: 5px; }
    .qrp-card { background: #fff; border-radius: 14px; margin: -16px 10px 12px; padding: 14px 14px 16px; }
    .qrp-field label { display: block; font-size: 12px; font-weight: 600; color: #334155; margin: 10px 0 5px; }
    .qrp-field label:first-child { margin-top: 0; }
    .qrp-req { color: #ef4444; margin-left: 2px; }
    .qrp-input {
        border: 1.5px solid #e2e8f0; border-radius: 9px; padding: 9px 11px;
        font-size: 13px; color: #94a3b8; background: #f8fafc; line-height: 1.4;
    }
    .qrp-textarea { height: 52px; }
    .qrp-locked { border-color: #fde68a; background: #fffbeb; }
    .qrp-sms-row { display: flex; gap: 6px; }
    .qrp-sms-input { flex: 1; min-width: 0; }
    .qrp-sms-btn {
        flex: 0 0 auto; border: 1px solid #3b82f6; border-radius: 9px; padding: 0 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 600; color: #2563eb; background: #eff6ff; white-space: nowrap;
    }
    .qrp-btn {
        margin-top: 16px; border-radius: 10px; padding: 11px; text-align: center;
        font-size: 14px; font-weight: 700; color: #fff;
        background: linear-gradient(135deg, #2563eb, #3b82f6);
        box-shadow: 0 6px 16px -8px rgba(37, 99, 235, .6);
    }
    .qrp-meta { width: 100%; max-width: 320px; font-size: 12px; color: #64748b; line-height: 1.7; }
    .qrp-meta b { color: #0f172a; }
    .qrp-off { color: #94a3b8; }
    .qrp-guard { color: #b45309; }
    .qrp-success { color: #94a3b8; }
</style>
