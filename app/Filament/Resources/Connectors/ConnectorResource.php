<?php

namespace App\Filament\Resources\Connectors;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\Filament\Resources\Connectors\Pages\CreateConnector;
use App\Filament\Resources\Connectors\Pages\EditConnector;
use App\Filament\Resources\Connectors\Pages\ListConnectors;
use App\Filament\Resources\Connectors\Schemas\ConnectorForm;
use App\Filament\Resources\Connectors\Tables\ConnectorsTable;
use App\Models\Connector;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ConnectorResource extends Resource
{
    use FiltersByOwner;

    protected static ?string $model = Connector::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = '设备接入';

    protected static ?string $navigationLabel = '平台连接器';

    protected static ?string $modelLabel = '平台连接器';

    public static function form(Schema $schema): Schema
    {
        return ConnectorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectors::route('/'),
            'create' => CreateConnector::route('/create'),
            'edit' => EditConnector::route('/{record}/edit'),
        ];
    }
}
