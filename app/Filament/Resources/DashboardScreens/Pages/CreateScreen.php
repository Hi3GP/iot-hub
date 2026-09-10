<?php

namespace App\Filament\Resources\DashboardScreens\Pages;

use App\Filament\Resources\DashboardScreens\DashboardScreenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScreen extends CreateRecord
{
    protected static string $resource = DashboardScreenResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
