<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Services\Sms\PhoneVerificationService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class SystemSettingsPage extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string | \UnitEnum | null $navigationGroup = '系统管理';

    protected static ?string $navigationLabel = '系统设置';

    protected static ?string $title = '系统设置';

    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $state = [];

        foreach (array_keys(SystemSetting::DEFAULTS) as $key) {
            $value = SystemSetting::get($key);

            // FileUpload 组件状态为数组格式
            if (in_array($key, ['logo', 'favicon'])) {
                $value = filled($value) ? [$value] : [];
            }

            $state[$key] = $value;
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('安全入口')
                    ->description('后台管理地址的路径段，修改后原 /admin 将完全失效，请使用新地址访问。')
                    ->schema([
                        TextInput::make('admin_path')
                            ->label('后台入口路径')
                            ->required()
                            ->maxLength(50)
                            ->regex('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/')
                            ->helperText('只允许小写字母、数字、连字符，不能以连字符开头/结尾。如：lans-console、manage、portal。修改保存后需用新地址 {域名}/<此路径> 访问后台。')
                            ->placeholder('lans-console')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('平台信息')
                    ->description('浏览器标题、侧栏品牌区域与登录页')
                    ->schema([
                        TextInput::make('platform_name')
                            ->label('平台名称')
                            ->required()
                            ->maxLength(100)
                            ->helperText('浏览器标题及侧栏品牌文字')
                            ->columnSpan(1),

                        TextInput::make('platform_short')
                            ->label('平台英文简称')
                            ->maxLength(50)
                            ->columnSpan(1),

                        TextInput::make('login_subtitle')
                            ->label('登录页欢迎语')
                            ->maxLength(200)
                            ->placeholder('显示在登录表单上方')
                            ->columnSpan(2),

                        FileUpload::make('logo')
                            ->label('平台 Logo')
                            ->image()
                            ->disk('public')
                            ->directory('brand')
                            ->maxSize(2048)
                            ->imagePreviewHeight('120')
                            ->getUploadedFileUsing(self::uploadedFilePreview(...))
                            ->helperText('显示在侧栏顶部与登录页，建议透明底 PNG/SVG，不传则用默认')
                            ->columnSpan(1),

                        FileUpload::make('favicon')
                            ->label('浏览器图标 Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('brand')
                            ->maxSize(1024)
                            ->imagePreviewHeight('96')
                            ->getUploadedFileUsing(self::uploadedFilePreview(...))
                            ->helperText('建议 64×64 PNG / .ico')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('版权信息')
                    ->description('显示在登录页卡片下方')
                    ->schema([
                        TextInput::make('footer_title')
                            ->label('平台署名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('如：物联网中台 · IoT Hub'),

                        TextInput::make('footer_copyright')
                            ->label('版权信息')
                            ->maxLength(200)
                            ->placeholder('如：© 2026 智慧园区物联网平台'),

                        TextInput::make('footer_icp')
                            ->label('备案号 / 附加信息')
                            ->maxLength(200)
                            ->placeholder('选填，如：京ICP备xxxx号'),
                    ])
                    ->columns(2),

                Section::make('联系方式')
                    ->description('技术支持联系方式，显示在登录页底部（留空则不显示）')
                    ->schema([
                        TextInput::make('support_phone')
                            ->label('客服 / 技术支持电话')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('如：400-xxx-xxxx'),

                        TextInput::make('support_email')
                            ->label('支持邮箱')
                            ->email()
                            ->maxLength(100)
                            ->placeholder('如：support@example.com'),
                    ])
                    ->columns(2),

                Section::make('支付宝实名认证（预留）')
                    ->description('访客实名认证使用，配置后在访客登记时可调用支付宝实名认证')
                    ->schema([
                        TextInput::make('alipay_app_id')
                            ->label('App ID')
                            ->maxLength(64)
                            ->placeholder('2021xxxxxxxxx'),

                        TextInput::make('alipay_private_key')
                            ->label('应用私钥')
                            ->password()
                            ->maxLength(4000)
                            ->placeholder('应用私钥内容'),

                        TextInput::make('alipay_public_key')
                            ->label('支付宝公钥')
                            ->password()
                            ->maxLength(4000)
                            ->placeholder('支付宝公钥内容'),

                        TextInput::make('alipay_gateway')
                            ->label('网关地址')
                            ->maxLength(200)
                            ->placeholder('https://openapi.alipay.com/gateway.do'),
                    ])
                    ->columns(2),

                Section::make('微信开放平台（预留）')
                    ->description('微信认证、微信登录等功能使用')
                    ->schema([
                        TextInput::make('wechat_app_id')
                            ->label('App ID')
                            ->maxLength(64)
                            ->placeholder('wxabcdefghijklmnop'),

                        TextInput::make('wechat_app_secret')
                            ->label('App Secret')
                            ->password()
                            ->maxLength(128)
                            ->placeholder('应用密钥'),

                        TextInput::make('wechat_mch_id')
                            ->label('商户号')
                            ->maxLength(32)
                            ->placeholder('支付相关，选填'),

                        TextInput::make('wechat_api_key')
                            ->label('API Key')
                            ->password()
                            ->maxLength(128)
                            ->placeholder('支付相关，选填'),
                    ])
                    ->columns(2),

                Section::make('短信服务（阿里云短信）')
                    ->description('用于访客登记二维码的「手机号短信验证码」：访客需填写收到的短信验证码才能提交。AccessKey 建议使用仅开通短信权限的 RAM 子账号。')
                    ->schema([
                        Toggle::make('sms_enabled')
                            ->label('启用短信验证')
                            ->helperText('关闭后所有登记点的短信验证码均不生效')
                            ->columnSpanFull(),

                        TextInput::make('sms_access_key_id')
                            ->label('AccessKey ID')
                            ->maxLength(128)
                            ->placeholder('LTAI...')
                            ->columnSpan(1),

                        TextInput::make('sms_access_key_secret')
                            ->label('AccessKey Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(128)
                            ->placeholder('阿里云 AccessKey Secret')
                            ->columnSpan(1),

                        TextInput::make('sms_sign_name')
                            ->label('短信签名')
                            ->maxLength(64)
                            ->placeholder('阿里云控制台审核通过的签名，如：蓝斯科技')
                            ->helperText('短信开头【】中的名称，需与阿里云控制台一致')
                            ->columnSpan(1),

                        TextInput::make('sms_template_code')
                            ->label('短信模板 CODE')
                            ->maxLength(32)
                            ->placeholder('SMS_XXXXXXXX')
                            ->helperText('模板变量需为 ${code}')
                            ->columnSpan(1),

                        TextInput::make('sms_code_ttl')
                            ->label('验证码有效期（分钟）')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->helperText('需与短信模板文案一致，默认 5 分钟')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->key('form-actions'),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('保存设置')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    /** 已上传文件的预览信息（不配置时 FilePond 拿不到 url，会一直显示加载中） */
    protected static function uploadedFilePreview(?string $file): ?array
    {
        $file = ltrim((string) $file, '/');

        if ($file === '' || ! Storage::disk('public')->exists($file)) {
            return null;
        }

        return [
            'url' => '/storage/'.$file,
            'name' => basename($file),
            'size' => Storage::disk('public')->size($file),
            'type' => Storage::disk('public')->mimeType($file) ?: 'image/png',
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $oldPath = admin_path();

        foreach ($data as $key => $value) {
            // FileUpload 单文件状态为数组（list 或 uuid 键），统一取路径字符串
            if (is_array($value)) {
                $first = reset($value);
                $value = is_string($first) ? $first : '';
            }

            SystemSetting::set($key, $value ?? '');
        }

        // 入口路径变更：提示新地址并清缓存使立即生效
        $newPath = trim((string) ($data['admin_path'] ?? $oldPath));
        if ($newPath !== '' && $newPath !== $oldPath) {
            \Illuminate\Support\Facades\Cache::forget('system_setting:admin_path');
            $newPath = admin_path();
            Notification::make()
                ->title('后台入口已更新')
                ->body("新访问地址：".config('app.url')."/{$newPath}，3 秒后自动跳转。")
                ->success()
                ->send();
            $this->redirect('/'.$newPath);

            return;
        }

        Notification::make()
            ->title('系统设置已保存')
            ->body('刷新页面后全部生效')
            ->success()
            ->send();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testSms')
                ->label('发送测试短信')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->form([
                    TextInput::make('phone')
                        ->label('接收手机号')
                        ->tel()
                        ->required()
                        ->placeholder('请输入接收测试验证码的手机号')
                        ->helperText('将发送一条 6 位数字验证码短信（请先保存短信配置）'),
                ])
                ->modalHeading('测试短信发送')
                ->action(function (array $data): void {
                    /** @var PhoneVerificationService $sms */
                    $sms = app(PhoneVerificationService::class);

                    try {
                        $sms->sendCode($data['phone']);

                        Notification::make()
                            ->title('测试短信已发送')
                            ->body("验证码短信已发送至 {$data['phone']}，请注意查收（若未收到请检查签名/模板配置）")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('发送失败')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
