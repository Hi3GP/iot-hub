<?php

namespace App\Filament\Resources\Devices\Tables;

use App\IoT\Registry\ConnectorManager;
use App\IoT\Support\DeviceStateService;
use App\IoT\ThingModel;
use App\Models\Device;
use App\Filament\Resources\Concerns\HasTrash;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DevicesTable
{
    use HasTrash;
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // 天翼云合并产品（门禁1代裸板）：隐藏被 WIFI 覆盖的 4G 副通道，仅展示合并后的主设备
                $query->withoutSecondaryChannels();

                $query->withCount([
                    'alerts as active_alerts_count' => fn (Builder $query) => $query->where('status', 'active'),
                ]);
                // 萤石云设备：预加载子通道数（仅主机行展示）
                $query->selectSub(
                    Device::query()
                        ->whereColumn('external_parent_id', 'devices.external_id')
                        ->whereColumn('connector_id', 'devices.connector_id')
                        ->whereNull('deleted_at')
                        ->selectRaw('COUNT(*)'),
                    'child_channels_count'
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('设备名称')
                    ->searchable()
                    ->sortable()
                    ->description(function (Device $record): ?string {
                        // 通道设备：显示归属主机
                        $parentId = $record->external_parent_id;
                        if (! $parentId) {
                            return null;
                        }
                        $parent = Device::where('external_id', $parentId)
                            ->where('connector_id', $record->connector_id)
                            ->value('name');
                        return $parent ? '归属：' . $parent : null;
                    }),

                TextColumn::make('connector.name')
                    ->label('平台')
                    ->badge()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('归属')
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                    ->toggleable(),

                TextColumn::make('category')
                    ->label('品类')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(function (?string $state, Device $record): string {
                        if ($state) {
                            return ThingModel::categoryName($state);
                        }
                        // 萤石云通道设备自动归类
                        $isChannel = ! empty($record->meta['is_channel']) || str_contains((string) $record->external_id, '#CH');
                        if ($isChannel && $record->connector?->driver === 'ezviz') {
                            return '摄像机通道';
                        }
                        return '-';
                    })
                    ->toggleable(),

                TextColumn::make('child_channels_count')
                    ->label('通道')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? $state . ' 路' : '-')
                    ->tooltip('下挂视频通道数')
                    ->toggleable(),

                TextColumn::make('product.name')
                    ->label('产品')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('space.name')
                    ->label('位置')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('external_id')
                    ->label('设备标识')
                    ->copyable()
                    ->toggleable(),

                IconColumn::make('status')
                    ->label('状态')
                    ->sortable()
                    ->icon(fn (string $state): string => match ($state) {
                        'online' => 'heroicon-o-wifi',
                        'offline' => 'heroicon-o-signal-slash',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'online' => '在线',
                        'offline' => '离线',
                        default => '未知',
                    }),

                // 合并产品（门禁裸板）：展示 WIFI/4G 双通道各自在线状态；其他产品显示 -
                TextColumn::make('channels')
                    ->label('通信通道')
                    ->html()
                    ->placeholder('-')
                    ->getStateUsing(function (?Device $record): string {
                        if (! $record || ! $record->isMergedChannelProduct()) {
                            return '';
                        }

                        $parts = [];
                        foreach ($record->channelStatuses() as $ch) {
                            $net = $ch['network'] ?? '?';
                            $dot = $ch['online']
                                ? '<span style="color:#16a34a">●</span>'
                                : '<span style="color:#dc2626">●</span>';
                            $label = $ch['online'] ? '在线' : '离线';
                            $parts[] = '<span class="inline-flex items-center whitespace-nowrap">'.$dot.' '.$net.' '.$label.'</span>';
                        }

                        return implode('<span class="mx-1 text-gray-300">|</span>', $parts);
                    }),

                TextColumn::make('bound_type')
                    ->label('接入方式')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pull' => '平台同步',
                        'manual' => '手动绑定',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pull' => 'info',
                        'manual' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('active_alerts_count')
                    ->label('活动告警')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('status_updated_at')
                    ->label('状态时间')
                    ->since()
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('connector')
                    ->label('平台')
                    ->relationship('connector', 'name')
                    ->default(null),
                SelectFilter::make('space')
                    ->label('位置')
                    ->relationship('space', 'name')
                    ->default(null),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(['online' => '在线', 'offline' => '离线', 'unknown' => '未知'])
                    ->default(null),
                SelectFilter::make('category')
                    ->label('品类')
                    ->options(ThingModel::CATEGORIES)
                    ->default(null),
                SelectFilter::make('bound_type')
                    ->label('接入方式')
                    ->options(['pull' => '平台同步', 'manual' => '手动绑定'])
                    ->default(null),
                TernaryFilter::make('is_active')
                    ->label('启用'),
                static::trashFilter(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordActions([
                EditAction::make(),

                Action::make('refreshStatus')
                    ->label('刷新状态')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Device $record, DeviceStateService $states) {
                        try {
                            $connectorManager = app(ConnectorManager::class);
                            $connectorConnector = $connectorManager->make($record->connector);

                            $props = $connectorConnector->fetchStatus($record->connector, $record);

                            $states->reportProperties($record, array_diff_key($props, ['online' => 1]));
                            $states->reportStatus($record, $props['online'] ?? null);

                            Notification::make()->title('状态已刷新')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('刷新失败')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('openDoor')
                    ->label('开门')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (Device $record) => $record->connector?->driver === 'ctwing'
                        && in_array($record->category, ['door_lock', 'access_control'], true))
                    ->requiresConfirmation()
                    ->modalHeading('远程开门')
                    ->modalDescription(fn (Device $record) => "将向「{$record->name}」下发开门指令（{\"io\":1,\"to\":N}）。确认开门？")
                    ->action(function (Device $record) {
                        try {
                            app(ConnectorManager::class)
                                ->make($record->connector)
                                ->sendCommand($record->connector, $record, 'door_lock', 1);

                            Notification::make()->title('开门指令已下发')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('开门失败')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('video')
                    ->label('视频预览')
                    ->icon('heroicon-o-video-camera')
                    ->color('info')
                    ->visible(fn (Device $record) => $record->connector->driver === 'ezviz')
                    ->modalHeading(fn (Device $record) => "实时视频：{$record->name}")
                    ->modalContent(fn (Device $record) => self::videoView($record))
                    ->modalSubmitAction(false),

                ...static::trashRecordActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(static::trashBulkActions()),
            ]);
    }

    protected static function videoView(Device $record): \Illuminate\Contracts\View\View
    {
        return view('filament.devices.video', [
            'url' => '',
            'deviceId' => $record->id,
        ]);
    }
}
