<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AdminOperationsApiController;
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
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\UpgradePlanController;
use App\Http\Controllers\PortalInactiveController;
use App\Http\Controllers\SuperAdmin\BillingAutomationController;
use App\Http\Controllers\Admin\IpLogsController;
use App\Http\Controllers\IpFilterController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\PaidMarketingController;
use App\Http\Controllers\Admin\PaidAdvertisingDashboardController;
use App\Http\Controllers\Admin\BotProtectionController;
use App\Http\Controllers\Admin\DomainManagementController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PaymentsController as SuperAdminPaymentsController;
use App\Http\Controllers\SuperAdmin\PlansController as SuperAdminPlansController;
use App\Http\Controllers\SuperAdmin\ProductsController as SuperAdminProductsController;
use App\Http\Controllers\SuperAdmin\SubscriptionsController as SuperAdminSubscriptionsController;
use App\Http\Controllers\SuperAdmin\SupportPagesController as SuperAdminSupportPagesController;
use App\Http\Controllers\SuperAdmin\TicketsController as SuperAdminTicketsController;
use App\Http\Controllers\SuperAdmin\RolesController as SuperAdminRolesController;
use App\Http\Controllers\SuperAdmin\UsersController as SuperAdminUsersController;
use App\Http\Controllers\SuperAdmin\IpAllowlistController as SuperAdminIpAllowlistController;
use App\Http\Controllers\CountryFlagController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DatabaseExportController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::match(['post', 'options'], '/ip-check', [IpFilterController::class, 'check'])->middleware('throttle:180,1')->name('ip-check');
Route::match(['get', 'post', 'options'], '/t/collect', [TrackingController::class, 'collect'])->middleware('throttle:240,1')->name('t.collect');
// GET must be allowed: the embedded tag falls back to an <img> pixel (query string) when sendBeacon/fetch fail.
Route::match(['get', 'post', 'options'], '/ingest/visit', [TrackingController::class, 'collect'])->middleware('throttle:240,1')->name('ingest.visit');
Route::get('/click', [TrackingController::class, 'googleAdsClick'])->middleware('throttle:120,1')->name('google-ads.click');
Route::get('/docs/click-tracker', function () {
    return response()->view('docs.transparent-click-tracker', [
        'trackerHost' => \App\Support\TransparentClickTracker::baseUrl(),
        'template' => \App\Support\GoogleAdsClickRedirect::trackingTemplateUrl(),
    ]);
})->name('click-tracker.docs');
Route::match(['post', 'options'], '/ingest/session-recording', [TrackingController::class, 'sessionRecording'])->middleware('throttle:120,1')->name('ingest.session-recording');
Route::get('/media/flags/{code}', [CountryFlagController::class, 'show'])
    ->where('code', '[a-zA-Z]{2}')
    ->name('country-flag');
Route::get('/tag/{domainKey}.js', [TagController::class, 'js'])->name('tag.js');
Route::get('/tag/{domainKey}.html', [TagController::class, 'noscript'])->name('tag.noscript');

Route::get('/cron/run/{token}', [CronController::class, 'run'])->name('cron.run');
Route::get('/cron/aggregate/{token}', [CronController::class, 'aggregate'])->name('cron.aggregate');

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->homeRouteName());
})->name('home');

Route::get('/admin/integrations/google/redirect', [IntegrationsController::class, 'googleRedirect'])->name('integrations.google.redirect');
Route::get('/admin/integrations/google/callback', [IntegrationsController::class, 'googleCallback'])->name('integrations.google.callback');

Route::middleware(['auth', 'super-admin'])->get('/db/export', [DatabaseExportController::class, 'download'])->name('db.export');

