<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 非管理员：强制归属自己（表单中连接器也只能选自己的）
        if (! auth()->user()?->isAdmin()) {
            $data['user_id'] = auth()->id();
        }

        $data['user_id'] ??= auth()->id();

        return $data;
    }
}
