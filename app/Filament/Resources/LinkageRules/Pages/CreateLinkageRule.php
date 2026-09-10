<?php

namespace App\Filament\Resources\LinkageRules\Pages;

use App\Filament\Resources\LinkageRules\LinkageRuleResource;
use App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm;
use Filament\Resources\Pages\CreateRecord;

class CreateLinkageRule extends CreateRecord
{
    protected static string $resource = LinkageRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] ??= auth()->id();

        return LinkageRuleForm::toStorageData($data);
    }
}
