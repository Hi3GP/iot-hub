<?php

namespace App\Filament\Resources\Alerts;

use App\Filament\Resources\Alerts\Pages\ListAlerts;
use App\Filament\Resources\Alerts\Tables\AlertsTable;
use App\Filament\Resources\Concerns\FiltersByOwner;
use App\Models\Alert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AlertResource extends Resource
{
    use FiltersByOwner;

    protected static ?string $model = Alert::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = '监控告警';

    protected static ?string $navigationLabel = '告警中心';

    protected static ?string $modelLabel = '告警';

    public static function table(Table $table): Table
    {
        return AlertsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlerts::route('/'),
        ];
    }
}
