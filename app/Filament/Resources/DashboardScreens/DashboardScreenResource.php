<?php

namespace App\Filament\Resources\DashboardScreens;

use App\Filament\Resources\DashboardScreens\Pages\CreateScreen;
use App\Filament\Resources\DashboardScreens\Pages\EditScreen;
use App\Filament\Resources\DashboardScreens\Pages\ListScreens;
use App\Filament\Resources\DashboardScreens\Schemas\ScreenForm;
use App\Filament\Resources\DashboardScreens\Tables\ScreensTable;
use App\Models\DashboardScreen;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DashboardScreenResource extends Resource
{
    protected static ?string $model = DashboardScreen::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = '监控告警';

    protected static ?string $navigationLabel = '大屏配置';

    protected static ?string $modelLabel = '数据大屏';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ScreenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScreensTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScreens::route('/'),
            'create' => CreateScreen::route('/create'),
            'edit' => EditScreen::route('/{record}/edit'),
        ];
    }
}
