<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAlerts extends TableWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 4];

    public function table(Table $table): Table
    {
        $query = Alert::query()->where('status', '!=', 'resolved')->latest('triggered_at')->limit(6);

        if (! auth()->user()?->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $table
            ->query($query)
            ->heading('最新告警')
            ->columns([
                TextColumn::make('level')
                    ->label('级别')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ['info' => '提示', 'warning' => '警告', 'critical' => '严重'][$state] ?? $state),

                TextColumn::make('title')
                    ->label('内容')
                    ->limit(14),

                TextColumn::make('triggered_at')
                    ->label('时间')
                    ->since(),
            ])
            ->emptyStateHeading('暂无告警')
            ->emptyStateDescription('设备运行正常，产生新告警时会显示在这里')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->paginated(false);
    }
}
