<?php

namespace App\Filament\Resources\DashboardScreens\Pages;

use App\Filament\Resources\DashboardScreens\DashboardScreenResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\EditRecord;

class EditScreen extends EditRecord
{
    protected static string $resource = DashboardScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            EditAction::make(),
        ];
    }
}
