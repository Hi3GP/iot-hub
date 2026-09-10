<?php

namespace App\Filament\Resources\Registrations;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Tables\RegistrationsTable;
use App\Models\VisitorRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class RegistrationResource extends Resource
{
    use FiltersByOwner;

    protected static ?string $model = VisitorRegistration::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = '门禁登记';

    protected static ?string $navigationLabel = '访客登记记录';

    protected static ?string $modelLabel = '访客登记记录';

    protected static ?string $slug = 'visitor-registrations';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return RegistrationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
        ];
    }
}
