<?php

namespace App\Filament\Resources\LinkageRules\Tables;

use App\Filament\Resources\Concerns\HasTrash;
use App\IoT\ThingModel;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinkageRulesTable
{
    use HasTrash;
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('规则名称')
                    ->searchable(),

                TextColumn::make('summary')
                    ->label('联动逻辑')
                    ->state(function ($record) {
                        return self::summarize($record);
                    })
                    ->wrap()
                    ->color('gray'),

                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),

                TextColumn::make('last_triggered_at')
                    ->label('上次触发')
                    ->since()
                    ->placeholder('从未'),

                TextColumn::make('description')
                    ->label('描述')
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('logs_count')
                    ->counts('logs')
                    ->label('执行次数')
                    ->toggleable(),
            ])
            ->filters([
                static::trashFilter(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('toggle')
                    ->label(fn ($record) => $record->enabled ? '停用' : '启用')
                    ->icon(fn ($record) => $record->enabled ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(fn ($record) => $record->update(['enabled' => ! $record->enabled])),

                ...static::trashRecordActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(static::trashBulkActions()),
            ]);
    }

    /**
     * 把规则的触发器/动作 JSON 汇总成「当…则…」的自然语言
     */
    protected static function summarize($record): string
    {
        $deviceName = function (?int $id) {
            static $cache = [];
            if (! $id) {
                return '未知设备';
            }

            return $cache[$id] ??= Device::with('connector')->find($id)?->name ?? "设备#{$id}";
        };

        $stateText = function ($value) {
            if ($value === true) {
                return '开启';
            }
            if ($value === false) {
                return '关闭';
            }

            return (string) $value;
        };

        $when = collect($record->triggers ?? [])->map(function (array $t) use ($deviceName, $stateText) {
            $device = $deviceName($t['device_id'] ?? null);

            return match ($t['type'] ?? 'property_changed') {
                'event' => "{$device} 发生「".(ThingModel::EVENTS[$t['event'] ?? ''] ?? '事件')."」",
                'status' => "{$device} 离线",
                default => "{$device} 的「".ThingModel::propertyName($t['code'] ?? '')."」变为「{$stateText($t['value'] ?? null)}」",
            };
        })->implode('；');

        $then = collect($record->actions ?? [])->map(function (array $a) use ($deviceName, $stateText) {
            if (($a['type'] ?? '') === 'alert') {
                return "发送告警「{$a['title']}」";
            }

            $device = $deviceName($a['device_id'] ?? null);

            return "将 {$device} 的「".ThingModel::propertyName($a['code'] ?? '')."」设为「{$stateText($a['value'] ?? null)}」";
        })->implode('；');

        $when = $when ?: '（未配置触发条件）';
        $then = $then ?: '（未配置动作）';

        return "当 {$when}，则 {$then}";
    }
}
