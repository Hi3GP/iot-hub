<?php

namespace App\Filament\Resources\Connectors\Schemas;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\IoT\Registry\ConnectorManager;
use App\IoT\ThingModel;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('名称')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FiltersByOwner::ownerSelect(),

                Select::make('driver')
                    ->label('平台驱动')
                    ->options(self::driverOptions())
                    ->required()
                    ->live()
                    ->disabled(fn ($operation) => $operation === 'edit')
                    ->dehydrated()
                    ->helperText('创建后不可更换。未列出的平台可通过实现 DeviceConnector 接口扩展。'),

                Repeater::make('settings.products')
                    ->label('CTWing 产品列表')
                    ->visible(fn (Get $get) => $get('driver') === 'ctwing')
                    ->schema([
                        TextInput::make('product_id')
                            ->label('产品ID')
                            ->required()
                            ->helperText('CTWing 控制台 → 产品中心 → 点进产品可看到产品ID'),

                        TextInput::make('master_key')
                            ->label('MasterKey')
                            ->password()
                            ->revealable()
                            ->helperText('点产品卡片右上角「钥匙」图标复制；每个产品独立'),

                        TextInput::make('name')
                            ->label('产品名称（备注）')
                            ->helperText('如：门禁1代'),

                        Select::make('category')
                            ->label('设备品类')
                            ->options(ThingModel::CATEGORIES)
                            ->placeholder('按名称自动识别')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->addActionLabel('添加产品')
                    ->minItems(fn (Get $get) => $get('driver') === 'ctwing' ? 1 : 0)
                    ->defaultItems(1)
                    ->columnSpanFull()
                    ->helperText('一个天翼云账号下的多个产品都加在这里（如：433联动智慧家、门禁1代、示例产品），同步时会逐个拉取设备。'),

                TextInput::make('settings.base_url')
                    ->label('API 网关（CTWing，一般留空）')
                    ->placeholder('https://ag-api.ctwing.cn')
                    ->visible(fn (Get $get) => $get('driver') === 'ctwing')
                    ->helperText('⚠️ 不要填控制台网址或 2000305593 这类数字（那是MasterKey不是域名）。留空即使用官方网关 ag-api.ctwing.cn。'),

                TextInput::make('settings.command_api_version')
                    ->label('命令下发 API 版本号（CTWing）')
                    ->default('20190712225145')
                    ->visible(fn (Get $get) => $get('driver') === 'ctwing')
                    ->helperText('MQTT协议 CreateCommand 接口版本号，默认 20190712225145。报 Api version not found 时需更新此值。'),

                TextInput::make('settings.door_unlock_seconds')
                    ->label('门禁开门时长（秒）')
                    ->default(10)
                    ->numeric()
                    ->visible(fn (Get $get) => $get('driver') === 'ctwing')
                    ->helperText('下发 {"io":1,"to":N} 开门指令时的 N 值，默认 10 秒。'),

                Group::make()
                    ->schema(fn (Get $get) => self::credentialComponents($get('driver')))
                    ->columns(2)
                    ->columnSpanFull(),

                Textarea::make('settings.access_token')
                    ->label('Access Token（Aqara）')
                    ->visible(fn (Get $get) => $get('driver') === 'aqara')
                    ->rows(2)
                    ->extraAttributes(['class' => 'font-mono text-xs'])
                    ->helperText('通过 Aqara 账号授权获取。先调用 getAuthCode 收到验证码，再用 getToken 换取。留空则无法拉取/控制设备。')
                    ->columnSpanFull(),

                Textarea::make('settings.refresh_token')
                    ->label('Refresh Token（Aqara）')
                    ->visible(fn (Get $get) => $get('driver') === 'aqara')
                    ->rows(2)
                    ->extraAttributes(['class' => 'font-mono text-xs'])
                    ->helperText('用于刷新 access_token，有效期为 access_token 到期 +30 天。')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('启用')
                    ->default(true),
            ])
            ->columns(2);
    }

    protected static function driverOptions(): array
    {
        $options = [];

        foreach (app(ConnectorManager::class)->drivers() as $driver => $class) {
            $options[$driver] = (new $class)->label();
        }

        return $options;
    }

    /**
     * 按驱动动态渲染凭证字段
     */
    protected static function credentialComponents(?string $driver): array
    {
        if (! $driver || ! app(ConnectorManager::class)->has($driver)) {
            return [
                Group::make()->schema([]),
            ];
        }

        $fields = (new (app(ConnectorManager::class)->drivers()[$driver]))->credentialFields();

        if ($fields === []) {
            return [
                Group::make()
                    ->schema([])
                    ->columnSpanFull(),
            ];
        }

        $components = [];

        foreach ($fields as $name => $meta) {
            $component = TextInput::make("credentials.{$name}")
                ->label($meta['label'] ?? $name)
                ->required((bool) ($meta['required'] ?? false))
                ->columnSpanFull();

            if ($meta['secret'] ?? false) {
                $component->password()->revealable();
            }

            $components[] = $component;
        }

        return $components;
    }
}
