<?php

namespace App\Filament\Resources\LinkageRules\Schemas;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\IoT\ThingModel;
use App\Models\Device;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LinkageRuleForm
{
    /**
     * 设备下拉选项：管理员可见全部（标注归属用户），普通用户仅自己的设备。
     * 天翼云合并产品隐藏 4G 副通道，仅保留 WIFI 主设备。
     */
    public static function deviceOptions(): array
    {
        $devices = Device::query()
            ->with(['connector', 'user'])
            ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->get();

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
     * 取某设备的设备单元定义（code => def）。
     *
     * 规则：产品一旦在「设备建模 → 设备单元」里定义了单元，就以产品定义为准，
     * 不再用温度/湿度/亮度等标准单元兜底（避免给门禁/透传设备列出无关单元）；
     * 产品完全没有定义时，才回退到标准通用单元。
     *
     * @param  bool  $writableOnly  true=仅可写（下发指令用）；false=全部（触发条件可读即可）
     * @return array<string, array>
     */
    public static function unitDefsForDevice(mixed $deviceId, bool $writableOnly = false): array
    {
        $device = $deviceId ? Device::with('product')->find($deviceId) : null;
        $productDefs = ThingModel::productProperties($device?->product);

        $defs = ! empty($productDefs) ? $productDefs : ThingModel::standardProperties();

        return collect($defs)
            ->filter(fn ($d) => ! $writableOnly || ($d['writable'] ?? true))
            ->keyBy('code')
            ->all();
    }

    /** 设备单元选项（label 列表）。$writableOnly=true 时仅返回可下发单元 */
    public static function propertyOptionsForDevice(mixed $deviceId, bool $writableOnly = false): array
    {
        return collect(self::unitDefsForDevice($deviceId, $writableOnly))
            ->mapWithKeys(fn ($def, $code) => [
                $code => $def['name']
                    .($def['unit'] ?? null ? "（{$def['unit']}）" : '')
                    .(($def['source'] ?? '') === 'standard' ? ' · 标准' : '')
                    .(($def['writable'] ?? true) ? '' : ' · 只读'),
            ])
            ->all();
    }

    /** 设备默认的可下发（可写）设备单元 code，用于切换设备时自动选中 */
    public static function defaultCommandCode(mixed $deviceId): ?string
    {
        $keys = array_keys(self::unitDefsForDevice($deviceId, true));

        return $keys[0] ?? null;
    }

    /** 判断某设备某单元是否为可写（可下发）单元 */
    public static function isWritableUnit(mixed $deviceId, ?string $code): bool
    {
        if (blank($code)) {
            return false;
        }

        return array_key_exists($code, self::unitDefsForDevice($deviceId, true));
    }

    /** 取某设备某设备单元的定义（含类型/取值映射），产品定义优先、标准兜底 */
    public static function propertyDef(mixed $deviceId, mixed $code): ?array
    {
        if (blank($deviceId) || blank($code)) {
            return null;
        }

        $device = Device::with('product')->find($deviceId);

        return ThingModel::propertiesFor($device?->product)[$code] ?? null;
    }

    /** bool 型属性的开启/关闭显示文案（带上平台实际值） */
    public static function boolLabels(mixed $deviceId, mixed $code): array
    {
        $def = self::propertyDef($deviceId, $code);
        $on = $def['on_value'] ?? '1';
        $off = $def['off_value'] ?? '0';

        return ['on' => "开启（{$on}）", 'off' => "关闭（{$off}）"];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        TextInput::make('name')
                            ->label('规则名称')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('description')
                            ->label('描述')
                            ->maxLength(255),

                        FiltersByOwner::ownerSelect(),

                        TextInput::make('cooldown_seconds')
                            ->label('冷却时间（秒）')
                            ->numeric()
                            ->default(0)
                            ->helperText('两次触发之间的最小间隔，防止反复执行'),

                        Toggle::make('enabled')
                            ->label('启用规则')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('触发条件（满足任意一条即触发）')
                    ->schema([
                        Repeater::make('triggers_ui')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('trigger_type')
                                    ->label('触发方式')
                                    ->options([
                                        'property_changed' => '设备属性变化',
                                        'event' => '设备事件',
                                        'status' => '设备离线',
                                    ])
                                    ->default('property_changed')
                                    ->required()
                                    ->live()
                                    ->native(false),

                                Select::make('device_id')
                                    ->label('触发设备')
                                    ->options(fn () => self::deviceOptions())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('code', null))
                                    ->native(false)
                                    ->helperText('格式：平台 · 设备名称（归属用户）'),

                                Select::make('code')
                                    ->label('设备单元')
                                    ->options(fn (Get $get) => self::propertyOptionsForDevice($get('device_id')))
                                    ->searchable()
                                    ->live()
                                    ->required(fn (Get $get): bool => $get('trigger_type') === 'property_changed')
                                    ->visible(fn (Get $get): bool => $get('trigger_type') === 'property_changed')
                                    ->native(false)
                                    ->helperText('触发条件可监听该产品定义的任意单元（含只读上报项）'),

                                Radio::make('value_mode')
                                    ->label('状态')
                                    ->options(fn (Get $get) => self::boolLabels($get('device_id'), $get('code')))
                                    ->default('on')
                                    ->inline()
                                    ->live()
                                    ->required(fn (Get $get): bool => $get('trigger_type') === 'property_changed')
                                    ->visible(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? 'bool') === 'bool'),

                                Select::make('enum_value')
                                    ->label('状态值')
                                    ->options(fn (Get $get) => ThingModel::propertyValueOptions(self::propertyDef($get('device_id'), $get('code'))))
                                    ->native(false)
                                    ->required(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'enum')
                                    ->visible(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'enum'),

                                TextInput::make('custom_value')
                                    ->label('状态值')
                                    ->numeric(fn (Get $get): bool => in_array(self::propertyDef($get('device_id'), $get('code'))['type'] ?? '', ['int', 'float']))
                                    ->helperText('数值或文本，如温度 30、亮度 80')
                                    ->required(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && in_array(self::propertyDef($get('device_id'), $get('code'))['type'] ?? '', ['int', 'float', 'string', null]))
                                    ->visible(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && in_array(self::propertyDef($get('device_id'), $get('code'))['type'] ?? '', ['int', 'float', 'string', null])),

                                Textarea::make('json_value')
                                    ->label('状态值（JSON）')
                                    ->placeholder('{"state": 1}')
                                    ->rows(2)
                                    ->rule('json', fn (Get $get): bool => (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'json')
                                    ->required(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'json')
                                    ->visible(fn (Get $get): bool => $get('trigger_type') === 'property_changed'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'json'),

                                Select::make('event')
                                    ->label('事件类型')
                                    ->options(ThingModel::EVENTS)
                                    ->required(fn (Get $get): bool => $get('trigger_type') === 'event')
                                    ->visible(fn (Get $get): bool => $get('trigger_type') === 'event')
                                    ->native(false),
                            ])
                            ->columns(2)
                            ->addActionLabel('添加触发条件')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('执行动作（触发后依次执行）')
                    ->schema([
                        Repeater::make('actions_ui')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('action_type')
                                    ->label('动作类型')
                                    ->options([
                                        'device_command' => '控制设备',
                                        'alert' => '发送告警',
                                    ])
                                    ->default('device_command')
                                    ->required()
                                    ->live()
                                    ->native(false),

                                Select::make('device_id')
                                    ->label('目标设备')
                                    ->options(fn () => self::deviceOptions())
                                    ->searchable()
                                    ->required(fn (Get $get): bool => $get('action_type') === 'device_command')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'device_command')
                                    ->live()
                                    ->afterStateUpdated(function ($set, $state) {
                                        $set('code', self::defaultCommandCode($state));
                                    })
                                    ->native(false)
                                    ->helperText('可跨平台联动，例如萤石摄像头触发 → 打开天翼云门禁'),

                                Select::make('code')
                                    ->label('下发的设备单元')
                                    ->options(fn (Get $get) => self::propertyOptionsForDevice($get('device_id'), true))
                                    ->searchable()
                                    ->live()
                                    ->required(fn (Get $get): bool => $get('action_type') === 'device_command')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'device_command')
                                    ->native(false)
                                    ->helperText('仅列出该设备可下发（可写）的单元；只读上报项不可作为指令目标'),

                                Radio::make('value_mode')
                                    ->label('状态')
                                    ->options(fn (Get $get) => self::boolLabels($get('device_id'), $get('code')))
                                    ->default('on')
                                    ->inline()
                                    ->live()
                                    ->required(fn (Get $get): bool => $get('action_type') === 'device_command')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? 'bool') === 'bool'),

                                Select::make('enum_value')
                                    ->label('指令值')
                                    ->options(fn (Get $get) => ThingModel::propertyValueOptions(self::propertyDef($get('device_id'), $get('code'))))
                                    ->native(false)
                                    ->required(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'enum')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'enum'),

                                TextInput::make('custom_value')
                                    ->label('指令值')
                                    ->numeric(fn (Get $get): bool => in_array(self::propertyDef($get('device_id'), $get('code'))['type'] ?? '', ['int', 'float']))
                                    ->required(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && in_array(self::propertyDef($get('device_id'), $get('code'))['type'] ?? '', ['int', 'float', 'string', null]))
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && in_array(self::propertyDef($get('device_id'), $get('code'))['type'] ?? '', ['int', 'float', 'string', null])),

                                Textarea::make('json_value')
                                    ->label('指令值（JSON）')
                                    ->placeholder('{"cmd": 1}')
                                    ->rows(2)
                                    ->rule('json', fn (Get $get): bool => (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'json')
                                    ->required(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'json')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'device_command'
                                        && (self::propertyDef($get('device_id'), $get('code'))['type'] ?? '') === 'json'),

                                Select::make('alert_level')
                                    ->label('告警级别')
                                    ->options([
                                        'info' => '提示',
                                        'warning' => '警告',
                                        'critical' => '严重',
                                    ])
                                    ->default('warning')
                                    ->required(fn (Get $get): bool => $get('action_type') === 'alert')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'alert')
                                    ->native(false),

                                TextInput::make('alert_title')
                                    ->label('告警标题')
                                    ->required(fn (Get $get): bool => $get('action_type') === 'alert')
                                    ->visible(fn (Get $get): bool => $get('action_type') === 'alert'),
                            ])
                            ->columns(2)
                            ->addActionLabel('添加动作')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * 编辑回显：存储的 triggers/actions JSON → 表单 repeater 结构
     */
    public static function toFormData(array $data): array
    {
        $data['triggers_ui'] = collect($data['triggers'] ?? [])
            ->map(function (array $t) {
                $item = [
                    'trigger_type' => $t['type'] ?? 'property_changed',
                    'device_id' => $t['device_id'] ?? null,
                ];

                if (($t['type'] ?? '') === 'property_changed') {
                    $item['code'] = $t['code'] ?? null;
                    $item += self::valueToFormData($t['device_id'] ?? null, $t['code'] ?? null, $t['value'] ?? null);
                } elseif (($t['type'] ?? '') === 'event') {
                    $item['event'] = $t['event'] ?? null;
                }

                return $item;
            })
            ->all();

        $data['actions_ui'] = collect($data['actions'] ?? [])
            ->map(function (array $a) {
                if (($a['type'] ?? '') === 'alert') {
                    return [
                        'action_type' => 'alert',
                        'alert_level' => $a['level'] ?? 'warning',
                        'alert_title' => $a['title'] ?? '',
                    ];
                }

                $item = [
                    'action_type' => 'device_command',
                    'device_id' => $a['device_id'] ?? null,
                    'code' => $a['code'] ?? null,
                ];

                $item += self::valueToFormData($a['device_id'] ?? null, $a['code'] ?? null, $a['value'] ?? null);

                return $item;
            })
            ->all();

        unset($data['triggers'], $data['actions']);

        return $data;
    }

    /**
     * 保存：表单 repeater 结构 → 引擎契约的 triggers/actions JSON
     */
    public static function toStorageData(array $data): array
    {
        $data['triggers'] = collect($data['triggers_ui'] ?? [])
            ->filter(fn ($t) => ! empty($t['device_id']))
            ->map(function (array $t) {
                $trigger = [
                    'type' => $t['trigger_type'] ?? 'property_changed',
                    'device_id' => (int) $t['device_id'],
                ];

                if ($trigger['type'] === 'property_changed') {
                    $trigger['code'] = $t['code'] ?? null;
                    $trigger['operator'] = '=';
                    $trigger['value'] = self::formValueToStorage($t);
                } elseif ($trigger['type'] === 'event') {
                    $trigger['event'] = $t['event'] ?? null;
                } elseif ($trigger['type'] === 'status') {
                    $trigger['status'] = 'offline';
                }

                return $trigger;
            })
            ->values()
            ->all();

        $data['actions'] = collect($data['actions_ui'] ?? [])
            ->filter(fn ($a) => ($a['action_type'] ?? '') === 'alert' || ! empty($a['device_id']))
            ->map(function (array $a) {
                if (($a['action_type'] ?? '') === 'alert') {
                    return [
                        'type' => 'alert',
                        'level' => $a['alert_level'] ?? 'warning',
                        'title' => $a['alert_title'] ?? '联动告警',
                    ];
                }

                $deviceId = (int) ($a['device_id'] ?? 0);
                $code = $a['code'] ?? null;

                // 单元为空或不是该设备的可写单元时，回退到默认可下发单元
                if (! self::isWritableUnit($deviceId, $code)) {
                    $code = self::defaultCommandCode($deviceId);
                }

                $a['code'] = $code;

                return [
                    'type' => 'device_command',
                    'device_id' => $deviceId,
                    'code' => $code,
                    'value' => self::formValueToStorage($a),
                ];
            })
            ->values()
            ->all();

        unset($data['triggers_ui'], $data['actions_ui']);

        return $data;
    }

    /**
     * 存储值 → 表单值（按设备单元类型回显到对应控件）
     */
    public static function valueToFormData(mixed $deviceId, mixed $code, mixed $value): array
    {
        $def = self::propertyDef($deviceId, $code);
        $type = $def['type'] ?? null;

        // bool：与产品映射值（如 1/0）比对还原为 开启/关闭
        if ($type === 'bool') {
            $on = $def['on_value'] ?? '1';
            $off = $def['off_value'] ?? '0';

            if (self::looseEquals($value, $on)) {
                return ['value_mode' => 'on'];
            }

            if (self::looseEquals($value, $off)) {
                return ['value_mode' => 'off'];
            }

            return ['value_mode' => 'custom', 'custom_value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
        }

        if ($type === 'enum') {
            return ['enum_value' => $value === null ? null : (string) $value];
        }

        if ($type === 'json') {
            return ['json_value' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
        }

        return ['value_mode' => 'custom', 'custom_value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
    }

    /**
     * 表单值 → 存储值（按设备单元类型转换为引擎/平台契约）
     */
    public static function formValueToStorage(array $item): mixed
    {
        $def = self::propertyDef($item['device_id'] ?? null, $item['code'] ?? null);

        return ThingModel::resolvePropertyValue(
            $def,
            (string) ($item['value_mode'] ?? 'custom'),
            self::customInputValue($item),
        );
    }

    /** 从表单 item 提取自定义输入值（enum/json/数字/文本 各自控件） */
    public static function customInputValue(array $item): mixed
    {
        if (array_key_exists('enum_value', $item) && filled($item['enum_value'])) {
            return $item['enum_value'];
        }

        if (array_key_exists('json_value', $item) && filled($item['json_value'])) {
            return $item['json_value']; // castValue 里 json_decode
        }

        return $item['custom_value'] ?? null;
    }

    /** 宽松相等：'1' == 1 == true 一并视为相同 */
    public static function looseEquals(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (is_scalar($a) && is_scalar($b)) {
            return (string) $a === (string) $b;
        }

        return false;
    }
}
