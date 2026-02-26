<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainsTrackersController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\PlansController;
use App\Http\Controllers\Admin\SaaSProductsController;
use App\Http\Controllers\Admin\SecurityLogsController;
use App\Http\Controllers\Admin\SubscriptionsController;
use App\Http\Controllers\Admin\SupportSystemController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\TrafficBotLogsController;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->is_admin
            ? redirect()->route('dashboard')
            : view('welcome');
    }
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::get('/saas-products', [SaaSProductsController::class, 'index'])->name('saas-products');
        Route::get('/plans', [PlansController::class, 'index'])->name('plans');
        Route::get('/subscriptions', [SubscriptionsController::class, 'index'])->name('subscriptions');
        Route::get('/payments', [PaymentsController::class, 'index'])->name('payments');
        Route::get('/domains-trackers', [DomainsTrackersController::class, 'index'])->name('domains-trackers');
        Route::get('/traffic-bot-logs', [TrafficBotLogsController::class, 'index'])->name('traffic-bot-logs');
        Route::get('/automation', [AutomationController::class, 'index'])->name('automation');
        Route::get('/integrations', [IntegrationsController::class, 'index'])->name('integrations');
        Route::get('/support-system', [SupportSystemController::class, 'index'])->name('support-system');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/security-logs', [SecurityLogsController::class, 'index'])->name('security-logs');
        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
