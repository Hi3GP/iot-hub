<?php

namespace App\Filament\Resources\Connectors\Pages;

use App\Filament\Resources\Connectors\ConnectorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnector extends CreateRecord
{
    protected static string $resource = ConnectorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] ??= auth()->id();

        return $data;
    }
}
