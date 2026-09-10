<?php

namespace App\Filament\Resources\DeviceLogs\Pages;

use App\Filament\Resources\DeviceLogs\DeviceLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceLogs extends ListRecords
{
    protected static string $resource = DeviceLogResource::class;
}
