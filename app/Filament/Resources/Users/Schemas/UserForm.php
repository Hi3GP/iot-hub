<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('姓名')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('邮箱（登录账号）')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Select::make('role')
                    ->label('角色')
                    ->options([
                        'admin' => '管理员（可见全部用户的数据）',
                        'user' => '普通用户（仅自己的连接器与设备）',
                    ])
                    ->default('user')
                    ->required()
                    ->native(false),

                TextInput::make('password')
                    ->label('密码')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->minLength(6)
                    ->maxLength(255)
                    ->hintIcon('heroicon-o-question-mark-circle', tooltip: '编辑时留空表示不修改密码'),
            ])
            ->columns(2);
    }
}
