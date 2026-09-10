<?php

namespace App\Filament\Resources\DashboardScreens\Pages;

use App\Filament\Resources\DashboardScreens\DashboardScreenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScreens extends ListRecords
{
    protected static string $resource = DashboardScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
