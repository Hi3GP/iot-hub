<?php

namespace App\Filament\Resources\DeviceProducts\Schemas;

use App\IoT\ThingModel;
use App\Models\DeviceProduct;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('产品信息')
                    ->description('产品来自平台连接器同步（如天翼云产品）；在此为每个产品定义自己的设备单元（物模型属性）。')
                    ->schema([
                        TextInput::make('name')
                            ->label('产品名称')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('external_key')
                            ->label('平台产品标识')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('未同步'),

                        Select::make('category')
                            ->label('设备品类')
                            ->options(ThingModel::CATEGORIES)
                            ->native(false)
                            ->searchable(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Tabs::make('物模型编辑模式')
                    ->tabs([
                        Tabs\Tab::make('可视化编辑')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Repeater::make('thing_model.properties')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('标识符')
                                            ->placeholder('如 switch / door_lock')
                                            ->required()
                                            ->maxLength(50)
                                            ->regex('/^[a-zA-Z][a-zA-Z0-9_]*$/')
                                            ->helperText('字母开头，字母/数字/下划线')
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),

                                        TextInput::make('name')
                                            ->label('名称')
                                            ->placeholder('如：开关 / 开锁')
                                            ->required()
                                            ->maxLength(50)
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),

                                        Select::make('type')
                                            ->label('数据类型')
                                            ->options(ThingModel::PROPERTY_TYPES)
                                            ->default('bool')
                                            ->live()
                                            ->native(false)
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),

                                        TextInput::make('unit')
                                            ->label('单位')
                                            ->placeholder('如 ℃ / %')
                                            ->maxLength(20)
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),

                                        // bool 类型：配置开/关对应的平台实际值（天翼云常为 1/0，也可能是 JSON 里的 "open"/"close"）
                                        TextInput::make('on_value')
                                            ->label('「开启」值')
                                            ->default('1')
                                            ->maxLength(100)
                                            ->visible(fn (Get $get): bool => ($get('type') ?? 'bool') === 'bool')
                                            ->helperText('平台实际下发的值')
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),

                                        TextInput::make('off_value')
                                            ->label('「关闭」值')
                                            ->default('0')
                                            ->maxLength(100)
                                            ->visible(fn (Get $get): bool => ($get('type') ?? 'bool') === 'bool')
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),

                                        // enum 类型：枚举选项
                                        Repeater::make('options')
                                            ->label('枚举选项')
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label('显示名')
                                                    ->required()
                                                    ->maxLength(50)
                                                    ->columnSpan(['default' => 'full', 'sm' => 6]),

                                                TextInput::make('value')
                                                    ->label('取值')
                                                    ->required()
                                                    ->maxLength(100)
                                                    ->columnSpan(['default' => 'full', 'sm' => 6]),
                                            ])
                                            ->columns(12)
                                            ->reorderable(false)
                                            ->addActionLabel('添加选项')
                                            ->defaultItems(0)
                                            ->visible(fn (Get $get): bool => $get('type') === 'enum')
                                            ->columnSpanFull(),

                                        Toggle::make('writable')
                                            ->label('可下发指令')
                                            ->default(true)
                                            ->inline(false)
                                            ->helperText('关闭后仅作状态展示，不可用于联动动作/开门指令')
                                            ->columnSpan(['default' => 'full', 'sm' => 3]),
                                    ])
                                    ->columns(12)
                                    ->addActionLabel('添加设备单元')
                                    ->reorderableWithDragAndDrop(true)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('JSON 模式')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Textarea::make('thing_model_json')
                                    ->hiddenLabel()
                                    ->rows(16)
                                    ->extraAttributes(['class' => 'font-mono text-xs', 'spellcheck' => 'false'])
                                    ->rule('json')
                                    ->helperText('完整物模型 JSON（{"properties": [...]}），保存时以此为准，会覆盖可视化编辑的内容。字段：code 标识符、name 名称、type 类型（bool/int/float/string/enum/json）、unit 单位、on_value/off_value 布尔映射、options 枚举选项、writable 可下发'),
                            ]),
                    ])
                    ->contained(false)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    /** 保存前规整 thing_model：JSON 模式优先（覆盖可视化），否则按可视化 properties 生成 */
    public static function normalizeThingModel(array $data): array
    {
        // JSON 模式：thing_model_json 有内容时以此为准
        if (filled($data['thing_model_json'] ?? null)) {
            $decoded = json_decode((string) $data['thing_model_json'], true);

            if (! is_array($decoded)) {
                throw new \InvalidArgumentException('物模型 JSON 格式不合法，请检查后重试');
            }

            $decoded['properties'] = self::sanitizeProperties($decoded['properties'] ?? []);
            $data['thing_model'] = $decoded;
            unset($data['thing_model_json']);

            return $data;
        }

        unset($data['thing_model_json']);

        $existing = $data['thing_model'] ?? [];

        if (is_string($existing)) {
            $existing = json_decode($existing, true) ?? [];
        }

        $data['thing_model'] = array_merge($existing, [
            'properties' => self::sanitizeProperties(data_get($data, 'thing_model.properties', [])),
        ]);

        return $data;
    }

    /** 过滤空行、标准化单个属性定义 */
    protected static function sanitizeProperties(array $properties): array
    {
        return collect($properties)
            ->filter(fn ($p) => filled($p['code'] ?? null) && filled($p['name'] ?? null))
            ->map(function ($p) {
                $type = in_array($p['type'] ?? 'string', array_keys(ThingModel::PROPERTY_TYPES)) ? $p['type'] : 'string';

                $item = [
                    'code' => trim((string) $p['code']),
                    'name' => trim((string) $p['name']),
                    'type' => $type,
                ];

                if (filled($p['unit'] ?? null)) {
                    $item['unit'] = trim((string) $p['unit']);
                }

                if ($type === 'bool') {
                    $item['on_value'] = (string) ($p['on_value'] ?? '1');
                    $item['off_value'] = (string) ($p['off_value'] ?? '0');
                }

                if ($type === 'enum') {
                    $item['options'] = collect($p['options'] ?? [])
                        ->filter(fn ($o) => isset($o['value']))
                        ->map(fn ($o) => ['label' => (string) ($o['label'] ?? $o['value']), 'value' => (string) $o['value']])
                        ->values()
                        ->all();
                }

                $item['writable'] = (bool) ($p['writable'] ?? true);

                return $item;
            })
            ->values()
            ->all();
    }

    /** 表单回显：thing_model JSON → 可视化 repeater + JSON 模式文本 */
    public static function toFormData(DeviceProduct $record): array
    {
        $properties = collect($record->thing_model['properties'] ?? [])
            ->map(fn ($p) => [
                'code' => $p['code'] ?? '',
                'name' => $p['name'] ?? '',
                'type' => $p['type'] ?? 'string',
                'unit' => $p['unit'] ?? null,
                'on_value' => $p['on_value'] ?? '1',
                'off_value' => $p['off_value'] ?? '0',
                'options' => collect($p['options'] ?? [])->values()->all(),
                'writable' => (bool) ($p['writable'] ?? true),
            ])
            ->all();

        return [
            'thing_model' => ['properties' => $properties],
            'thing_model_json' => json_encode($record->thing_model ?: ['properties' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }
}
