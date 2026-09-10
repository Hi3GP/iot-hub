<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(admin_path())
            ->login()
            ->brandName(fn () => \App\Models\SystemSetting::get('platform_name'))
            ->brandLogo(fn () => \App\Models\SystemSetting::get('logo')
                ? '/storage/'.ltrim((string) \App\Models\SystemSetting::get('logo'), '/')
                : asset('logo.svg'))
            ->sidebarWidth('16rem')
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
            ->renderHook(\Filament\View\PanelsRenderHook::HEAD_START, fn () => '<link rel="icon" href="'.(\App\Models\SystemSetting::get('favicon')
                ? '/storage/'.ltrim((string) \App\Models\SystemSetting::get('favicon'), '/')
                : asset('favicon.ico')).'">')
            ->renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, function () {
                $subtitle = trim((string) \App\Models\SystemSetting::get('login_subtitle'));

                return $subtitle !== ''
                    ? '<p style="text-align:center;font-size:0.875rem;color:#6b7280;margin:0 0 1.25rem;">'.e($subtitle).'</p>'
                    : '';
            })
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode(false)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => view('filament.admin-styles')->render())
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => view('filament.auth-footer')->render())
            ->navigationGroups([
                '设备接入',
                '设备中心',
                '监控告警',
                '自动化联动',
                '门禁登记',
                '园区配置',
                '系统管理',
            ])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('数据大屏')
                    ->icon('heroicon-o-squares-2x2')
                    ->group('监控告警')
                    ->sort(1)
                    ->url('/dashboard')
                    ->openUrlInNewTab(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
