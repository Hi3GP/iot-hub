<?php

namespace App\Filament\Resources\DeviceProducts\Pages;

use App\Filament\Resources\DeviceProducts\DeviceProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = DeviceProductResource::class;

    public function getSubheading(): string
    {
        return '定义每个产品的设备单元（物模型属性），供联动规则与登记码开门使用';
    }
}
