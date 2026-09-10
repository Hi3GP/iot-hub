<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Filament\Resources\Concerns\HasTrash;
use App\Models\AccessQrCode;
use App\Models\VisitorRegistration;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RegistrationsTable
{
    use HasTrash;
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('登记时间')
                    ->dateTime('m-d H:i')
                    ->sortable(),

                TextColumn::make('qrCode.name')
                    ->label('登记点')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('name')
                    ->label('访客姓名')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('phone')
                    ->label('手机号')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('info')
                    ->label('登记信息')
                    ->state(fn (VisitorRegistration $record) => collect($record->info_map)
                        ->map(fn ($value, $label) => "{$label}：{$value}")
                        ->implode('；'))
                    ->wrap()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('device.name')
                    ->label('开门设备')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('auth_status')
                    ->label('实名认证')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'verified' => '已认证',
                        'failed' => '认证失败',
                        default => '未认证',
                    })
                    ->toggleable(),

                IconColumn::make('status')
                    ->label('结果')
                    ->icon(fn (string $state) => $state === 'granted' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn (string $state) => $state === 'granted' ? 'success' : 'danger')
                    ->boolean(fn (VisitorRegistration $record) => $record->status === 'granted')
                    ->tooltip(fn (VisitorRegistration $record) => $record->result_message ?? ''),

                TextColumn::make('opened_at')
                    ->label('开门时间')
                    ->since()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('access_qr_code_id')
                    ->label('登记点（二维码）')
                    ->options(AccessQrCode::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('结果')
                    ->options([
                        'granted' => '成功',
                        'failed' => '失败',
                    ]),

                SelectFilter::make('auth_status')
                    ->label('实名认证')
                    ->options([
                        'unverified' => '未认证',
                        'verified' => '已认证',
                        'failed' => '认证失败',
                    ]),

                Filter::make('created_at')
                    ->label('登记时间')
                    ->form([
                        DatePicker::make('from')->label('开始日期')->native(false),
                        DatePicker::make('until')->label('结束日期')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                static::trashFilter(),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('详情')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (VisitorRegistration $record) => "登记详情 · {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->modalContent(fn (VisitorRegistration $record) => new HtmlString(self::detailHtml($record))),

                ...static::trashRecordActions(),
            ])
            ->bulkActions([
                BulkAction::make('export')
                    ->label('导出选中')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($records) => self::exportCsv($records->pluck('id')->all())),

                ...static::trashBulkActions(),
            ])
            ->headerActions([
                Action::make('exportFiltered')
                    ->label('导出当前筛选结果')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Table $table) => self::exportCsv($table->getFilteredQuery()->pluck('id')->all())),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([50, 100, 200]);
    }

    protected static function detailHtml(VisitorRegistration $r): string
    {
        $row = function (string $label, ?string $value) {
            $value = e($value ?: '-');

            return "<div style='display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:14px;'>
                        <span style='color:#94a3b8;'>{$label}</span><span style='font-weight:500;'>{$value}</span>
                    </div>";
        };

        $badge = $r->status === 'granted'
            ? '<span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:999px;font-size:12px;">成功</span>'
            : '<span style="background:#fee2e2;color:#dc2626;padding:2px 10px;border-radius:999px;font-size:12px;">失败</span>';

        $authBadge = match ($r->auth_status) {
            'verified' => '<span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:999px;font-size:12px;">已认证</span>',
            'failed' => '<span style="background:#fee2e2;color:#dc2626;padding:2px 10px;border-radius:999px;font-size:12px;">认证失败</span>',
            default => '<span style="background:#f1f5f9;color:#64748b;padding:2px 10px;border-radius:999px;font-size:12px;">未认证</span>',
        };

        $html = "<div style='padding:4px 8px;'>"
            .$row('登记点', $r->qrCode?->name)
            .$row('访客姓名', $r->name)
            .$row('手机号', $r->phone);

        foreach ($r->info_map as $label => $value) {
            $html .= $row($label, $value);
        }

        $html .= $row('实名认证', $authBadge)
            .$row('开门设备', $r->device?->name)
            .$row('结果', $badge)
            .$row('设备反馈', $r->result_message)
            .$row('登记时间', $r->created_at?->format('Y-m-d H:i:s'))
            .$row('开门时间', $r->opened_at?->format('Y-m-d H:i:s'))
            .$row('IP 地址', $r->ip)
            .'</div>';

        return $html;
    }

    /**
     * 导出访客记录为 CSV
     */
    public static function exportCsv(array $ids): void
    {
        $records = VisitorRegistration::query()
            ->whereIn('id', $ids)
            ->orderBy('created_at')
            ->with(['qrCode', 'device'])
            ->get();

        $filename = 'visitor_registrations_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($records) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['登记时间', '登记点', '访客姓名', '手机号', '身份证号', '来访单位', '来访事由', '开门设备', '实名认证', '结果', '设备反馈', '开门时间', 'IP地址']);

            foreach ($records as $r) {
                $info = $r->info_map;
                fputcsv($fh, [
                    $r->created_at?->format('Y-m-d H:i:s'),
                    $r->qrCode?->name ?? '',
                    $r->name,
                    $r->phone,
                    $info['身份证号'] ?? '',
                    $info['来访单位'] ?? '',
                    $info['来访事由'] ?? '',
                    $r->device?->name ?? '',
                    match ($r->auth_status) {
                        'verified' => '已认证',
                        'failed' => '认证失败',
                        default => '未认证',
                    },
                    $r->status === 'granted' ? '成功' : '失败',
                    $r->result_message ?? '',
                    $r->opened_at?->format('Y-m-d H:i:s') ?? '',
                    $r->ip ?? '',
                ]);
            }
            fclose($fh);
        };

        response()->stream($callback, 200, $headers)->send();
        exit;
    }
}
