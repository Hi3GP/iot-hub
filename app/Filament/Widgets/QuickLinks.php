<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * 快捷入口：2 列网格白卡（对标参考图"加入团队"）。
 */
class QuickLinks extends Widget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 4];

    protected string $view = 'filament.widgets.quick-links';

    protected function getViewData(): array
    {
        $base = admin_url();

        return [
            'links' => [
                ['label' => '设备中心', 'icon' => 'heroicon-o-squares-2x2', 'url' => "{$base}/devices"],
                ['label' => '平台连接器', 'icon' => 'heroicon-o-link', 'url' => "{$base}/connectors"],
                ['label' => '登记二维码', 'icon' => 'heroicon-o-qr-code', 'url' => "{$base}/access-qr-codes"],
                ['label' => '联动规则', 'icon' => 'heroicon-o-bolt', 'url' => "{$base}/linkage-rules"],
                ['label' => '告警中心', 'icon' => 'heroicon-o-bell-alert', 'url' => "{$base}/alerts"],
                ['label' => '空间管理', 'icon' => 'heroicon-o-building-office-2', 'url' => "{$base}/spaces"],
            ],
        ];
    }
}
