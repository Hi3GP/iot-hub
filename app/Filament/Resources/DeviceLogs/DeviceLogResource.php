<?php

namespace App\Filament\Resources\DeviceLogs;

use App\Filament\Resources\DeviceLogs\Pages\ListDeviceLogs;
use App\Filament\Resources\DeviceLogs\Tables\DeviceLogsTable;
use App\Models\DeviceLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeviceLogResource extends Resource
{
    protected static ?string $model = DeviceLog::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = '设备中心';

    protected static ?string $navigationLabel = '设备日志';

    protected static ?string $modelLabel = '设备日志';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return DeviceLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeviceLogs::route('/'),
        ];
    }
}
