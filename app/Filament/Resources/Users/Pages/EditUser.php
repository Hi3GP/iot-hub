<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord() instanceof User && $this->getRecord()->id !== auth()->id()),
        ];
    }

    /**
     * 防止管理员把自己的角色改成普通用户后失去管理能力（最后一个管理员）
     */
    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if ($record->id === auth()->id() && ($this->data['role'] ?? null) === 'user') {
            Notification::make()
                ->title('不能将自己降级为普通用户')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
