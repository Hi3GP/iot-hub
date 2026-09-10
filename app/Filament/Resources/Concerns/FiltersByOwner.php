<?php

namespace App\Filament\Resources\Concerns;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * 数据隔离：普通用户只能看到自己（user_id）的数据，管理员可见全部。
 * 使用方为 Filament Resource，模型需具备 user_id 字段。
 */
trait FiltersByOwner
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user?->isAdmin()) {
            $query->where('user_id', $user?->id);
        }

        return $query;
    }

    /**
     * 表单中的「归属用户」字段：仅管理员可见，普通用户由后端自动归属自己
     */
    public static function ownerSelect(): Select
    {
        return Select::make('user_id')
            ->label('归属用户')
            ->relationship('user', 'name')
            ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
            ->default(auth()->id())
            ->searchable()
            ->preload()
            ->native(false);
    }

    /**
     * 供下拉选项使用的归属过滤
     */
    public static function applyOwnerFilter(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user?->isAdmin()) {
            $query->where('user_id', $user?->id);
        }

        return $query;
    }
}
