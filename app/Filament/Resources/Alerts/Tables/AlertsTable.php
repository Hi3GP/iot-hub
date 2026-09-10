<?php

namespace App\Filament\Resources\Alerts\Tables;

use App\Models\Alert;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')
                    ->label('级别')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ['info' => '提示', 'warning' => '警告', 'critical' => '严重'][$state] ?? $state),

                TextColumn::make('title')
                    ->label('内容')
                    ->searchable()
                    ->description(fn (Alert $record): ?string => $record->content),

                TextColumn::make('device.name')
                    ->label('设备')
                    ->default('-'),

                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'danger',
                        'acknowledged' => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => ['active' => '未处理', 'acknowledged' => '已确认', 'resolved' => '已解除'][$state] ?? $state),

                TextColumn::make('triggered_at')
                    ->label('触发时间')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(['active' => '未处理', 'acknowledged' => '已确认', 'resolved' => '已解除'])
                    ->default('active'),
                SelectFilter::make('level')
                    ->label('级别')
                    ->options(['info' => '提示', 'warning' => '警告', 'critical' => '严重']),
            ])
            ->recordActions([
                Action::make('acknowledge')
                    ->label('确认')
                    ->icon('heroicon-o-check')
                    ->color('warning')
                    ->visible(fn (Alert $record) => $record->status === 'active')
                    ->action(fn (Alert $record) => $record->update(['status' => 'acknowledged', 'acknowledged_at' => now()])),

                Action::make('resolve')
                    ->label('解决')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Alert $record) => $record->status !== 'resolved')
                    ->action(fn (Alert $record) => $record->update(['status' => 'resolved', 'resolved_at' => now()])),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('triggered_at'))
            ->poll('30s');
    }
}
