<?php

namespace App\Filament\Resources\DeviceProducts;

use App\Filament\Resources\DeviceProducts\Pages\EditProduct;
use App\Filament\Resources\DeviceProducts\Pages\ListProducts;
use App\Filament\Resources\DeviceProducts\Schemas\ProductForm;
use App\Filament\Resources\DeviceProducts\Tables\ProductsTable;
use App\Models\DeviceProduct;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceProductResource extends Resource
{
    protected static ?string $model = DeviceProduct::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = '设备中心';

    protected static ?string $navigationLabel = '设备单元';

    protected static ?string $modelLabel = '产品设备单元';

    protected static ?string $slug = 'device-products';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    /**
     * 数据隔离：产品跟随连接器归属（device_products 无 user_id，经 connector 判断）
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['connector', 'devices']);

        $user = auth()->user();

        if (! $user?->isAdmin()) {
            $query->whereHas('connector', fn (Builder $q) => $q->where('user_id', $user?->id));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
