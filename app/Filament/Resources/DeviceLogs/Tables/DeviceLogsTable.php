<?php

namespace App\Filament\Resources\DeviceLogs\Tables;

use App\Models\Device;
use App\Models\DeviceLog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DeviceLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('device.name')
                    ->label('设备')
                    ->default('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('direction')
                    ->label('方向')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'info',
                        'out' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => DeviceLog::directionLabels()[$state] ?? $state),

                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'command' => 'warning',
                        'data' => 'info',
                        'status' => 'success',
                        'alert' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => DeviceLog::typeLabels()[$state] ?? $state),

                TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->limit(40)
                    ->description(fn (DeviceLog $record): ?string => $record->content ? json_encode($record->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null),

                TextColumn::make('result')
                    ->label('结果')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state ? (DeviceLog::resultLabels()[$state] ?? $state) : '-'),

                TextColumn::make('operator.name')
                    ->label('操作人')
                    ->default(fn (DeviceLog $record) => $record->operator_type === 'system' ? '系统' : ($record->operator_type === 'device' ? '设备' : '-')),
            ])
            ->filters([
                SelectFilter::make('device_id')
                    ->label('设备')
                    ->options(Device::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('direction')
                    ->label('方向')
                    ->options(DeviceLog::directionLabels()),

                SelectFilter::make('type')
                    ->label('类型')
                    ->options(DeviceLog::typeLabels()),

                SelectFilter::make('result')
                    ->label('结果')
                    ->options(DeviceLog::resultLabels()),

                Filter::make('occurred_at')
                    ->label('时间区间')
                    ->form([
                        DatePicker::make('from')->label('开始日期')->native(false),
                        DatePicker::make('until')->label('结束日期')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('occurred_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('occurred_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('viewContent')
                    ->label('查看详情')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn (DeviceLog $record) => view('filament.device-log-content', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭'),
            ])
            ->bulkActions([
                BulkAction::make('export')
                    ->label('导出选中')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($records) => self::exportCsv($records->pluck('id')->all())),
            ])
            ->headerActions([
                Action::make('exportAll')
                    ->label('导出当前筛选结果')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Table $table) => self::exportCsv($table->getFilteredQuery()->pluck('id')->all())),

                Action::make('cleanup')
                    ->label('清理日志')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->form([
                        Select::make('preset')
                            ->label('快捷清理')
                            ->options([
                                'last_year' => '删除去年及更早的数据',
                                '6_months' => '删除 6 个月前的数据',
                                '3_months' => '删除 3 个月前的数据',
                                'custom' => '自定义时间区间',
                            ])
                            ->default('last_year')
                            ->reactive()
                            ->required(),

                        DatePicker::make('from')
                            ->label('开始日期')
                            ->native(false)
                            ->visible(fn ($get) => $get('preset') === 'custom')
                            ->required(fn ($get) => $get('preset') === 'custom'),

                        DatePicker::make('until')
                            ->label('结束日期')
                            ->native(false)
                            ->visible(fn ($get) => $get('preset') === 'custom')
                            ->required(fn ($get) => $get('preset') === 'custom'),
                    ])
                    ->action(function (array $data) {
                        $until = match ($data['preset']) {
                            'last_year' => Carbon::now()->startOfYear(),
                            '6_months' => Carbon::now()->subMonths(6),
                            '3_months' => Carbon::now()->subMonths(3),
                            'custom' => $data['until'] ? Carbon::parse($data['until'])->endOfDay() : null,
                        };

                        $from = $data['preset'] === 'custom' && $data['from']
                            ? Carbon::parse($data['from'])->startOfDay()
                            : null;

                        if (! $until) {
                            return;
                        }

                        $query = DeviceLog::query()->where('occurred_at', '<', $until);
                        if ($from) {
                            $query->where('occurred_at', '>=', $from);
                        }

                        $count = $query->count();
                        $query->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('日志清理完成')
                            ->body("已删除 {$count} 条日志记录")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalDescription('此操作不可恢复，请谨慎操作。'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('occurred_at'))
            ->defaultSort('occurred_at', 'desc')
            ->paginated([50, 100, 200, 500]);
    }

    /**
     * 导出日志为 CSV
     */
    public static function exportCsv(array $ids): void
    {
        $logs = DeviceLog::query()
            ->whereIn('id', $ids)
            ->orderBy('occurred_at')
            ->with(['device', 'operator'])
            ->get();

        $filename = 'device_logs_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $fh = fopen('php://output', 'w');
            // BOM 防止 Excel 中文乱码
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['时间', '设备', '方向', '类型', '标题', '内容', '结果', '操作人', 'IP']);

            foreach ($logs as $log) {
                fputcsv($fh, [
                    $log->occurred_at?->format('Y-m-d H:i:s'),
                    $log->device?->name ?? '-',
                    DeviceLog::directionLabels()[$log->direction] ?? $log->direction,
                    DeviceLog::typeLabels()[$log->type] ?? $log->type,
                    $log->title,
                    $log->content ? json_encode($log->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                    $log->result ? (DeviceLog::resultLabels()[$log->result] ?? $log->result) : '',
                    match ($log->operator_type) {
                        'user' => $log->operator?->name ?? '未知用户',
                        'system' => '系统',
                        'device' => '设备',
                        default => '-',
                    },
                    $log->ip ?? '',
                ]);
            }
            fclose($fh);
        };

        response()->stream($callback, 200, $headers)->send();
        exit;
    }
}
