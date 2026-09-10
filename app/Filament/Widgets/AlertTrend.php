<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use Filament\Widgets\ChartWidget;

class AlertTrend extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 8];

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '300px';

    protected bool $isSample = false;

    public function getHeading(): string
    {
        return $this->isSample ? '近7天告警趋势（示例数据）' : '近7天告警趋势';
    }

    protected function getData(): array
    {
        $owned = fn ($query) => auth()->user()?->isAdmin()
            ? $query
            : $query->where('user_id', auth()->id());

        $labels = [];
        $created = [];
        $resolved = [];

        foreach (range(6, 0) as $i) {
            $day = now()->subDays($i)->startOfDay();
            $labels[] = $day->format('m-d');
            $created[] = $owned(Alert::query())->whereBetween('triggered_at', [$day, $day->copy()->endOfDay()])->count();
            $resolved[] = $owned(Alert::query())->whereBetween('resolved_at', [$day, $day->copy()->endOfDay()])->count();
        }

        // 无真实数据时用示例数据占位
        if (array_sum($created) === 0 && array_sum($resolved) === 0) {
            $this->isSample = true;
            $created = [2, 5, 3, 8, 4, 6, 1];
            $resolved = [1, 4, 3, 6, 4, 5, 1];
        }

        return [
            'datasets' => [
                [
                    'label' => '新增告警',
                    'data' => $created,
                    'borderColor' => '#ff6b6b',
                    'backgroundColor' => 'rgba(255, 107, 107, .10)',
                    'tension' => .4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#ff6b6b',
                    'borderWidth' => 2.5,
                    'fill' => true,
                ],
                [
                    'label' => '处理告警',
                    'data' => $resolved,
                    'borderColor' => '#3d8bff',
                    'backgroundColor' => 'rgba(61, 139, 255, .10)',
                    'tension' => .4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#3d8bff',
                    'borderWidth' => 2.5,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 2,
                    ],
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, .15)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
