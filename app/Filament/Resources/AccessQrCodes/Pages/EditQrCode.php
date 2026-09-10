<?php

namespace App\Filament\Resources\AccessQrCodes\Pages;

use App\Filament\Resources\AccessQrCodes\QrCodeResource;
use App\Filament\Resources\AccessQrCodes\Schemas\QrCodeForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQrCode extends EditRecord
{
    protected static string $resource = QrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, QrCodeForm::commandToFormData(
            $data['device_id'] ?? null,
            $data['command_code'] ?? 'switch',
            $data['command_value'] ?? '1',
        ));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return QrCodeForm::normalizeCommand($data);
    }
}
