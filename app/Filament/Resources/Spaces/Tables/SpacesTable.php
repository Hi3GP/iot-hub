<?php

namespace App\Filament\Resources\Spaces\Tables;

use App\Filament\Resources\Concerns\HasTrash;
use App\Filament\Resources\Spaces\Schemas\SpaceForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpacesTable
{
    use HasTrash;
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'park' => 'success',
                        'building' => 'info',
                        'floor' => 'warning',
                        'room' => 'gray',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => SpaceForm::TYPES[$state] ?? $state),

                TextColumn::make('parent.name')
                    ->label('上级空间')
                    ->default('-'),

                TextColumn::make('devices_count')
                    ->counts('devices')
                    ->label('设备数'),

                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                static::trashFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                ...static::trashRecordActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(static::trashBulkActions()),
            ])
            ->defaultSort('sort');
    }
}
