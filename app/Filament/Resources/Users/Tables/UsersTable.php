<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('姓名')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('登录账号')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label('角色')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'admin' ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'admin' ? '管理员' : '普通用户'),

                TextColumn::make('devices_count')
                    ->counts('devices')
                    ->label('设备数')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
            ])
            ->toolbarActions([]);
    }
}
