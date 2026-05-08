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
use App\Http\Controllers\Admin\PaidAdvertisingDashboardController;
use App\Http\Controllers\Admin\BotProtectionController;
use App\Http\Controllers\Admin\DomainManagementController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::match(['post', 'options'], '/ip-check', [IpFilterController::class, 'check'])->name('ip-check');
Route::match(['get', 'post', 'options'], '/t/collect', [TrackingController::class, 'collect'])->name('t.collect');
Route::match(['post', 'options'], '/ingest/visit', [TrackingController::class, 'collect'])->name('ingest.visit');
Route::get('/tag/{domainKey}.js', [TagController::class, 'js'])->name('tag.js');
Route::get('/tag/{domainKey}.html', [TagController::class, 'noscript'])->name('tag.noscript');

Route::get('/cron/run/{token}', [CronController::class, 'run'])->name('cron.run');
Route::get('/cron/aggregate/{token}', [CronController::class, 'aggregate'])->name('cron.aggregate');

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

Route::get('/admin/integrations/google/redirect', [IntegrationsController::class, 'googleRedirect'])->name('integrations.google.redirect');
Route::get('/admin/integrations/google/callback', [IntegrationsController::class, 'googleCallback'])->name('integrations.google.callback');

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
        Route::post('/integrations/google/{connection}/sync-accounts', [IntegrationsController::class, 'syncAccounts'])->name('integrations.google.sync-accounts');
        Route::delete('/integrations/google/{connection}', [IntegrationsController::class, 'disconnect'])->name('integrations.google.disconnect');
        Route::post('/integrations/accounts', [IntegrationsController::class, 'storeAccount'])->name('integrations.store-account');
        Route::post('/integrations/mappings', [IntegrationsController::class, 'storeMapping'])->name('integrations.store-mapping');
        Route::delete('/integrations/mappings/{mapping}', [IntegrationsController::class, 'destroyMapping'])->name('integrations.destroy-mapping');
        Route::get('/paid-marketing/dashboard', [PaidAdvertisingDashboardController::class, 'index'])->name('paid-marketing.dashboard');
        Route::get('/paid-marketing/detection-settings', [PaidMarketingController::class, 'detectionSettings'])->name('paid-marketing.detection-settings');
        Route::post('/paid-marketing/detection-settings/{domain}', [PaidMarketingController::class, 'updateDetectionSettings'])->name('paid-marketing.detection-settings.update');
        Route::get('/bot-protection', [BotProtectionController::class, 'dashboard'])->name('bot-protection.dashboard');
        Route::get('/bot-protection/advanced', [BotProtectionController::class, 'advancedView'])->name('bot-protection.advanced');
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

