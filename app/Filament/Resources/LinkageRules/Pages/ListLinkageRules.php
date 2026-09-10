<?php

namespace App\Filament\Resources\LinkageRules\Pages;

use App\Filament\Resources\LinkageRules\LinkageRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLinkageRules extends ListRecords
{
    protected static string $resource = LinkageRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
