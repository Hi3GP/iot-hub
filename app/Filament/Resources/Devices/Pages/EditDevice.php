<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\IoT\Registry\ConnectorManager;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openDoor')
                ->label('远程开门')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn (Device $record): bool => $this->canOpenDoor($record))
                ->requiresConfirmation()
                ->modalHeading('远程开门')
                ->modalDescription(fn (Device $record): string => '将向设备「' . $record->name . '」下发开门指令（{"io":1,"to":N}，N 取连接器「门禁开门时长」，默认 10 秒）。确认开门？')
                ->action(function (Device $record): void {
                    $this->openDoor($record);
                }),

            DeleteAction::make(),
        ];
    }

    /**
     * 是否为可远程开门的门禁/门锁设备（当前支持天翼云 CTWing 门禁板）。
     */
    protected function canOpenDoor(Device $device): bool
    {
        $driver = $device->connector?->driver;
        $category = $device->category;

        return $driver === 'ctwing'
            && in_array($category, ['door_lock', 'access_control'], true);
    }

    /**
     * 下发开门指令（复用连接器 sendCommand，自动记录设备日志）。
     */
    protected function openDoor(Device $device): void
    {
        try {
            $driver = app(ConnectorManager::class)->make($device->connector);
            $driver->sendCommand($device->connector, $device, 'door_lock', 1);

            Notification::make()
                ->title('开门指令已下发')
                ->body("已向「{$device->name}」下发开门指令，设备动作结果以订阅推送为准。")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('开门失败')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