Route::middleware('auth')->group(function () {
    Route::get('/overview/summary', [DashboardController::class, 'summary']);
    Route::get('/insights', [DashboardController::class, 'insights']);
    Route::get('/analytics/trends', [DashboardController::class, 'trends']);
    Route::get('/analytics/threats', [DashboardController::class, 'threats']);
    Route::get('/notifications', [DashboardController::class, 'notifications']);
    Route::get('/dashboard/live-snapshot', [DashboardController::class, 'liveSnapshot']);
    Route::get('/dashboard/live-stream', [DashboardController::class, 'liveStream']);
    Route::get('/domains/performance', [DashboardController::class, 'domainPerformance']);
    Route::get('/campaigns', [DashboardController::class, 'campaigns']);
    Route::put('/user/preferences', [DashboardController::class, 'preferences']);

    Route::get('/domains', [DomainManagementController::class, 'list']);
    Route::post('/domains', [DomainManagementController::class, 'store']);
    Route::post('/domains/validate', [DomainManagementController::class, 'validateDomain']);
    Route::post('/domains/bulk-add', [DomainManagementController::class, 'bulkAdd']);
    Route::put('/domains/{domain}/status', [DomainManagementController::class, 'updateStatus']);
    Route::get('/domains/{domain}/tracking-script', [DomainManagementController::class, 'trackingScript']);
    Route::get('/domains/{domain}/api-key', [DomainManagementController::class, 'apiKey']);
    Route::put('/domains/{domain}/gtm', [DomainManagementController::class, 'updateGtm']);
    Route::put('/domains/{domain}/tracking-params', [DomainManagementController::class, 'updateTrackingParams']);
    Route::post('/domains/{domain}/email-developer', [DomainManagementController::class, 'emailDeveloper']);
    Route::post('/domains/{domain}/verify-wordpress', [DomainManagementController::class, 'verifyWordpress']);
    Route::get('/tracking/wordpress-plugin', [DomainManagementController::class, 'wordpressPlugin']);

    Route::get('/detection/{domain}/rules', [PaidMarketingController::class, 'getRulesApi']);
    Route::put('/detection/{domain}/rules', [PaidMarketingController::class, 'updateRulesApi']);
    Route::put('/detection/{domain}/exclusions', [PaidMarketingController::class, 'updateExclusionsApi']);
    Route::put('/detection/{domain}/marketing-rules', [PaidMarketingController::class, 'updateMarketingRulesApi']);

    Route::get('/bot-protection/summary', [BotProtectionController::class, 'summary']);
    Route::get('/bot-protection/traffic-breakdown', [BotProtectionController::class, 'trafficBreakdown']);
    Route::get('/bot-protection/threat-groups', [BotProtectionController::class, 'threatGroups']);
    Route::get('/bot-protection/invalid-breakdown', [BotProtectionController::class, 'invalidBreakdown']);
    Route::get('/bot-protection/countries', [BotProtectionController::class, 'countries']);
    Route::get('/bot-protection/domains-summary', [BotProtectionController::class, 'domainsSummary']);
    Route::get('/bot-protection/visits', [BotProtectionController::class, 'visits']);
    Route::get('/bot-protection/export.csv', [BotProtectionController::class, 'exportCsv'])->name('bot-protection.export');

    Route::get('/paid-marketing/summary', [PaidAdvertisingDashboardController::class, 'summary']);
    Route::get('/paid-marketing/trends', [PaidAdvertisingDashboardController::class, 'trends']);
    Route::get('/paid-marketing/blocking-activity', [PaidAdvertisingDashboardController::class, 'blockingActivity']);
    Route::get('/paid-marketing/campaigns', [PaidAdvertisingDashboardController::class, 'campaigns']);
    Route::get('/paid-marketing/keywords', [PaidAdvertisingDashboardController::class, 'keywords']);
    Route::get('/paid-marketing/countries', [PaidAdvertisingDashboardController::class, 'countries']);
    Route::get('/paid-marketing/ips', [PaidAdvertisingDashboardController::class, 'ips']);
    Route::get('/paid-marketing/heatmap', [PaidAdvertisingDashboardController::class, 'heatmap']);

    Route::get('/integrations/connected', [IntegrationsController::class, 'connectedJson']);
    Route::get('/integrations/status', [IntegrationsController::class, 'statusJson']);
    Route::get('/integrations/all', [IntegrationsController::class, 'allJson']);
    Route::get('/integrations/google/oauth-url', [IntegrationsController::class, 'googleOauthUrl']);
    Route::get('/integrations/google/pixel-guard', [IntegrationsController::class, 'pixelGuardGet']);
    Route::put('/integrations/google/pixel-guard', [IntegrationsController::class, 'pixelGuardSave']);
    Route::post('/integrations/google/audience-exclusion', [IntegrationsController::class, 'audienceExclusionSave']);
    Route::get('/integrations/direct-ads', [IntegrationsController::class, 'directAdsList']);
    Route::post('/integrations/direct-ads', [IntegrationsController::class, 'directAdsStore']);
    Route::delete('/integrations/direct-ads/{integration}', [IntegrationsController::class, 'directAdsDestroy']);
});

require __DIR__.'/auth.php';
