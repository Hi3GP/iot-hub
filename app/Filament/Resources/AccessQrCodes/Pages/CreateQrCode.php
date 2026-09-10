<?php

namespace App\Filament\Resources\AccessQrCodes\Pages;

use App\Filament\Resources\AccessQrCodes\QrCodeResource;
use App\Filament\Resources\AccessQrCodes\Schemas\QrCodeForm;
use Filament\Resources\Pages\CreateRecord;

class CreateQrCode extends CreateRecord
{
    protected static string $resource = QrCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] ??= auth()->id();

        return QrCodeForm::normalizeCommand($data);
    }
}