Route::middleware(['auth', 'super-admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/', fn () => redirect()->route('super-admin.dashboard'))->name('home');
        Route::get('/users', [SuperAdminUsersController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [SuperAdminUsersController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/assign-plan', [SuperAdminUsersController::class, 'assignPlan'])->name('users.assign-plan');
        Route::post('/users/{user}/assign-team', [SuperAdminUsersController::class, 'assignTeam'])->name('users.assign-team');
        Route::put('/users/{user}', [SuperAdminUsersController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [SuperAdminUsersController::class, 'status'])->name('users.status');
        Route::post('/users/{user}/reset-password', [SuperAdminUsersController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('/users/{user}', [SuperAdminUsersController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/impersonate', [SuperAdminUsersController::class, 'impersonate'])->name('users.impersonate');
        Route::resource('roles', SuperAdminRolesController::class)->except(['show']);
        Route::resource('products', SuperAdminProductsController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/products/{product}/duplicate', [SuperAdminProductsController::class, 'duplicate'])->name('products.duplicate');
        Route::resource('plans', SuperAdminPlansController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/plans/{plan}/roles', [SuperAdminPlansController::class, 'syncPlanRoles'])->name('plans.roles');
        Route::get('/subscriptions', [SuperAdminSubscriptionsController::class, 'index'])->name('subscriptions.index');
        Route::put('/subscriptions/{subscription}', [SuperAdminSubscriptionsController::class, 'update'])->name('subscriptions.update');
        Route::delete('/subscriptions/{subscription}', [SuperAdminSubscriptionsController::class, 'destroy'])->name('subscriptions.destroy');
        Route::get('/payments', [SuperAdminPaymentsController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}/receipt', [SuperAdminPaymentsController::class, 'downloadReceipt'])->name('payments.receipt');
        Route::post('/payments/{payment}/verify', [SuperAdminPaymentsController::class, 'verify'])->name('payments.verify');
        Route::post('/payments/{payment}/reject', [SuperAdminPaymentsController::class, 'reject'])->name('payments.reject');
        Route::post('/payments/{payment}/mark-failed', [SuperAdminPaymentsController::class, 'markFailed'])->name('payments.mark-failed');
        Route::get('/billing-automation', [BillingAutomationController::class, 'index'])->name('billing-automation.index');
        Route::post('/billing-automation', [BillingAutomationController::class, 'update'])->name('billing-automation.update');
        Route::post('/users/invite', [SuperAdminUsersController::class, 'invite'])->name('users.invite');
        Route::post('/users/create', [SuperAdminUsersController::class, 'store'])->name('users.store');
        Route::get('/domains', [SuperAdminSupportPagesController::class, 'domains'])->name('domains.index');
        Route::patch('/domains/{domain}/tracking', [SuperAdminSupportPagesController::class, 'toggleDomainTracking'])->name('domains.toggle-tracking');
        Route::patch('/domains/{domain}/force-verify', [SuperAdminSupportPagesController::class, 'forceVerifyDomain'])->name('domains.force-verify');
        Route::patch('/domains/{domain}/regenerate-tracker', [SuperAdminSupportPagesController::class, 'regenerateDomainTracker'])->name('domains.regenerate-tracker');
        Route::delete('/domains/{domain}', [SuperAdminSupportPagesController::class, 'destroyDomain'])->name('domains.destroy');
        Route::get('/analytics', [SuperAdminSupportPagesController::class, 'analytics'])->name('analytics.index');
        Route::get('/security', [SuperAdminSupportPagesController::class, 'security'])->name('security.index');
        Route::post('/security/block-ip', [SuperAdminSupportPagesController::class, 'blockIp'])->name('security.block-ip');
        Route::post('/security/unblock-ip', [SuperAdminSupportPagesController::class, 'unblockIp'])->name('security.unblock-ip');
        Route::post('/security/{id}/flag', [SuperAdminSupportPagesController::class, 'flagDetection'])->name('security.flag');
        Route::get('/settings', [SuperAdminSupportPagesController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [SuperAdminSupportPagesController::class, 'saveSettings'])->name('settings.save');
        Route::get('/settings/whitelist', [SuperAdminIpAllowlistController::class, 'index'])->name('settings.whitelist');
        Route::post('/settings/whitelist', [SuperAdminIpAllowlistController::class, 'store'])->name('settings.whitelist.store');
        Route::patch('/settings/whitelist/{entry}/toggle', [SuperAdminIpAllowlistController::class, 'toggle'])->name('settings.whitelist.toggle');
        Route::delete('/settings/whitelist/{entry}', [SuperAdminIpAllowlistController::class, 'destroy'])->name('settings.whitelist.destroy');
        Route::put('/email-templates/{emailTemplate}', [SuperAdminSupportPagesController::class, 'updateEmailTemplate'])->name('email-templates.update');
        Route::post('/email-templates/{emailTemplate}/restore', [SuperAdminSupportPagesController::class, 'restoreEmailTemplate'])->name('email-templates.restore');
        Route::post('/email-templates/{emailTemplate}/send-test', [SuperAdminSupportPagesController::class, 'sendTestEmailTemplate'])->name('email-templates.send-test');
        Route::post('/plans/{plan}/toggles', [SuperAdminSupportPagesController::class, 'updatePlanToggles'])->name('plans.toggles');
        Route::get('/tickets', [SuperAdminTicketsController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [SuperAdminTicketsController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/assign', [SuperAdminTicketsController::class, 'assign'])->name('tickets.assign');
        Route::post('/tickets/{ticket}/reply', [SuperAdminTicketsController::class, 'reply'])->name('tickets.reply');
        Route::get('/guidance', [\App\Http\Controllers\SuperAdmin\GuidanceController::class, 'index'])->name('guidance.index');
        Route::post('/guidance', [\App\Http\Controllers\SuperAdmin\GuidanceController::class, 'store'])->name('guidance.store');
        Route::put('/guidance/{guidance}', [\App\Http\Controllers\SuperAdmin\GuidanceController::class, 'update'])->name('guidance.update');
        Route::delete('/guidance/{guidance}', [\App\Http\Controllers\SuperAdmin\GuidanceController::class, 'destroy'])->name('guidance.destroy');
        Route::get('/traffic-bot-logs/cross-domain', [SuperAdminSupportPagesController::class, 'crossDomainIntel'])->name('traffic.cross-domain');
        Route::post('/feature-flags', [SuperAdminSupportPagesController::class, 'storeFeatureFlag'])->name('feature-flags.store');
        Route::patch('/feature-flags/{featureFlag}/toggle', [SuperAdminSupportPagesController::class, 'toggleFeatureFlag'])->name('feature-flags.toggle');
        Route::get('/traffic-bot-logs', [SuperAdminSupportPagesController::class, 'trafficBotLogs'])->name('traffic.index');
        Route::get('/automation', [SuperAdminSupportPagesController::class, 'automation'])->name('automation.index');
        Route::get('/integrations', [SuperAdminSupportPagesController::class, 'integrations'])->name('integrations.index');
    });

Route::middleware(['auth', 'admin', 'portal-product'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/portal-inactive', PortalInactiveController::class)->name('portal.inactive');

        Route::get('/', function () {
            $menu = config('admin.menu', []);
            foreach ($menu as $slug => $item) {
                if (auth()->user()->canAccess($slug)) {
                    return redirect()->route($item['route']);
                }
            }
            abort(403, 'Your role has no menu permissions.');
        })->name('admin');
        Route::middleware(['permission', 'redirect-super-admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reports', [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('reports.index');
        Route::get('/paid-marketing/detailed-view', [PaidMarketingController::class, 'detailedView'])->name('paid-marketing.detailed');
        Route::get('/paid-marketing/detailed-visits', [PaidMarketingController::class, 'detailedVisits'])->name('paid-marketing.detailed-visits');
        Route::get('/paid-marketing/detailed-ip-timeline', [PaidMarketingController::class, 'detailedIpTimeline'])->name('paid-marketing.detailed-ip-timeline');
        Route::get('/paid-marketing/detailed-export.csv', [PaidMarketingController::class, 'exportDetailedCsv'])->name('paid-marketing.detailed-export');
        Route::get('/paid-marketing/detailed-export.xlsx', [PaidMarketingController::class, 'exportDetailedXlsx'])->name('paid-marketing.detailed-export-xlsx');
        Route::post('/paid-marketing/detailed-override', [PaidMarketingController::class, 'overrideVisitDecision'])->name('paid-marketing.detailed-override');
        Route::post('/paid-marketing/detailed-bulk', [PaidMarketingController::class, 'bulkVisitActions'])->name('paid-marketing.detailed-bulk');
        Route::get('/paid-marketing/session-recording/{recording}', [PaidMarketingController::class, 'showSessionRecording'])->name('paid-marketing.session-recording');
        Route::delete('/paid-marketing/session-recording/{recording}', [PaidMarketingController::class, 'destroySessionRecording'])->name('paid-marketing.session-recording.destroy');
        Route::get('/domains', [DomainManagementController::class, 'index'])->name('domains.index');
        Route::post('/domains', [DomainManagementController::class, 'store'])->name('domains.store');
        Route::put('/domains/{domain}', [DomainManagementController::class, 'update'])->name('domains.update');
        Route::delete('/domains/{domain}', [DomainManagementController::class, 'destroy'])->name('domains.destroy');
        Route::get('/domains/{domain}/setup', [DomainManagementController::class, 'setup'])->name('domains.setup');
        Route::get('/domains/{domain}/paid-marketing/connect', function (\App\Models\Domain $domain) {
            abort_unless($domain->user_id === auth()->id() && $domain->isManual(), 403);

            return redirect()->route('integrations.google.redirect', [
                'domain_id' => $domain->id,
                'context' => 'paid_domain',
            ]);
        })->name('domains.paid-marketing.connect');
        Route::get('/domains/{domain}/wordpress-plugin.zip', [DomainManagementController::class, 'downloadWpPlugin'])->name('domains.wp-plugin');
        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::post('/team/invite', [UsersController::class, 'invite'])->name('team.invite');
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
        Route::get('/automation/{job}', [AutomationController::class, 'show'])->name('automation.show');
        Route::get('/integrations', [IntegrationsController::class, 'index'])->name('integrations');
        Route::post('/integrations/google/{connection}/sync-accounts', [IntegrationsController::class, 'syncAccounts'])->name('integrations.google.sync-accounts');
        Route::post('/integrations/google/{connection}/test', [IntegrationsController::class, 'testConnection'])->name('integrations.google.test');
        Route::post('/integrations/google/reconnect-all', [IntegrationsController::class, 'reconnectAllDomains'])->name('integrations.google.reconnect-all');
        Route::delete('/integrations/google/{connection}', [IntegrationsController::class, 'disconnect'])->name('integrations.google.disconnect');
        Route::post('/integrations/accounts', [IntegrationsController::class, 'storeAccount'])->name('integrations.store-account');
        Route::post('/integrations/mappings', [IntegrationsController::class, 'storeMapping'])->name('integrations.store-mapping');
        Route::delete('/integrations/mappings/{mapping}', [IntegrationsController::class, 'destroyMapping'])->name('integrations.destroy-mapping');
        Route::get('/integrations/google-ads/campaign-metrics', [IntegrationsController::class, 'campaignMetricsForHost'])->name('integrations.google.campaign-metrics');
        Route::get('/domains/{domain}/google-ads/pick-accounts', [IntegrationsController::class, 'pickAccountsJson'])->name('domains.google.pick-accounts');
        Route::post('/domains/{domain}/google-ads/link', [IntegrationsController::class, 'linkDomainPaidAccount'])->name('domains.google.link-account');
        Route::get('/paid-marketing/dashboard', [PaidAdvertisingDashboardController::class, 'index'])->name('paid-marketing.dashboard');
        Route::post('/paid-marketing/detection-settings/{domain}/google-exclusion/push', [PaidMarketingController::class, 'pushGoogleExclusionIp'])->name('paid-marketing.detection-settings.google-exclusion.push');
        Route::post('/paid-marketing/detection-settings/{domain}/google-exclusion/push-row', [PaidMarketingController::class, 'pushGoogleExclusionRow'])->name('paid-marketing.detection-settings.google-exclusion.push-row');
        Route::post('/paid-marketing/detection-settings/{domain}/google-exclusion/toggle-row', [PaidMarketingController::class, 'toggleGoogleExclusionRow'])->name('paid-marketing.detection-settings.google-exclusion.toggle-row');
        Route::post('/paid-marketing/detection-settings/{domain}/google-exclusion/push-bulk', [PaidMarketingController::class, 'pushGoogleExclusionBulk'])->name('paid-marketing.detection-settings.google-exclusion.push-bulk');
        Route::post('/paid-marketing/detection-settings/{domain}/google-exclusion/sync', [PaidMarketingController::class, 'syncGoogleExclusionIps'])->name('paid-marketing.detection-settings.google-exclusion.sync');
        Route::get('/paid-marketing/detection-settings', [PaidMarketingController::class, 'detectionSettings'])->name('paid-marketing.detection-settings');
        Route::post('/paid-marketing/detection-settings/{domain}', [PaidMarketingController::class, 'updateDetectionSettings'])->name('paid-marketing.detection-settings.update');
        Route::get('/paid-marketing/geo/countries', [PaidMarketingController::class, 'geoCountries'])->name('paid-marketing.geo.countries');
        Route::get('/paid-marketing/geo/states', [PaidMarketingController::class, 'geoStates'])->name('paid-marketing.geo.states');
        Route::get('/paid-marketing/geo/cities', [PaidMarketingController::class, 'geoCities'])->name('paid-marketing.geo.cities');
        Route::get('/bot-protection', [BotProtectionController::class, 'dashboard'])->name('bot-protection.dashboard');
        Route::get('/bot-protection/advanced', [BotProtectionController::class, 'advancedView'])->name('bot-protection.advanced');
        Route::get('/analytics/dashboard', [BotProtectionController::class, 'dashboard'])->name('analytics.dashboard');
        Route::get('/analytics/traffic-control', [BotProtectionController::class, 'advancedView'])->name('analytics.traffic-control');
        Route::get('/support-system', [SupportSystemController::class, 'index'])->name('support-system');
        Route::get('/support-system/create', [SupportSystemController::class, 'create'])->name('support-system.create');
        Route::post('/support-system', [SupportSystemController::class, 'store'])->name('support-system.store');
        Route::get('/support-system/{ticket}', [SupportSystemController::class, 'show'])->name('support-system.show');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/security-logs', [SecurityLogsController::class, 'index'])->name('security-logs');
        Route::get('/system-settings', [SystemSettingsController::class, 'index'])->name('system-settings');
        Route::resource('roles', \App\Http\Controllers\Admin\RolesController::class)->except(['show']);
        });

        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/receipts/{payment}', [BillingController::class, 'downloadReceipt'])->name('billing.receipt.download');
        Route::post('/billing', [BillingController::class, 'submit'])->name('billing.submit');
        Route::post('/billing/pay-card', [BillingController::class, 'payWithCard'])->name('billing.pay-card');
        Route::get('/billing/stripe/success', [BillingController::class, 'stripeSuccess'])->name('billing.stripe.success');
        Route::post('/billing/payment-methods', [BillingController::class, 'storePaymentMethod'])->name('billing.payment-methods.store');
        Route::patch('/billing/payment-methods/{paymentMethod}/primary', [BillingController::class, 'setPrimaryPaymentMethod'])->name('billing.payment-methods.primary');
        Route::delete('/billing/payment-methods/{paymentMethod}', [BillingController::class, 'destroyPaymentMethod'])->name('billing.payment-methods.destroy');
        Route::get('/upgrade-plan', fn () => redirect()->route('billing.index'))->name('upgrade-plan');
        Route::post('/upgrade-plan', [BillingController::class, 'submit'])->name('upgrade-plan.submit');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/avatar/{user}', [\App\Http\Controllers\ProfileController::class, 'showAvatar'])->name('profile.avatar.show');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [\App\Http\Controllers\ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [\App\Http\Controllers\ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::post('/profile/timezone/sync', [\App\Http\Controllers\ProfileController::class, 'syncTimezone'])->name('profile.timezone.sync');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/profile/two-factor/enable', [\App\Http\Controllers\TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/profile/two-factor/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/profile/two-factor/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::post('/profile/two-factor/recovery-codes', [\App\Http\Controllers\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery');
    Route::post('/profile/sessions/logout-others', [\App\Http\Controllers\TwoFactorController::class, 'destroyOtherSessions'])->name('two-factor.sessions.destroy-others');
    Route::post('/profile/api-keys', [\App\Http\Controllers\TwoFactorController::class, 'storeApiKey'])->name('two-factor.api-keys.store');
    Route::delete('/profile/api-keys/{apiKey}', [\App\Http\Controllers\TwoFactorController::class, 'destroyApiKey'])->name('two-factor.api-keys.destroy');
    Route::post('/impersonate/stop', [SuperAdminUsersController::class, 'stopImpersonating'])->name('impersonate.stop');

    // Onboarding (post email verification, pre dashboard)
        Route::get('/onboarding/plan', [OnboardingController::class, 'plans'])->name('onboarding.plan');
    Route::post('/onboarding/plan/start-trial', [OnboardingController::class, 'startTrial'])->name('onboarding.start-trial');
    Route::get('/onboarding/payment', [OnboardingController::class, 'payment'])->name('onboarding.payment');
    Route::post('/onboarding/payment', [OnboardingController::class, 'storePayment'])->name('onboarding.payment.store');
    Route::post('/onboarding/payment/stripe-confirm', [OnboardingController::class, 'confirmStripePayment'])->name('onboarding.payment.stripe-confirm');
    Route::get('/onboarding/stripe/success', [OnboardingController::class, 'stripeSuccess'])->name('onboarding.stripe.success');
});




Route::middleware('auth')->group(function () {
    Route::get('/overview/summary', [DashboardController::class, 'summary']);
    Route::get('/overview/search', [DashboardController::class, 'search']);
    Route::get('/insights', [DashboardController::class, 'insights']);
    Route::get('/analytics/trends', [DashboardController::class, 'trends']);
    Route::get('/analytics/threats', [DashboardController::class, 'threats']);
    Route::get('/notifications', [DashboardController::class, 'notifications']);
    Route::get('/dashboard/live-snapshot', [DashboardController::class, 'liveSnapshot']);
    Route::get('/dashboard/live-stream', [DashboardController::class, 'liveStream']);
    Route::get('/domains/performance', [DashboardController::class, 'domainPerformance']);
    Route::get('/campaigns', [DashboardController::class, 'campaigns']);
    Route::get('/campaigns/performance', [DashboardController::class, 'campaignPerformance']);
    Route::put('/user/preferences', [DashboardController::class, 'preferences']);

    Route::get('/domains', [DomainManagementController::class, 'list']);
    Route::post('/domains', [DomainManagementController::class, 'store']);
    Route::post('/domains/validate', [DomainManagementController::class, 'validateDomain']);
    Route::post('/domains/bulk-add', [DomainManagementController::class, 'bulkAdd']);
    Route::put('/domains/{domain}', [DomainManagementController::class, 'update']);
    Route::delete('/domains/{domain}', [DomainManagementController::class, 'destroy']);
    Route::put('/domains/{domain}/status', [DomainManagementController::class, 'updateStatus']);
    Route::get('/domains/{domain}/tracking-script', [DomainManagementController::class, 'trackingScript']);
    Route::get('/domains/{domain}/api-key', [DomainManagementController::class, 'apiKey']);
    Route::put('/domains/{domain}/gtm', [DomainManagementController::class, 'updateGtm']);
    Route::put('/domains/{domain}/tracking-params', [DomainManagementController::class, 'updateTrackingParams']);
    Route::post('/domains/{domain}/email-developer', [DomainManagementController::class, 'emailDeveloper']);
    Route::post('/domains/{domain}/verify-wordpress', [DomainManagementController::class, 'verifyWordpress']);
    Route::post('/domains/{domain}/check-connectivity', [DomainManagementController::class, 'checkTagConnectivity']);
    Route::get('/domains/{domain}/google-ads/pick-accounts', [IntegrationsController::class, 'pickAccountsJson']);
    Route::post('/domains/{domain}/google-ads/link', [IntegrationsController::class, 'linkDomainPaidAccount']);
    Route::get('/tracking/wordpress-plugin', [DomainManagementController::class, 'wordpressPlugin']);

    Route::get('/detection/{domain}/rules', [PaidMarketingController::class, 'getRulesApi']);
    Route::put('/detection/{domain}/rules', [PaidMarketingController::class, 'updateRulesApi']);
    Route::put('/detection/{domain}/exclusions', [PaidMarketingController::class, 'updateExclusionsApi']);
    Route::put('/detection/{domain}/marketing-rules', [PaidMarketingController::class, 'updateMarketingRulesApi']);

    Route::get('/bot-protection/page-analytics', [BotProtectionController::class, 'pageAnalytics']);
    Route::get('/bot-protection/page-analytics/export', [BotProtectionController::class, 'pageAnalyticsExport']);
    Route::get('/bot-protection/traffic-control/sessions', [BotProtectionController::class, 'trafficControlSessions']);
    Route::get('/bot-protection/summary', [BotProtectionController::class, 'summary']);
    Route::get('/bot-protection/traffic-breakdown', [BotProtectionController::class, 'trafficBreakdown']);
    Route::get('/bot-protection/invalid-traffic-trends', [BotProtectionController::class, 'invalidTrafficTrends']);
    Route::get('/bot-protection/threat-groups', [BotProtectionController::class, 'threatGroups']);
    Route::get('/bot-protection/invalid-breakdown', [BotProtectionController::class, 'invalidBreakdown']);
    Route::get('/bot-protection/countries', [BotProtectionController::class, 'countries']);
    Route::get('/bot-protection/country-ips', [BotProtectionController::class, 'countryIps']);
    Route::get('/bot-protection/domains-summary', [BotProtectionController::class, 'domainsSummary']);
    Route::get('/bot-protection/bot-stats', [BotProtectionController::class, 'botStats']);
    Route::get('/bot-protection/visits', [BotProtectionController::class, 'visits']);
    Route::get('/bot-protection/export.csv', [BotProtectionController::class, 'exportCsv'])->name('bot-protection.export');

    Route::get('/paid-marketing/summary', [PaidAdvertisingDashboardController::class, 'summary']);
    Route::get('/paid-marketing/watermark', [PaidAdvertisingDashboardController::class, 'watermark']);
    Route::get('/paid-marketing/trends', [PaidAdvertisingDashboardController::class, 'trends']);
    Route::get('/paid-marketing/blocking-activity', [PaidAdvertisingDashboardController::class, 'blockingActivity']);
    Route::get('/paid-marketing/campaigns', [PaidAdvertisingDashboardController::class, 'campaigns']);
    Route::get('/paid-marketing/keywords', [PaidAdvertisingDashboardController::class, 'keywords']);
    Route::get('/paid-marketing/countries', [PaidAdvertisingDashboardController::class, 'countries']);
    Route::get('/paid-marketing/country-ips', [PaidAdvertisingDashboardController::class, 'countryIps']);
    Route::get('/paid-marketing/ips', [PaidAdvertisingDashboardController::class, 'ips']);
    Route::get('/paid-marketing/ip-clicks', [PaidAdvertisingDashboardController::class, 'ipClicks']);
    Route::get('/paid-marketing/ips/export.csv', [PaidAdvertisingDashboardController::class, 'exportIpsCsv'])->name('paid-marketing.ips.export');
    Route::get('/paid-marketing/heatmap', [PaidAdvertisingDashboardController::class, 'heatmap']);

    Route::get('/integrations/connected', [IntegrationsController::class, 'connectedJson']);
    Route::get('/integrations/status', [IntegrationsController::class, 'statusJson']);
    Route::get('/integrations/logs', [IntegrationsController::class, 'logsJson']);
    Route::post('/integrations/google/{connection}/test', [IntegrationsController::class, 'testConnection']);
    Route::get('/integrations/all', [IntegrationsController::class, 'allJson']);
    Route::get('/integrations/google/oauth-url', [IntegrationsController::class, 'googleOauthUrl']);
    Route::get('/integrations/google/pixel-guard', [IntegrationsController::class, 'pixelGuardGet']);
    Route::put('/integrations/google/pixel-guard', [IntegrationsController::class, 'pixelGuardSave']);
    Route::post('/integrations/google/audience-exclusion', [IntegrationsController::class, 'audienceExclusionSave']);
    Route::get('/integrations/direct-ads', [IntegrationsController::class, 'directAdsList']);
    Route::post('/integrations/direct-ads', [IntegrationsController::class, 'directAdsStore']);
    Route::delete('/integrations/direct-ads/{integration}', [IntegrationsController::class, 'directAdsDestroy'])->name('integrations.direct-ads.destroy');
    Route::delete('/integrations/gtm/{domain}', [IntegrationsController::class, 'destroyGtm'])->name('integrations.destroy-gtm');
});

Route::middleware(['auth', 'admin', 'portal-product'])
    ->prefix('api/admin')
    ->name('api.admin.')
    ->group(function () {
        Route::get('/traffic', [AdminOperationsApiController::class, 'traffic'])->name('traffic.index');
        Route::get('/traffic/stats', [AdminOperationsApiController::class, 'trafficStats'])->name('traffic.stats');
        Route::post('/traffic/block-ip', [AdminOperationsApiController::class, 'blockIp'])->name('traffic.block-ip');
        Route::get('/traffic/blocklist', [AdminOperationsApiController::class, 'blocklist'])->name('traffic.blocklist');

        Route::get('/jobs', [AdminOperationsApiController::class, 'jobs'])->name('jobs.index');
        Route::post('/jobs/{id}/run', [AdminOperationsApiController::class, 'runJob'])->name('jobs.run');
        Route::patch('/jobs/{id}/schedule', [AdminOperationsApiController::class, 'scheduleJob'])->name('jobs.schedule');
        Route::get('/jobs/{id}/history', [AdminOperationsApiController::class, 'jobHistory'])->name('jobs.history');
        Route::post('/jobs/retry-failed', [AdminOperationsApiController::class, 'retryFailedJobs'])->name('jobs.retry-failed');

        Route::get('/integrations', [AdminOperationsApiController::class, 'integrations'])->name('integrations.index');
        Route::put('/integrations/{name}', [AdminOperationsApiController::class, 'updateIntegration'])->name('integrations.update');
        Route::post('/integrations/{name}/rotate', [AdminOperationsApiController::class, 'rotateIntegration'])->name('integrations.rotate');
        Route::post('/integrations/{name}/test', [AdminOperationsApiController::class, 'testIntegration'])->name('integrations.test');

        Route::get('/webhooks', [AdminOperationsApiController::class, 'webhooks'])->name('webhooks.index');
        Route::post('/webhooks', [AdminOperationsApiController::class, 'storeWebhook'])->name('webhooks.store');

        Route::get('/tickets', [AdminOperationsApiController::class, 'tickets'])->name('tickets.index');
        Route::get('/tickets/{id}', [AdminOperationsApiController::class, 'ticket'])->name('tickets.show');
        Route::post('/tickets/{id}/reply', [AdminOperationsApiController::class, 'replyTicket'])->name('tickets.reply');
        Route::patch('/tickets/{id}/assign', [AdminOperationsApiController::class, 'assignTicket'])->name('tickets.assign');
        Route::post('/tickets/{id}/escalate', [AdminOperationsApiController::class, 'escalateTicket'])->name('tickets.escalate');
        Route::post('/tickets/{id}/close', [AdminOperationsApiController::class, 'closeTicket'])->name('tickets.close');

        Route::post('/guidance/ask', [\App\Http\Controllers\Api\GuidanceChatController::class, 'ask'])->name('guidance.ask');
        Route::post('/guidance/ticket', [\App\Http\Controllers\Api\GuidanceChatController::class, 'createTicket'])->name('guidance.ticket');
    });


/*
| Fallback for branding assets when the web server does not map /public correctly.
*/
Route::get('/images/{filename}', function (string $filename) {
    $safe = basename($filename);
    $path = public_path('images/' . $safe);
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => match (strtolower(pathinfo($safe, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        },
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->where('filename', '[a-zA-Z0-9._-]+');

require __DIR__.'/auth.php';
