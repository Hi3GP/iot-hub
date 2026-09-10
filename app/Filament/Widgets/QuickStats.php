<?php

namespace App\Filament\Widgets;

use App\Models\AccessQrCode;
use App\Models\Alert;
use App\Models\Connector;
use App\Models\Device;
use App\Models\VisitorRegistration;
use Filament\Widgets\Widget;

/**
 * 仪表板快速统计行：白卡内横向排列的关键指标（彩色图标 + 数字）。
 */
class QuickStats extends Widget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = ['default' => 1, 'xl' => 12];

    protected ?string $pollingInterval = '30s';

    protected string $view = 'filament.widgets.quick-stats';

    protected function getViewData(): array
    {
        $owned = fn ($query) => auth()->user()?->isAdmin()
            ? $query
            : $query->where('user_id', auth()->id());

        $total = $owned(Device::query()->where('is_active', true))->count();
        $online = $owned(Device::query()->where('is_active', true))->where('status', 'online')->count();
        $offline = $total - $online;
        $activeAlerts = $owned(Alert::query())->where('status', 'active')->count();
        $connectors = $owned(Connector::query()->where('is_active', true))->count();
        $qrCodes = $owned(AccessQrCode::query())->count();
        $visitorsToday = $owned(VisitorRegistration::query())
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $base = admin_url();

        return [
            'stats' => [
                ['label' => '设备总数', 'value' => $total, 'icon' => 'heroicon-o-circle-stack', 'color' => 'blue', 'url' => "{$base}/devices"],
                ['label' => '在线设备', 'value' => $online, 'icon' => 'heroicon-o-wifi', 'color' => 'emerald', 'url' => "{$base}/devices"],
                ['label' => '离线设备', 'value' => $offline, 'icon' => 'heroicon-o-signal-slash', 'color' => 'gray', 'url' => "{$base}/devices"],
                ['label' => '活跃告警', 'value' => $activeAlerts, 'icon' => 'heroicon-o-bell-alert', 'color' => 'coral', 'url' => "{$base}/alerts"],
                ['label' => '接入平台', 'value' => $connectors, 'icon' => 'heroicon-o-link', 'color' => 'violet', 'url' => "{$base}/connectors"],
                ['label' => '今日访客登记', 'value' => $visitorsToday, 'icon' => 'heroicon-o-users', 'color' => 'amber', 'url' => "{$base}/visitor-registrations"],
            ],
            'qrCodes' => $qrCodes,
        ];
    }
}
