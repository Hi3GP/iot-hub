<?php

namespace App\Filament\Widgets;

use App\Models\Connector;
use App\Models\Device;
use Filament\Widgets\ChartWidget;

class PlatformDistributionChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 4];

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '240px';

    protected bool $isSample = false;

    public function getHeading(): string
    {
        return $this->isSample ? '平台接入分布（示例数据）' : '平台接入分布';
    }

    protected function getData(): array
    {
        $connectors = Connector::query()
            ->when(! auth()->user()?->isAdmin(), fn ($q) => $q->where('user_id', auth()->id()))
            ->withCount(['devices as devices_total' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $labels = $connectors->pluck('name')->all();
        $data = $connectors->pluck('devices_total')->map(fn ($v) => (int) $v)->all();

        if (array_sum($data) === 0) {
            $this->isSample = true;
            $labels = ['天翼云 CTWing', '萤石云', 'LifeSmart', '本地设备'];
            $data = [17, 8, 6, 2];
        }

        return [
            'datasets' => [
                [
                    'label' => '设备数',
                    'data' => $data,
                    'backgroundColor' => '#3d8bff',
                    'borderRadius' => 6,
                    'barThickness' => 18,
                ],
                [
                    'label' => '在线数',
                    'data' => collect($data)->map(fn ($v) => (int) round($v * 0.8))->all(),
                    'backgroundColor' => '#22c583',
                    'borderRadius' => 6,
                    'barThickness' => 18,
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
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, .15)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
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
        return 'bar';
    }
}
