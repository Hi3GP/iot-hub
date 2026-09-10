<?php

namespace App\Filament\Widgets;

use App\IoT\ThingModel;
use App\Models\Device;
use Filament\Widgets\ChartWidget;

class DeviceCategoryChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 4];

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '240px';

    protected bool $isSample = false;

    public function getHeading(): string
    {
        return $this->isSample ? '设备品类分布（示例数据）' : '设备品类分布';
    }

    protected function getData(): array
    {
        $query = Device::query()->where('is_active', true);

        if (! auth()->user()?->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $counts = $query
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $labels = [];
        $data = [];

        foreach (ThingModel::CATEGORIES as $code => $name) {
            $total = (int) ($counts[$code] ?? 0);
            if ($total > 0) {
                $labels[] = $name;
                $data[] = $total;
            }
        }

        if ($data === []) {
            $this->isSample = true;
            $labels = ['门锁', '摄像头', '开关/插座', '环境传感器', '门禁'];
            $data = [15, 8, 6, 4, 3];
        }

        return [
            'datasets' => [
                [
                    'label' => '设备数',
                    'data' => $data,
                    'backgroundColor' => [
                        '#3d8bff',
                        '#38bdf8',
                        '#22c583',
                        '#ffa940',
                        '#8b7cf6',
                        '#ff6b6b',
                    ],
                    'borderRadius' => 6,
                    'barThickness' => 16,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, .15)'],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
