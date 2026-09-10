<?php

namespace App\Filament\Resources\AccessQrCodes\Pages;

use App\Filament\Resources\AccessQrCodes\QrCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQrCodes extends ListRecords
{
    protected static string $resource = QrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新建登记码'),
        ];
    }
}
