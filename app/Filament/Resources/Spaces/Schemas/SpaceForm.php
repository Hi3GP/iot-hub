<?php

namespace App\Filament\Resources\Spaces\Schemas;

use App\Filament\Resources\Concerns\FiltersByOwner;
use App\Models\Space;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SpaceForm
{
    public const TYPES = [
        'park' => '园区',
        'building' => '楼栋',
        'floor' => '楼层',
        'room' => '房间',
        'zone' => '自定义区域',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('名称')
                    ->required()
                    ->maxLength(255),

                FiltersByOwner::ownerSelect(),

                Select::make('type')
                    ->label('类型')
                    ->options(self::TYPES)
                    ->default('room')
                    ->required(),

                Select::make('parent_id')
                    ->label('上级空间')
                    ->options(fn () => Space::query()
                        ->orderBy('type')
                        ->orderBy('sort')
                        ->get()
                        ->filter(fn (Space $space) => auth()->user()?->isAdmin() || $space->user_id === auth()->id() || $space->user_id === null)
                        ->mapWithKeys(fn (Space $space) => [$space->id => $space->name]))
                    ->searchable(),

                TextInput::make('sort')
                    ->label('排序')
                    ->numeric()
                    ->default(0),
            ])
            ->columns(2);
    }
}
