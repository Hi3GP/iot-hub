<?php

namespace App\Filament\Resources\DashboardScreens\Schemas;

use App\Filament\Resources\LinkageRules\Schemas\LinkageRuleForm;
use App\Models\Device;
use App\Models\Space;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ScreenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        TextInput::make('name')
                            ->label('大屏名称')
                            ->placeholder('如：园区 A 监控大屏')
                            ->required()
                            ->maxLength(100),

                        Select::make('space_id')
                            ->label('关联园区')
                            ->options(Space::where('type', 'park')->orderBy('sort')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('全部园区（不筛选）'),

                        Toggle::make('is_default')
                            ->label('设为默认大屏')
                            ->helperText('访问 /dashboard 时默认展示该配置'),
                    ])
                    ->columns(2),

                Section::make('监控画面')
                    ->description('萤石云摄像头轮播设置')
                    ->schema([
                        Select::make('layout')
                            ->label('画面布局')
                            ->options([
                                4 => '4 画面',
                                8 => '8 画面',
                                16 => '16 画面',
                            ])
                            ->default(4)
                            ->required(),

                        Select::make('carousel_interval')
                            ->label('轮播间隔')
                            ->options([
                                5 => '5 秒',
                                10 => '10 秒',
                                15 => '15 秒',
                                30 => '30 秒',
                                60 => '60 秒',
                            ])
                            ->default(10)
                            ->visible(fn (Get $get) => (bool) $get('carousel_enabled')),

                        Toggle::make('carousel_enabled')
                            ->label('开启轮播')
                            ->default(true),

                        Select::make('camera_device_ids')
                            ->label('指定摄像头')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => Device::viewableCameras()
                                ->withTrashed()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Device $d) => [
                                    $d->id => ($d->meta['is_channel'] ?? false
                                        ? '📹 '
                                        : '🎥 ')."{$d->name}（{$d->external_id}）",
                                ]))
                            ->helperText('不选则自动显示该园区全部在线摄像头。NVR 视频通道可单独选择')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('自定义数据卡片')
                    ->description('拉取平台上任意设备的属性数据或在线状态，展示在大屏上')
                    ->schema([
                        Repeater::make('widgets')
                            ->label('数据卡片')
                            ->schema([
                                TextInput::make('title')
                                    ->label('卡片标题')
                                    ->placeholder('如：大门温度、车库门禁状态')
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('type')
                                    ->label('数据类型')
                                    ->options([
                                        'property' => '设备属性（物模型数据）',
                                        'status' => '在线状态',
                                    ])
                                    ->default('property')
                                    ->required()
                                    ->reactive()
                                    ->live(),

                                Select::make('device_id')
                                    ->label('设备')
                                    ->options(fn () => Device::withTrashed()
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (Device $d) => [$d->id => "{$d->name}（{$d->external_id}）"]))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->live(),

                                Select::make('code')
                                    ->label('设备单元（属性）')
                                    ->options(fn (Get $get) => LinkageRuleForm::propertyOptionsForDevice($get('device_id')))
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('type') === 'property')
                                    ->required(fn (Get $get) => $get('type') === 'property'),
                            ])
                            ->columns(3)
                            ->addActionLabel('添加数据卡片')
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                    ])
                    ->collapsed(),

                Section::make('面板与刷新')
                    ->schema([
                        TextInput::make('refresh_interval')
                            ->label('数据刷新间隔（秒）')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(300)
                            ->default(30)
                            ->required(),

                        Toggle::make('show_stats')->label('显示统计卡片')->default(true),
                        Toggle::make('show_categories')->label('显示设备分类')->default(true),
                        Toggle::make('show_cameras')->label('显示监控画面')->default(true),
                        Toggle::make('show_alerts')->label('显示告警列表')->default(true),
                        Toggle::make('show_widgets')->label('显示自定义数据卡片')->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
