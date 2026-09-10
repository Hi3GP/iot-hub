<?php

namespace App\Filament\Resources\AccessQrCodes\Schemas;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\IoT\ThingModel;
use App\Models\AccessQrCode;
use App\Models\Device;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class QrCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('登记点信息')
                ->schema([
                    TextInput::make('name')
                        ->label('登记点名称')
                        ->placeholder('如：3栋2单元门、东门访客登记')
                        ->required()
                        ->live()
                        ->maxLength(100),

                    FiltersByOwner::ownerSelect(),

                    Toggle::make('is_active')
                        ->label('启用该登记点（关闭后扫码页不可访问）')
                        ->default(true),
                ])
                ->columns(1)
                ->columnSpan(['default' => 'full', 'lg' => 1]),

            Section::make('安全验证 · 防恶搞')
                ->description('小区单元门推荐「通行口令」：口令只告知本单元住户、由住户转告访客，外人即使扫码也无法提交开门。')
                ->schema([
                    Radio::make('verify_mode')
                        ->label('验证方式')
                        ->options([
                            'none' => '不验证：填完登记信息即可提交',
                            'code' => '通行口令：访客必须输入正确口令才能提交',
                            'question' => '验证问题：访客必须答对问题才能提交',
                        ])
                        ->default('none')
                        ->live()
                        ->columnSpanFull()
                        ->helperText('口令/答案不区分大小写、忽略空格；验证问题可设置多个等价答案，用 | 分隔。'),

                    TextInput::make('verify_code')
                        ->label('通行口令')
                        ->placeholder('如：3栋2单元（建议用只有本单元住户知道的信息）')
                        ->maxLength(64)
                        ->live()
                        ->visible(fn (Get $get) => $get('verify_mode') === 'code')
                        ->required(fn (Get $get) => $get('verify_mode') === 'code')
                        ->columnSpanFull(),

                    TextInput::make('verify_question')
                        ->label('验证问题')
                        ->placeholder('如：本单元是几栋几单元？')
                        ->maxLength(100)
                        ->live()
                        ->visible(fn (Get $get) => $get('verify_mode') === 'question')
                        ->required(fn (Get $get) => $get('verify_mode') === 'question')
                        ->columnSpanFull(),

                    TextInput::make('verify_answer')
                        ->label('正确答案')
                        ->placeholder('如：3栋2单元；多个等价答案用 | 分隔，如：3栋2单元|三栋二单元')
                        ->maxLength(100)
                        ->live()
                        ->visible(fn (Get $get) => $get('verify_mode') === 'question')
                        ->required(fn (Get $get) => $get('verify_mode') === 'question')
                        ->columnSpanFull(),

                    TextInput::make('verify_hint')
                        ->label('访客输入提示')
                        ->placeholder('如：请向业主确认本单元栋数与单元号后填写')
                        ->maxLength(150)
                        ->live()
                        ->visible(fn (Get $get) => $get('verify_mode') === 'question')
                        ->helperText('显示在访客扫码页输入框里的灰色提示文字。因每次问题/答案不同，建议按问题填写具体的引导提示；留空则默认显示「请输入答案（向业主/物业获取）」。')
                        ->columnSpanFull(),

                    Toggle::make('sms_verify')
                        ->label('短信验证手机号（推荐）')
                        ->helperText('访客点击「获取验证码」，填写手机收到的 6 位验证码后才能提交；可有效防止乱填手机号、恶搞开门。需在「系统管理 → 系统设置」中配置并启用阿里云短信。')
                        ->columnSpanFull(),

                    TextInput::make('open_cooldown_minutes')
                        ->label('同一手机号开门间隔（分钟）')
                        ->numeric()
                        ->minValue(0)
                        ->default(5)
                        ->helperText('两次开门最小间隔，防反复刷门；0 = 不限制')
                        ->columnSpan(['default' => 'full', 'sm' => 1]),

                    TextInput::make('daily_open_limit')
                        ->label('同一手机号每日开门上限（次）')
                        ->numeric()
                        ->minValue(0)
                        ->default(10)
                        ->helperText('超出后当天无法再开门；0 = 不限制')
                        ->columnSpan(['default' => 'full', 'sm' => 1]),
                ])
                ->columns(2)
                ->columnSpan(['default' => 'full', 'lg' => 1]),

            Section::make('开门设备')
                ->description('访客提交登记后，系统自动向该设备下发指令。不绑定设备则仅做信息登记。')
                ->schema([
                    Select::make('device_id')
                        ->label('目标设备')
                        ->options(fn () => self::deviceOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($set, $state) {
                            $set('command_code', \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::defaultCommandCode($state));
                        })
                        ->native(false)
                        ->helperText('可选择任意平台的设备，如天翼云门禁；格式：平台 · 设备名')
                        ->columnSpan(['default' => 'full', 'sm' => 1]),

                    Select::make('command_code')
                        ->label('下发的设备单元')
                        ->options(fn (Get $get) => \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyOptionsForDevice($get('device_id'), true))
                        ->searchable()
                        ->live()
                        ->required()
                        ->native(false)
                        ->visible(fn (Get $get) => (bool) $get('device_id'))
                        ->helperText('仅列出该设备可下发（可写）的单元；门禁选「门锁」即下发开门指令')
                        ->columnSpan(['default' => 'full', 'sm' => 1]),

                    // bool 型：开启/关闭（显示产品配置的实际值）
                    Radio::make('command_value')
                        ->label('指令状态')
                        ->options(fn (Get $get) => \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::boolLabels($get('device_id'), $get('command_code')))
                        ->default('on')
                        ->inline()
                        ->live()
                        ->visible(fn (Get $get) => (bool) $get('device_id')
                            && ((\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? 'bool') === 'bool'))
                        ->columnSpanFull(),

                    // enum 型：从产品定义的枚举选项中选择
                    Select::make('command_enum_value')
                        ->label('指令值')
                        ->options(fn (Get $get) => \App\IoT\ThingModel::propertyValueOptions(
                            \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))
                        ))
                        ->native(false)
                        ->required(fn (Get $get) => (bool) $get('device_id')
                            && (\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '') === 'enum')
                        ->visible(fn (Get $get) => (bool) $get('device_id')
                            && (\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '') === 'enum')
                        ->columnSpanFull(),

                    // int/float/string 型
                    TextInput::make('command_custom_value')
                        ->label('指令值')
                        ->numeric(fn (Get $get): bool => in_array(\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '', ['int', 'float']))
                        ->visible(fn (Get $get) => (bool) $get('device_id')
                            && in_array(\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '', ['int', 'float', 'string', null]))
                        ->columnSpanFull(),

                    // json 型
                    Textarea::make('command_json_value')
                        ->label('指令值（JSON）')
                        ->placeholder('{"cmd": 1}')
                        ->rows(2)
                        ->rule('json', fn (Get $get): bool => (\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '') === 'json')
                        ->required(fn (Get $get) => (bool) $get('device_id')
                            && (\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '') === 'json')
                        ->visible(fn (Get $get) => (bool) $get('device_id')
                            && (\App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::propertyDef($get('device_id'), $get('command_code'))['type'] ?? '') === 'json')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpan('full'),

            Section::make('登记表单字段')
                ->description('左侧配置要收集的信息，右侧「手机扫码页预览」即访客扫码后看到的真实表单（实时同步）。姓名、手机号固定必填；关闭「扫码页显示」的字段不会出现在扫码页，配置会保留。')
                ->schema([
                    Repeater::make('form_fields')
                        ->hiddenLabel()
                        ->live()
                        ->schema([
                            TextInput::make('label')
                                ->label('字段名称')
                                ->placeholder('如：身份证号、车牌号、来访单位')
                                ->required()
                                ->live()
                                ->maxLength(50)
                                ->columnSpan(['default' => 'full', 'sm' => 5]),

                            Select::make('type')
                                ->label('字段类型')
                                ->options([
                                    'text' => '单行文本',
                                    'textarea' => '多行文本',
                                ])
                                ->default('text')
                                ->selectablePlaceholder(false)
                                ->live()
                                ->native(false)
                                ->columnSpan(['default' => 'full', 'sm' => 3]),

                            Toggle::make('enabled')
                                ->label('扫码页显示')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(['default' => 'full', 'sm' => 2]),

                            Toggle::make('required')
                                ->label('必填')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(['default' => 'full', 'sm' => 2]),
                        ])
                        ->columns([
                            'default' => 1,
                            'sm' => 12,
                        ])
                        ->default(AccessQrCode::DEFAULT_FIELDS)
                        ->addActionLabel('添加字段')
                        ->reorderable()
                        ->columnSpan(['default' => 'full', 'lg' => 7]),

                    ViewField::make('scan_preview')
                        ->label('手机扫码页预览')
                        ->view('filament.qr-scan-preview')
                        ->columnSpan(['default' => 'full', 'lg' => 5]),

                    TextInput::make('success_message')
                        ->label('成功提示语（访客提交后看到）')
                        ->default('登记成功，门已打开，请通行')
                        ->live()
                        ->maxLength(200)
                        ->columnSpanFull(),
                ])
                ->columns(12)
                ->columnSpan('full'),
        ]);
    }

    public static function deviceOptions(): array
    {
        $devices = Device::query()
            ->with(['connector', 'user'])
            ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->get();

        // 合并产品：隐藏 4G 副通道（仅保留 WIFI 主设备）
        $externalIds = $devices->pluck('external_id')->all();
        $devices = $devices->reject(function (Device $d) use ($externalIds) {
            return $d->isMergedChannelProduct()
                && $d->channelNetwork() === '4G'
                && in_array($d->channelBaseId().'-WIFI', $externalIds, true);
        });

        return $devices
            ->mapWithKeys(function (Device $d) {
                $platform = $d->connector?->name ?? '本地';
                $owner = auth()->user()?->isAdmin() && $d->user
                    ? "（{$d->user->name}）"
                    : '';

                return [$d->id => "[{$platform}] {$d->name}{$owner}"];
            })
            ->all();
    }

    /**
     * 表单多控件（bool/enum/数字/JSON）→ 存储 command_value（按产品物模型转为平台实际值）
     */
    public static function normalizeCommand(array $data): array
    {
        if (blank($data['device_id'] ?? null)) {
            return $data;
        }

        $linkageForm = \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::class;

        // 单元为空或不是该设备的可写单元时（历史数据可能存了 raw/switch 等），回退到默认可下发单元
        if (! $linkageForm::isWritableUnit($data['device_id'], $data['command_code'] ?? null)) {
            $data['command_code'] = $linkageForm::defaultCommandCode($data['device_id']);
        }

        $def = $linkageForm::propertyDef($data['device_id'], $data['command_code'] ?? null);
        $type = $def['type'] ?? 'bool';

        $data['command_value'] = match (true) {
            $type === 'bool' && in_array($data['command_value'] ?? 'on', ['on', '1', 1, true], true)
                => (string) ($def['on_value'] ?? '1'),
            $type === 'bool'
                => (string) ($def['off_value'] ?? '0'),
            $type === 'enum'
                => (string) ($data['command_enum_value'] ?? '1'),
            $type === 'json'
                => (string) ($data['command_json_value'] ?? '{}'),
            default => (string) ($data['command_custom_value'] ?? '1'),
        };

        unset($data['command_enum_value'], $data['command_custom_value'], $data['command_json_value']);

        return $data;
    }

    /**
     * 存储 command_value → 表单控件值（按产品物模型类型回显）
     */
    public static function commandToFormData(mixed $deviceId, mixed $code, mixed $value): array
    {
        $linkageForm = \App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm::class;
        $def = $linkageForm::propertyDef($deviceId, $code);
        $type = $def['type'] ?? 'bool';

        if ($type === 'enum') {
            return ['command_enum_value' => $value === null ? null : (string) $value];
        }

        if ($type === 'json') {
            $decoded = is_string($value) ? json_decode($value, true) : $value;

            return ['command_json_value' => is_string($decoded) ? $decoded : json_encode($decoded ?? $value, JSON_UNESCAPED_UNICODE)];
        }

        if ($type === 'bool') {
            $mode = $linkageForm::looseEquals($value, $def['off_value'] ?? '0') ? 'off' : 'on';

            return ['command_value' => $mode];
        }

        return ['command_custom_value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
    }
}
