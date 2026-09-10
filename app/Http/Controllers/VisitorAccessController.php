<?php

namespace App\Http\Controllers;

use App\IoT\Access\AccessGrantService;
use App\Models\AccessQrCode;
use App\Models\VisitorRegistration;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VisitorAccessController extends Controller
{
    public function show(string $code)
    {
        $qr = AccessQrCode::with('device')
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        return view('visitor.register', ['qr' => $qr]);
    }

    /**
     * 发送短信验证码（H5 页「获取验证码」按钮调用）
     */
    public function sendSms(Request $request, string $code, PhoneVerificationService $sms)
    {
        $qr = AccessQrCode::where('code', $code)->where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s]{6,20}$/'],
        ], [
            'phone.required' => '请填写手机号。',
            'phone.regex' => '手机号格式不正确，请输入 6~20 位数字。',
        ]);

        if (! $qr->sms_verify) {
            return response()->json(['ok' => false, 'message' => '该登记点未开启短信验证。'], 422);
        }

        try {
            $sms->sendCode($validated['phone'], $request->ip());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => '验证码已发送，请查收短信（'.($sms->ttlMinutes()).' 分钟内有效）。',
        ]);
    }

    public function store(Request $request, string $code, AccessGrantService $service, PhoneVerificationService $sms)
    {
        $qr = AccessQrCode::with('device.connector')
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        // 姓名、手机号始终必填；其余字段按登记点自定义配置动态校验
        $fields = $qr->enabledFields();

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s]{6,20}$/'],
        ];
        $attributes = ['name' => '姓名', 'phone' => '手机号'];
        $messages = [
            'name.required' => '请填写姓名。',
            'name.max' => '姓名过长（最多 100 个字符）。',
            'phone.required' => '请填写手机号。',
            'phone.regex' => '手机号格式不正确，请输入 6~20 位数字。',
            'phone.max' => '手机号过长。',
        ];

        foreach ($fields as $i => $field) {
            $rules["custom.{$i}"] = array_merge(
                ['nullable', 'string', 'max:500'],
                $field['required'] ? ['required'] : []
            );
            $attributes["custom.{$i}"] = $field['label'];
            if ($field['required']) {
                $messages["custom.{$i}.required"] = "请填写「{$field['label']}」。";
            }
            $messages["custom.{$i}.max"] = "「{$field['label']}」内容过长。";
        }

        // 安全验证：通行口令 / 验证问题
        if ($qr->verify_mode === 'code') {
            $rules['verify_code'] = ['required', 'string', 'max:64'];
            $attributes['verify_code'] = '通行口令';
            $messages['verify_code.required'] = '请输入通行口令。';
        } elseif ($qr->verify_mode === 'question') {
            $rules['verify_answer'] = ['required', 'string', 'max:100'];
            $attributes['verify_answer'] = $qr->verify_question ?: '验证答案';
            $messages['verify_answer.required'] = '请填写验证答案。';
        }

        // 手机号短信验证
        if ($qr->sms_verify) {
            $rules['sms_code'] = ['required', 'string', 'digits:6'];
            $attributes['sms_code'] = '短信验证码';
            $messages['sms_code.required'] = '请输入短信验证码。';
            $messages['sms_code.digits'] = '短信验证码为 6 位数字。';
        }

        $data = $request->validate($rules, $messages, $attributes);

        // 校验口令/答案（不匹配直接拒绝，不留登记记录）
        if ($qr->verify_mode === 'code' && ! AccessQrCode::answerMatches($data['verify_code'] ?? '', $qr->verify_code)) {
            throw ValidationException::withMessages([
                'verify_code' => '通行口令不正确，请向本单元业主/物业确认后再试。',
            ]);
        }
        if ($qr->verify_mode === 'question' && ! AccessQrCode::answerMatches($data['verify_answer'] ?? '', $qr->verify_answer)) {
            throw ValidationException::withMessages([
                'verify_answer' => '答案不正确，请向本单元业主/物业确认后再试。',
            ]);
        }

        // 短信验证码校验（不通过直接拒绝，不留登记记录）
        if ($qr->sms_verify && ! $sms->verify($data['phone'], $data['sms_code'] ?? null)) {
            throw ValidationException::withMessages([
                'sms_code' => '短信验证码不正确或已过期，请重新获取。',
            ]);
        }

        // 防刷限制：同一手机号（按归一化号码匹配）每日上限 + 两次开门最小间隔
        $this->assertPhoneNotRateLimited($qr, $data['phone']);

        // 组装 {字段名: 值}，空值丢弃
        $custom = [];
        $submitted = $data['custom'] ?? [];
        foreach ($fields as $i => $field) {
            $value = trim((string) ($submitted[$i] ?? ''));
            if ($value !== '') {
                $custom[$field['label']] = mb_substr($value, 0, 500);
            }
        }

        $registration = $service->register(
            $qr,
            [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'custom' => $custom,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return view('visitor.result', [
            'qr' => $qr,
            'registration' => $registration,
        ]);
    }

    /**
     * 同一手机号的开门频率限制（每日上限 + 最小间隔）
     * 号码归一化（仅保留数字）后比对，防止加空格/横线绕过。
     */
    protected function assertPhoneNotRateLimited(AccessQrCode $qr, string $phone): void
    {
        $dailyLimit = (int) ($qr->daily_open_limit ?? 0);
        $cooldown = (int) ($qr->open_cooldown_minutes ?? 0);

        if ($dailyLimit <= 0 && $cooldown <= 0) {
            return;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // 取近两天的记录在内存里归一化比对（数据量小，避免数据库方言差异）
        $recent = VisitorRegistration::where('access_qr_code_id', $qr->id)
            ->where('created_at', '>=', now()->subDays(2))
            ->orderByDesc('created_at')
            ->get(['id', 'phone', 'created_at']);

        $mine = $recent->filter(
            fn ($r) => preg_replace('/\D+/', '', (string) $r->phone) === $digits
        );

        if ($dailyLimit > 0) {
            $todayCount = $mine->filter(fn ($r) => $r->created_at->isToday())->count();
            if ($todayCount >= $dailyLimit) {
                throw ValidationException::withMessages([
                    'phone' => "该手机号今日开门次数已达上限（{$dailyLimit} 次），如需协助请联系物业。",
                ]);
            }
        }

        if ($cooldown > 0 && ($last = $mine->first())) {
            $retryAt = $last->created_at->copy()->addMinutes($cooldown);
            if ($retryAt->isFuture()) {
                $waitMinutes = (int) ceil(now()->diffInSeconds($retryAt) / 60);
                throw ValidationException::withMessages([
                    'phone' => "开门过于频繁，请 {$waitMinutes} 分钟后再试（同一手机号两次开门需间隔 {$cooldown} 分钟）。",
                ]);
            }
        }
    }
}
