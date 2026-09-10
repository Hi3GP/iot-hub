<?php

namespace App\Filament\Resources\Connectors\Tables;

use App\IoT\Registry\ConnectorManager;
use App\IoT\Sync\DeviceSynchronizer;
use App\Models\Connector;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(),

                TextColumn::make('driver')
                    ->label('平台')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ezviz' => 'info',
                        'ctwing' => 'warning',
                        'lifesmart' => 'success',
                        'mihome' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => self::driverLabel($state)),

                TextColumn::make('user.name')
                    ->label('归属')
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                    ->toggleable(),

                TextColumn::make('devices_count')
                    ->counts('devices')
                    ->label('设备数'),

                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),

                TextColumn::make('last_synced_at')
                    ->label('上次同步')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('test')
                    ->label('测试连接')
                    ->icon('heroicon-o-signal')
                    ->action(function (Connector $record) {
                        try {
                            app(ConnectorManager::class)->make($record)->testConnection($record);

                            Notification::make()->title('连接成功')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('连接失败')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('sync')
                    ->label('同步设备')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Connector $record) => in_array('pull', app(ConnectorManager::class)->make($record)->capabilities(), true))
                    ->action(function (Connector $record) {
                        try {
                            $stats = app(DeviceSynchronizer::class)->sync($record);

                            Notification::make()
                                ->title('同步完成')
                                ->body("新增 {$stats['created']}，更新 {$stats['updated']}，停用 {$stats['deactivated']}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('同步失败')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                //
            ]);
    }

    protected static function driverLabel(string $driver): string
    {
        $class = app(ConnectorManager::class)->drivers()[$driver] ?? null;

        return $class ? (new $class)->label() : $driver;
    }
}
