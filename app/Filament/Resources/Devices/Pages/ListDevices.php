<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Connector;
use App\IoT\Registry\ConnectorManager;
use App\IoT\Sync\DeviceSynchronizer;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            \Filament\Actions\Action::make('syncAll')
                ->label('同步全平台设备')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $summary = [];

                    $connectors = Connector::where('is_active', true)
                        ->when(! auth()->user()?->isAdmin(), fn ($q) => $q->where('user_id', auth()->id()))
                        ->get();

                    $connectors->each(function (Connector $connector) use (&$summary) {
                        $driver = app(ConnectorManager::class)->make($connector);

                        if (! in_array('pull', $driver->capabilities(), true)) {
                            return;
                        }

                        try {
                            $stats = app(DeviceSynchronizer::class)->sync($connector, $driver);
                            $summary[] = "[{$connector->name}] +{$stats['created']} ~{$stats['updated']} -{$stats['deactivated']}";
                        } catch (\Throwable $e) {
                            $summary[] = "[{$connector->name}] 失败: {$e->getMessage()}";
                        }
                    });

                    Notification::make()
                        ->title('全平台同步完成')
                        ->body(implode("\n", $summary) ?: '没有可同步的连接器')
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
