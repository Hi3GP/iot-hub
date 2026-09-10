<?php

namespace App\Filament\Resources\LinkageRules\Pages;

use App\Filament\Resources\LinkageRules\LinkageRuleResource;
use App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\EditRecord;

class EditLinkageRule extends EditRecord
{
    protected static string $resource = LinkageRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return LinkageRuleForm::toFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LinkageRuleForm::toStorageData($data);
    }
}
