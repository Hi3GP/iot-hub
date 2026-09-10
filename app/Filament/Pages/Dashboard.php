<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    /**
     * 仪表板栅格：xl 断点 12 列
     * 第一行：告警趋势 8 列 + 平台总览 4 列
     * 第二行：品类分布 4 列 + 平台分布 4 列 + 最新告警 4 列
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 12,
        ];
    }
}
