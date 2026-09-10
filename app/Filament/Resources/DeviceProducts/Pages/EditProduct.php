<?php

namespace App\Filament\Resources\DeviceProducts\Pages;

use App\Filament\Resources\DeviceProducts\DeviceProductResource;
use App\Filament\Resources\DeviceProducts\Schemas\ProductForm;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = DeviceProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, ProductForm::toFormData($this->getRecord()));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            return ProductForm::normalizeThingModel($data);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('JSON 模式保存失败')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('设备单元已保存')
            ->body('联动规则与登记码开门的设备单元下拉已同步更新')
            ->success()
            ->send();
    }
}
