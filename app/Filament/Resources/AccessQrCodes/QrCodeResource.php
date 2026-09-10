<?php

namespace App\Filament\Resources\AccessQrCodes;

use App\Filament\Resources\AccessQrCodes\Pages\CreateQrCode;
use App\Filament\Resources\AccessQrCodes\Pages\EditQrCode;
use App\Filament\Resources\AccessQrCodes\Pages\ListQrCodes;
use App\Filament\Resources\AccessQrCodes\Schemas\QrCodeForm;
use App\Filament\Resources\AccessQrCodes\Tables\QrCodesTable;
use App\Filament\Resources\Concerns\FiltersByOwner;
use App\Models\AccessQrCode;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class QrCodeResource extends Resource
{
    use FiltersByOwner;

    protected static ?string $model = AccessQrCode::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';

    protected static string | \UnitEnum | null $navigationGroup = '门禁登记';

    protected static ?string $navigationLabel = '登记二维码';

    protected static ?string $modelLabel = '登记二维码';

    protected static ?string $slug = 'access-qr-codes';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return QrCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QrCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQrCodes::route('/'),
            'create' => CreateQrCode::route('/create'),
            'edit' => EditQrCode::route('/{record}/edit'),
        ];
    }
}
