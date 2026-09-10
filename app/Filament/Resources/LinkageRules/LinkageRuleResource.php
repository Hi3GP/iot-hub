<?php

namespace App\Filament\Resources\LinkageRules;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\Filament\Resources\LinkageRules\Pages\CreateLinkageRule;
use App\Filament\Resources\LinkageRules\Pages\EditLinkageRule;
use App\Filament\Resources\LinkageRules\Pages\ListLinkageRules;
use App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm;
use App\Filament\Resources\LinkageRules\Tables\LinkageRulesTable;
use App\Models\LinkageRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LinkageRuleResource extends Resource
{
    use FiltersByOwner;

    protected static ?string $model = LinkageRule::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = '自动化联动';

    protected static ?string $navigationLabel = '联动规则';

    protected static ?string $modelLabel = '联动规则';

    public static function form(Schema $schema): Schema
    {
        return LinkageRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LinkageRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinkageRules::route('/'),
            'create' => CreateLinkageRule::route('/create'),
            'edit' => EditLinkageRule::route('/{record}/edit'),
        ];
    }
}
