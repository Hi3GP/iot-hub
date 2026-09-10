<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VisitorAccessController;
use App\Http\Controllers\Webhooks\CTWingWebhookController;
use App\Http\Controllers\Webhooks\EzvizWebhookController;
use Illuminate\Support\Facades\Route;

// 根路径不暴露任何框架/应用信息，直接 404（安全加固）
Route::get('/', function () {
    abort(404);
});

// Laravel auth 中间件默认重定向到 route('login')，别名到后台登录页
Route::get('/login', fn () => redirect(admin_url('/login')))->name('login');

// 数据大屏（需登录）
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/camera/{id}', [DashboardController::class, 'camera'])->name('dashboard.camera');
    Route::get('/dashboard/camera/{id}/play', [DashboardController::class, 'play'])->name('dashboard.camera.play');
    Route::get('/dashboard/camera/{id}/stream', [DashboardController::class, 'stream'])->name('dashboard.camera.stream');
});

// 第三方平台推送回调
Route::post('/webhooks/ezviz', EzvizWebhookController::class)->name('webhooks.ezviz');
Route::post('/webhooks/ctwing', CTWingWebhookController::class)->name('webhooks.ctwing');

// 访客扫码登记开门（公开页面，无需登录）
Route::get('/q/{code}', [VisitorAccessController::class, 'show'])->name('visitor.register');
Route::post('/q/{code}/sms', [VisitorAccessController::class, 'sendSms'])
    ->middleware('throttle:visitor_access')
    ->name('visitor.sms');
Route::post('/q/{code}', [VisitorAccessController::class, 'store'])
    ->middleware('throttle:visitor_access')
    ->name('visitor.submit');
