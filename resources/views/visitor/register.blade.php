@extends('visitor.layout')
@section('title', '访客登记')

@section('content')
    <div class="header">
        <div class="brand">{{ \App\Models\SystemSetting::get('platform_name', '物联网中台') }} · 访客通行</div>
        <h1>{{ $qr->name }}</h1>
        <p>请填写登记信息，提交后{{ $qr->device ? '门禁将自动开启' : '完成登记' }}</p>
    </div>

    <div class="card">
        @if ($errors->any())
            <div class="errors">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('visitor.submit', $qr->code) }}">
            @csrf
            <label>姓名<span class="req">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="请输入真实姓名" required>

            <label>手机号<span class="req">*</span></label>
            <input type="tel" name="phone" id="visitor-phone" value="{{ old('phone') }}" placeholder="请输入手机号" required autocomplete="tel">

            @if ($qr->sms_verify)
                <label>短信验证码<span class="req">*</span></label>
                <div class="sms-row">
                    <input type="text" name="sms_code" value="{{ old('sms_code') }}"
                           placeholder="请输入 6 位验证码" required inputmode="numeric"
                           maxlength="6" autocomplete="one-time-code" class="sms-input">
                    <button type="button" class="sms-btn" id="sms-send-btn">获取验证码</button>
                </div>
                <p class="sms-msg" id="sms-msg" style="display:none;"></p>
            @endif

            @foreach ($qr->enabledFields() as $i => $field)
                <label>{{ $field['label'] }}@if ($field['required'])<span class="req">*</span>@endif</label>
                @if ($field['type'] === 'textarea')
                    <textarea name="custom[{{ $i }}]" rows="3"
                              placeholder="请输入{{ $field['label'] }}"
                              @if ($field['required']) required @endif>{{ old('custom.'.$i) }}</textarea>
                @else
                    <input type="text" name="custom[{{ $i }}]" value="{{ old('custom.'.$i) }}"
                           placeholder="请输入{{ $field['label'] }}"
                           @if ($field['required']) required @endif>
                @endif
            @endforeach

            @if ($qr->verify_mode === 'code')
                <label>通行口令<span class="req">*</span></label>
                <input type="text" name="verify_code" value="{{ old('verify_code') }}"
                       placeholder="请输入本单元通行口令（向业主/物业获取）" required autocomplete="off">
            @elseif ($qr->verify_mode === 'question')
                <label>{{ $qr->verify_question ?: '验证问题' }}<span class="req">*</span></label>
                <input type="text" name="verify_answer" value="{{ old('verify_answer') }}"
                       placeholder="{{ $qr->verify_hint ?: '请输入答案（向业主/物业获取）' }}" required autocomplete="off">
            @endif

            <button type="submit" class="btn">提交登记 @if($qr->device)· 一键开门 @endif</button>

            @if ($tip = $qr->securityTip())
                <p class="tip" style="margin-top:12px;">{{ $tip }}</p>
            @endif
        </form>
    </div>

    @if ($qr->sms_verify)
    <style>
        .sms-row { display: flex; gap: 10px; align-items: stretch; }
        .sms-row .sms-input { flex: 1; min-width: 0; }
        .sms-btn {
            flex: 0 0 auto; border: 1.5px solid #3b82f6; background: #eff6ff; color: #2563eb;
            border-radius: 10px; padding: 0 16px; font-size: 15px; font-weight: 600;
            cursor: pointer; white-space: nowrap; transition: background .15s;
        }
        .sms-btn:active { transform: scale(.97); }
        .sms-btn:disabled { border-color: #cbd5e1; background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        .sms-msg { font-size: 13px; margin-top: 8px; line-height: 1.5; }
        .sms-msg.ok { color: #16a34a; }
        .sms-msg.err { color: #dc2626; }
    </style>
    <script>
    (function () {
        var btn = document.getElementById('sms-send-btn');
        var phoneInput = document.getElementById('visitor-phone');
        var msg = document.getElementById('sms-msg');
        if (!btn || !phoneInput) return;

        var timer = null;
        function showMsg(text, ok) {
            msg.textContent = text;
            msg.style.display = text ? 'block' : 'none';
            msg.className = 'sms-msg ' + (ok ? 'ok' : 'err');
        }
        function startCountdown(seconds) {
            var left = seconds;
            btn.disabled = true;
            btn.textContent = left + ' 秒后重发';
            timer = setInterval(function () {
                left--;
                if (left <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.textContent = '重新获取';
                } else {
                    btn.textContent = left + ' 秒后重发';
                }
            }, 1000);
        }

        btn.addEventListener('click', function () {
            var phone = (phoneInput.value || '').replace(/\s+/g, '');
            if (!/^[0-9+\-]{6,20}$/.test(phone)) {
                showMsg('请先填写正确的手机号。', false);
                phoneInput.focus();
                return;
            }
            btn.disabled = true;
            btn.textContent = '发送中...';
            showMsg('', false);

            fetch(@json(route('visitor.sms', $qr->code)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phone })
            }).then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            }).then(function (r) {
                if (r.ok && r.data.ok) {
                    showMsg(r.data.message || '验证码已发送，请查收短信。', true);
                    startCountdown(60);
                } else {
                    showMsg((r.data && r.data.message) || '验证码发送失败，请稍后重试。', false);
                    btn.disabled = false;
                    btn.textContent = '获取验证码';
                }
            }).catch(function () {
                showMsg('网络异常，请稍后重试。', false);
                btn.disabled = false;
                btn.textContent = '获取验证码';
            });
        });
    })();
    </script>
    @endif
@endsection
