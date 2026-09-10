<?php

namespace App\Filament\Resources\AccessQrCodes\Tables;

use App\Filament\Resources\Concerns\HasTrash;
use App\IoT\Access\QrRenderer;
use App\Models\AccessQrCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class QrCodesTable
{
    use HasTrash;
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('登记点')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('device.name')
                    ->label('开门设备')
                    ->default('未绑定（仅登记）')
                    ->description(fn (AccessQrCode $record) => $record->device
                        ? '指令：'.$record->command_code.' = '.($record->command_value === '1' ? '开启' : '关闭')
                        : null),

                TextColumn::make('code')
                    ->label('扫码地址')
                    ->copyable()
                    ->copyableState(fn (AccessQrCode $record) => $record->publicUrl())
                    ->formatStateUsing(fn (AccessQrCode $record) => '/q/'.$record->code)
                    ->color('primary'),

                TextColumn::make('verify_mode')
                    ->label('安全验证')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'code' => '通行口令',
                        'question' => '验证问题',
                        default => '未验证',
                    })
                    ->colors([
                        'warning' => 'code',
                        'warning' => 'question',
                        'gray' => 'none',
                    ]),

                IconColumn::make('sms_verify')
                    ->label('短信验证')
                    ->boolean(),

                TextColumn::make('registrations_count')
                    ->label('登记次数')
                    ->counts('registrations')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),

                TextColumn::make('last_used_at')
                    ->label('最近使用')
                    ->since()
                    ->placeholder('从未')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('归属')
                    ->default('-')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('启用状态'),
                static::trashFilter(),
            ])
            ->recordActions([
                Action::make('qrcode')
                    ->label('二维码')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->modalHeading(fn (AccessQrCode $record) => "扫码登记 · {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->modalContent(fn (AccessQrCode $record) => new HtmlString(
                        view('filament.qr-modal', [
                            'qr' => $record,
                            'url' => $record->publicUrl(),
                            'svg' => QrRenderer::svg($record->publicUrl(), 220),
                            'dataUri' => QrRenderer::dataUri($record->publicUrl(), 220),
                        ])->render()
                    )),

                EditAction::make(),

                ...static::trashRecordActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(static::trashBulkActions()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
