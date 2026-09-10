<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * 仪表板顶部欢迎横幅：蓝色渐变 + 问候语 + 快捷按钮。
 */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = ['default' => 1, 'xl' => 12];

    protected string $view = 'filament.widgets.welcome-banner';

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'userName' => $user?->name ?? '管理员',
            'isAdmin' => (bool) $user?->isAdmin(),
            'loginTime' => now()->format('Y-m-d H:i'),
            'weekday' => ['日', '一', '二', '三', '四', '五', '六'][now()->dayOfWeek],
        ];
    }
}
