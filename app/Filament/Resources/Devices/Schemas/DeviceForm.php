<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\IoT\Registry\ConnectorManager;
use App\Models\Connector;
use App\Models\Device;
use App\Models\Space;
use App\IoT\ThingModel;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('设备名称')
                    ->required()
                    ->maxLength(255),

                FiltersByOwner::ownerSelect(),

                TextInput::make('external_id')
                    ->label('平台设备标识')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('平台侧唯一标识（如萤石序列号）。本地设备可自定义。'),

                Select::make('connector_id')
                    ->label('所属连接器')
                    ->relationship(
                        'connector',
                        'name',
                        fn (Builder $query) => FiltersByOwner::applyOwnerFilter($query)
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),

                // 萤石云：视频加密验证码
                TextInput::make('meta.verify_code')
                    ->label(function ($record) {
                        $isChannel = ! empty($record?->meta['is_channel']) || str_contains((string) $record?->external_id, '#CH');

                        return $isChannel ? '视频验证码（单独）' : '视频验证码（主机统一）';
                    })
                    ->password()
                    ->revealable()
                    ->visible(function (Get $get, $record) {
                        $connectorId = $get('connector_id') ?? $record?->connector_id;
                        if (! $connectorId) {
                            return false;
                        }

                        return Connector::find($connectorId)?->driver === 'ezviz';
                    })
                    ->helperText(function ($record) {
                        $isChannel = ! empty($record?->meta['is_channel']) || str_contains((string) $record?->external_id, '#CH');

                        if ($isChannel) {
                            return '设置后覆盖主机验证码（仅本通道）。留空则使用主机的统一验证码。';
                        }

                        return '主机验证码，所有未单独设置的通道自动继承。填写后播放视频时自动带入。';
                    }),

                Select::make('category')
                    ->label('设备品类')
                    ->options(ThingModel::CATEGORIES)
                    ->searchable(),

                Select::make('space_id')
                    ->label('安装位置')
                    ->options(fn () => Space::query()
                        ->orderBy('type')
                        ->orderBy('sort')
                        ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
                        ->pluck('name', 'id'))
                    ->searchable(),

                TextInput::make('model')
                    ->label('型号'),

                Hidden::make('bound_type')
                    ->default('manual'),

                Toggle::make('is_active')
                    ->label('启用')
                    ->default(true),

                // 萤石云等视频设备：显示视频/通道信息
                \Filament\Schemas\Components\Section::make('视频信息')
                    ->description('萤石云设备及视频通道，可直接预览直播画面')
                    ->visible(fn ($record) => $record?->exists && $record->connector?->driver === 'ezviz')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Forms\Components\ViewField::make('ezviz_video_info')
                            ->view('filament.ezviz-video-info'),
                    ]),

                // 其他平台：物模型设备单元数据
                \Filament\Schemas\Components\Section::make('设备单元数据（最近上报）')
                    ->description('设备属性当前值，来源平台订阅上报。产品设备单元在「设备中心 → 设备单元」中定义')
                    ->visible(fn ($record) => $record?->exists && $record->connector?->driver !== 'ezviz')
                    ->columnSpanFull()
                    ->schema([
                        // 门禁类设备：单元区内提供开门按钮，直接主动下发指令（无需联动/二维码）
                        Actions::make([
                            FilamentAction::make('openDoor')
                                ->label('远程开门')
                                ->icon('heroicon-o-lock-open')
                                ->color('success')
                                ->visible(fn (Device $record): bool => $record->connector?->driver === 'ctwing'
                                    && in_array($record->category, ['door_lock', 'access_control'], true))
                                ->requiresConfirmation()
                                ->modalHeading('远程开门')
                                ->modalDescription(fn (Device $record): string => '将向设备「' . $record->name . '」下发开门指令（{"io":1,"to":N}，N 取连接器「门禁开门时长」，默认 10 秒）。确认开门？')
                                ->action(function (Device $record): void {
                                    try {
                                        app(ConnectorManager::class)
                                            ->make($record->connector)
                                            ->sendCommand($record->connector, $record, 'door_lock', 1);

                                        Notification::make()
                                            ->title('开门指令已下发')
                                            ->body("已向「{$record->name}」下发指令，设备动作结果以订阅推送为准。")
                                            ->success()
                                            ->send();
                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->title('开门失败')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])->columnSpanFull(),

                        \Filament\Forms\Components\ViewField::make('device_property_values')
                            ->view('filament.device-property-values'),
                    ]),
            ])
            ->columns(2);
    }
}
