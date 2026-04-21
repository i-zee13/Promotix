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
use App\Http\Controllers\Admin\IpLogsController;
use App\Http\Controllers\IpFilterController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\PaidMarketingController;
use App\Http\Controllers\Admin\DomainManagementController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::match(['post', 'options'], '/ip-check', [IpFilterController::class, 'check'])->name('ip-check');
Route::match(['get', 'post', 'options'], '/t/collect', [TrackingController::class, 'collect'])->name('t.collect');
Route::get('/tag/{domainKey}.js', [TagController::class, 'js'])->name('tag.js');
Route::get('/tag/{domainKey}.html', [TagController::class, 'noscript'])->name('tag.noscript');

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }
    $user = auth()->user();
    if ($user->is_admin) {
        return redirect()->route('dashboard');
    }
    if ($user->role_id && $user->role?->permissions()->exists()) {
        return redirect()->route('admin');
    }
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', function () {
            $menu = config('admin.menu', []);
            foreach ($menu as $slug => $item) {
                if (auth()->user()->canAccess($slug)) {
                    return redirect()->route($item['route']);
                }
            }
            abort(403, 'Your role has no menu permissions.');
        })->name('admin');
        Route::middleware('permission')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/paid-marketing/detailed-view', [PaidMarketingController::class, 'detailedView'])->name('paid-marketing.detailed');
        Route::get('/domains', [DomainManagementController::class, 'index'])->name('domains.index');
        Route::post('/domains', [DomainManagementController::class, 'store'])->name('domains.store');
        Route::get('/domains/{domain}/setup', [DomainManagementController::class, 'setup'])->name('domains.setup');
        Route::get('/domains/{domain}/wordpress-plugin.zip', [DomainManagementController::class, 'downloadWpPlugin'])->name('domains.wp-plugin');
        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::patch('/users/{user}/role', [UsersController::class, 'updateRole'])->name('users.update-role');
        Route::get('/saas-products', [SaaSProductsController::class, 'index'])->name('saas-products');
        Route::get('/plans', [PlansController::class, 'index'])->name('plans');
        Route::get('/subscriptions', [SubscriptionsController::class, 'index'])->name('subscriptions');
        Route::get('/payments', [PaymentsController::class, 'index'])->name('payments');
        Route::get('/domains-trackers', [DomainsTrackersController::class, 'index'])->name('domains-trackers');
        Route::get('/traffic-bot-logs', [TrafficBotLogsController::class, 'index'])->name('traffic-bot-logs');
        Route::get('/ip-logs', [IpLogsController::class, 'index'])->name('ip-logs');
        Route::post('/ip-logs/{ipLog}/toggle-block', [IpLogsController::class, 'toggleBlock'])->name('ip-logs.toggle-block');
        Route::delete('/ip-logs/{ipLog}', [IpLogsController::class, 'destroy'])->name('ip-logs.destroy');
        Route::get('/automation', [AutomationController::class, 'index'])->name('automation');
        Route::get('/integrations', [IntegrationsController::class, 'index'])->name('integrations');
        Route::get('/support-system', [SupportSystemController::class, 'index'])->name('support-system');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/security-logs', [SecurityLogsController::class, 'index'])->name('security-logs');
        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings');
        Route::resource('roles', \App\Http\Controllers\Admin\RolesController::class)->except(['show']);
        });
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
