<?php

namespace App\Filament\Resources\DashboardScreens\Tables;

use App\Filament\Resources\Concerns\HasTrash;
use App\Filament\Resources\DashboardScreens\DashboardScreenResource;
use App\Models\DashboardScreen;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScreensTable
{
    use HasTrash;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('大屏名称')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('space.name')
                    ->label('关联园区')
                    ->badge()
                    ->color('gray')
                    ->default('全部园区'),

                TextColumn::make('layout')
                    ->label('画面布局')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => "{$state} 画面"),

                IconColumn::make('carousel_enabled')
                    ->label('轮播')
                    ->boolean(),

                TextColumn::make('carousel_interval')
                    ->label('轮播间隔')
                    ->suffix(' 秒')
                    ->toggleable(),

                TextColumn::make('refresh_interval')
                    ->label('刷新间隔')
                    ->suffix(' 秒')
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('默认')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning'),

                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->since(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('打开大屏')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (DashboardScreen $record) => '/dashboard?screen='.$record->id)
                    ->openUrlInNewTab(),

                EditAction::make(),

                ...static::trashRecordActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(static::trashBulkActions()),
            ])
            ->filters([
                static::trashFilter(),
            ])
            ->defaultSort('is_default', 'desc');
    }
}
