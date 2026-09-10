<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * 回收站功能 Trait
 *
 * 为资源表格提供：
 * - 软删除按钮（非红色，灰色文字按钮）
 * - 回收站筛选（显示已删除/未删除）
 * - 恢复按钮
 * - 永久删除按钮
 *
 * 使用方法：
 *   1. 模型 use SoftDeletes
 *   2. Table configure 中调用 static::trashActions() 获取 record/bulk/header actions
 *   3. filters 中调用 static::trashFilter()
 */
trait HasTrash
{
    /**
     * 回收站筛选：仅显示已删除 / 仅显示未删除 / 全部
     */
    public static function trashFilter(): TernaryFilter
    {
        return TernaryFilter::make('trashed')
            ->label('回收站')
            ->placeholder('未删除')
            ->trueLabel('已删除')
            ->falseLabel('全部')
            ->queries(
                true: fn (Builder $q) => $q->onlyTrashed(),
                false: fn (Builder $q) => $q->withTrashed(),
                blank: fn (Builder $q) => $q->withoutTrashed(),
            );
    }

    /**
     * 行操作：软删除 + 恢复 + 永久删除
     */
    public static function trashRecordActions(): array
    {
        return [
            Action::make('restore')
                ->label('恢复')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn ($record) => $record->trashed())
                ->action(fn ($record) => $record->restore()),

            Action::make('delete')
                ->label('删除')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->visible(fn ($record) => ! $record->trashed())
                ->requiresConfirmation()
                ->modalHeading('移入回收站')
                ->modalDescription('确认删除？删除后可在回收站中恢复。')
                ->modalSubmitActionLabel('确认删除')
                ->action(fn ($record) => $record->delete()),

            Action::make('forceDelete')
                ->label('彻底删除')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn ($record) => $record->trashed())
                ->requiresConfirmation()
                ->modalHeading('彻底删除')
                ->modalDescription('此操作不可恢复，确认彻底删除该记录？')
                ->action(fn ($record) => $record->forceDelete()),
        ];
    }

    /**
     * 批量操作：批量软删除 + 批量恢复 + 批量永久删除
     */
    public static function trashBulkActions(): array
    {
        return [
            BulkAction::make('bulkRestore')
                ->label('批量恢复')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(fn ($records) => $records->each->restore()),

            BulkAction::make('bulkDelete')
                ->label('批量删除')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn ($records) => $records->each->delete()),

            BulkAction::make('bulkForceDelete')
                ->label('彻底删除选中')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn ($records) => $records->each->forceDelete()),
        ];
    }
}
