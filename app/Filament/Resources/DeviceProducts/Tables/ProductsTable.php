<?php

namespace App\Filament\Resources\DeviceProducts\Tables;

use App\Filament\Resources\Concerns\HasTrash;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    use HasTrash;
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('产品名称')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('connector.name')
                    ->label('所属连接器')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('external_key')
                    ->label('平台产品标识')
                    ->placeholder('—')
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('properties_count')
                    ->label('设备单元数')
                    ->state(fn ($record) => count($record->thing_model['properties'] ?? []))
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'primary' : 'gray'),

                TextColumn::make('properties_preview')
                    ->label('设备单元')
                    ->state(function ($record) {
                        $props = collect($record->thing_model['properties'] ?? []);

                        if ($props->isEmpty()) {
                            return '未定义（使用标准单元）';
                        }

                        return $props->take(4)->map(fn ($p) => $p['name'] ?? $p['code'])->implode('、')
                            . ($props->count() > 4 ? " 等 {$props->count()} 个" : '');
                    })
                    ->color('secondary')
                    ->limit(50),

                TextColumn::make('devices_count')
                    ->label('设备数')
                    ->state(fn ($record) => $record->devices->count())
                    ->toggleable(),
            ])
            ->filters([
                static::trashFilter(),
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\DeviceProducts\DeviceProductResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                ...static::trashRecordActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(static::trashBulkActions()),
            ]);
    }
}
