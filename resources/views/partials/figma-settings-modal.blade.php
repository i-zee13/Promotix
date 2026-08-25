{{-- Clickronix Settings modal — 9-tab panel --}}
@php
    use Illuminate\Support\Facades\Schema;

    $settingsUser = auth()->user();
    $settingsPrefs = (array) ($settingsUser?->ui_preferences ?? []);
    $settingsNotify = (array) ($settingsPrefs['notifications'] ?? []);
    $settingsSafety = (array) ($settingsPrefs['safety'] ?? []);
    $settingsDataDisplay = (array) ($settingsPrefs['data_display'] ?? []);
    $settingsOther = (array) ($settingsPrefs['other_options'] ?? []);
    $settingsDetection = (array) ($settingsNotify['detection'] ?? []);
    $settingsSystem = (array) ($settingsNotify['system'] ?? []);

    $settingsTz = 'UTC';
    try {
        $settingsTz = $settingsUser ? \App\Support\UserTimezone::forUser($settingsUser) : 'UTC';
    } catch (\Throwable $e) {
        $settingsTz = (string) ($settingsUser?->timezone ?: 'UTC');
    }

    $settingsAppearance = (string) ($settingsPrefs['appearance'] ?? (($settingsPrefs['dark_mode'] ?? true) ? 'dark' : 'light'));
    if (! in_array($settingsAppearance, ['dark', 'light', 'system'], true)) {
        $settingsAppearance = 'dark';
    }

    $settingsDomains = collect();
    $domainStats = ['total' => 0, 'active' => 0, 'remaining' => 0, 'limit' => 0];
    try {
        if ($settingsUser && class_exists(\App\Models\Domain::class) && Schema::hasTable('domains')) {
            $settingsDomains = $settingsUser->domains()->latest('id')->limit(8)->get(['id', 'hostname', 'status', 'tag_connected']);
            $used = method_exists($settingsUser, 'domainsUsed') ? (int) $settingsUser->domainsUsed() : $settingsDomains->count();
            $limit = method_exists($settingsUser, 'domainLimit') ? $settingsUser->domainLimit() : 50;
            $limitNum = is_finite((float) $limit) ? (int) $limit : 999;
            $active = (int) $settingsUser->domains()->where('tag_connected', true)->count();
            $domainStats = [
                'total' => $used,
                'active' => $active,
                'remaining' => max(0, $limitNum - $used),
                'limit' => $limitNum,
            ];
        }
    } catch (\Throwable $e) {
        $settingsDomains = collect();
    }

    $settingsSubscription = null;
    $settingsPlan = null;
    try {
        if ($settingsUser && method_exists($settingsUser, 'activeSubscription')) {
            $settingsSubscription = $settingsUser->activeSubscription();
            $settingsPlan = $settingsSubscription?->plan ?? (method_exists($settingsUser, 'currentPlan') ? $settingsUser->currentPlan() : null);
        }
    } catch (\Throwable $e) {
        $settingsSubscription = null;
        $settingsPlan = null;
    }

    $invoices = collect();
    try {
        if ($settingsUser && class_exists(\App\Models\Payment::class) && Schema::hasTable('payments')) {
            $invoices = \App\Models\Payment::query()
                ->where('user_id', $settingsUser->id)
                ->latest('id')
                ->limit(5)
                ->get();
        }
    } catch (\Throwable $e) {
        $invoices = collect();
    }

    $paymentMethod = null;
    try {
        if ($settingsUser && class_exists(\App\Models\PaymentMethod::class) && Schema::hasTable('payment_methods')) {
            $paymentMethod = \App\Models\PaymentMethod::query()
                ->where('user_id', $settingsUser->id)
                ->where('is_primary', true)
                ->first()
                ?? \App\Models\PaymentMethod::query()->where('user_id', $settingsUser->id)->latest('id')->first();
        }
    } catch (\Throwable $e) {
        $paymentMethod = null;
    }

    $twoFactorEnabled = false;
    try {
        $twoFactorEnabled = $settingsUser && method_exists($settingsUser, 'hasTwoFactorEnabled')
            ? (bool) $settingsUser->hasTwoFactorEnabled()
            : false;
    } catch (\Throwable $e) {
        $twoFactorEnabled = false;
    }

    $settingsSecurityTab = (string) old('settings_security_tab', session('settings.security_tab', 'two_factor'));
    if (! in_array($settingsSecurityTab, ['two_factor', 'password', 'sessions', 'api_keys', 'login_alerts'], true)) {
        $settingsSecurityTab = 'two_factor';
    }
    $settingsSecurityAutoOpen = (bool) old('settings_security_tab')
        || (bool) session('settings.security_tab')
        || $errors->updatePassword->isNotEmpty()
        || $errors->has('code');

    $settingsContacts = array_values(array_map(function ($row) {
        $row = (array) $row;
        $perms = (array) ($row['permissions'] ?? []);

        return [
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'role' => (string) ($row['role'] ?? 'security'),
            'permissions' => [
                'alerts' => (bool) ($perms['alerts'] ?? in_array('alerts', $perms, true)),
                'approve' => (bool) ($perms['approve'] ?? in_array('approve', $perms, true)),
                'billing' => (bool) ($perms['billing'] ?? in_array('billing', $perms, true)),
                'domains' => (bool) ($perms['domains'] ?? in_array('domains', $perms, true)),
            ],
        ];
    }, array_slice((array) ($settingsPrefs['trusted_contacts'] ?? []), 0, 5)));

    if (count($settingsContacts) === 0) {
        $settingsContacts[] = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'role' => 'security',
            'permissions' => ['alerts' => true, 'approve' => false, 'billing' => false, 'domains' => false],
        ];
    }

    $commonTimezones = [
        'UTC',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Toronto',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'Asia/Dubai',
        'Asia/Karachi',
        'Asia/Kolkata',
        'Asia/Singapore',
        'Asia/Tokyo',
        'Australia/Sydney',
    ];
    if ($settingsTz && ! in_array($settingsTz, $commonTimezones, true)) {
        array_unshift($commonTimezones, $settingsTz);
    }

    $reportsUrl = \Illuminate\Support\Facades\Route::has('reports.index') ? route('reports.index') : '#';
    $comprehensiveReportUrl = \Illuminate\Support\Facades\Route::has('paid-marketing.detailed-export')
        ? route('paid-marketing.detailed-export')
        : $reportsUrl;
    $reportDownloadUrls = [
        'dashboard_ips' => \Illuminate\Support\Facades\Route::has('paid-marketing.ips.export')
            ? route('paid-marketing.ips.export')
            : $comprehensiveReportUrl,
        'advanced' => $comprehensiveReportUrl,
        'bot' => \Illuminate\Support\Facades\Route::has('bot-protection.export')
            ? route('bot-protection.export')
            : $comprehensiveReportUrl,
        'bot_advanced' => \Illuminate\Support\Facades\Route::has('bot-protection.export')
            ? route('bot-protection.export')
            : $comprehensiveReportUrl,
    ];
    $billingUrl = \Illuminate\Support\Facades\Route::has('billing.index') ? route('billing.index') : '#';
    $domainsUrl = \Illuminate\Support\Facades\Route::has('domains.index') ? route('domains.index') : '#';
    $domainsCreateUrl = \Illuminate\Support\Facades\Route::has('domains.create')
        ? route('domains.create')
        : $domainsUrl;
    $detectionUrl = \Illuminate\Support\Facades\Route::has('paid-marketing.detection-settings')
        ? route('paid-marketing.detection-settings')
        : '#';
    $profileUrl = \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : '#';
    $profileUpdateUrl = \Illuminate\Support\Facades\Route::has('profile.update') ? route('profile.update') : '#';
    $avatarUpdateUrl = \Illuminate\Support\Facades\Route::has('profile.avatar.update') ? route('profile.avatar.update') : '#';
    $avatarDestroyUrl = \Illuminate\Support\Facades\Route::has('profile.avatar.destroy') ? route('profile.avatar.destroy') : '#';
    $usersUrl = \Illuminate\Support\Facades\Route::has('users')
        ? route('users')
        : (\Illuminate\Support\Facades\Route::has('users.index') ? route('users.index') : $profileUrl);
    $teamOwner = $settingsUser;
    $teamMembers = collect();
    if ($settingsUser && \Illuminate\Support\Facades\Schema::hasColumn('users', 'team_owner_id')) {
        $teamOwner = $settingsUser->team_owner_id
            ? \App\Models\User::query()->find($settingsUser->team_owner_id) ?? $settingsUser
            : $settingsUser;
        $teamMembers = \App\Models\User::query()
            ->with('role')
            ->where('team_owner_id', $teamOwner->id)
            ->orderBy('name')
            ->get();
    }
    $receiptRouteExists = \Illuminate\Support\Facades\Route::has('billing.receipt.download');

    $settingsTabs = [
        ['id' => 'general', 'label' => 'General', 'icon' => 'settings-general'],
        ['id' => 'notifications', 'label' => 'Notifications', 'icon' => 'settings-notifications'],
        ['id' => 'reports', 'label' => 'Data Reports', 'icon' => 'settings-data-reports'],
        ['id' => 'billing', 'label' => 'Billing', 'icon' => 'settings-billing'],
        ['id' => 'domains', 'label' => 'Domains', 'icon' => 'settings-domains'],
        ['id' => 'safety', 'label' => 'Safety', 'icon' => 'settings-safety'],
        ['id' => 'security', 'label' => 'Security & Login', 'icon' => 'settings-security-login'],
        ['id' => 'contacts', 'label' => 'Trusted Contacts', 'icon' => 'settings-trusted-contacts'],
        ['id' => 'account', 'label' => 'Account', 'icon' => 'settings-account'],
    ];
    $settingsIconSvg = static function (string $name): string {
        $path = public_path('images/settings-icons/'.$name.'.svg');
        if (! is_file($path)) {
            return '';
        }
        $svg = (string) file_get_contents($path);
        // Ensure icons inherit tab text color (active/inactive).
        $svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg, 2) ?? $svg;

        return $svg;
    };
@endphp

<div
    id="promotix-settings-modal"
    class="pmx-settings"
    x-data="promotixSettingsModal(@js([
        'appearance' => $settingsAppearance,
        'language' => (string) ($settingsPrefs['language'] ?? 'en'),
        'timezone' => $settingsTz,
        'default_dashboard' => (string) ($settingsPrefs['default_dashboard'] ?? 'overview'),
        'data_display' => [
            'show_risk_scores' => (bool) ($settingsDataDisplay['show_risk_scores'] ?? true),
            'show_technical_ip' => (bool) ($settingsDataDisplay['show_technical_ip'] ?? true),
            'show_advanced_columns' => (bool) ($settingsDataDisplay['show_advanced_columns'] ?? false),
        ],
        'other_options' => [
            'auto_refresh' => (bool) ($settingsOther['auto_refresh'] ?? true),
            'dashboard_tips' => (bool) ($settingsOther['dashboard_tips'] ?? true),
            'alert_sound' => (bool) ($settingsOther['alert_sound'] ?? false),
        ],
        'reportDownloadUrl' => $comprehensiveReportUrl,
        'reportUrls' => $reportDownloadUrls,
        'avatar' => [
            'url' => $settingsUser?->avatarUrl(),
            'initial' => $settingsUser?->avatarInitial() ?? '?',
            'has_custom' => filled($settingsUser?->avatar_path),
            'update_url' => $avatarUpdateUrl,
            'destroy_url' => $avatarDestroyUrl,
        ],
        'team' => [
            'owner' => [
                'name' => $teamOwner?->name ?? 'Workspace owner',
                'email' => $teamOwner?->email ?? '',
                'role' => 'Owner',
                'initial' => $teamOwner?->avatarInitial() ?? '?',
            ],
            'members' => $teamMembers->map(fn ($member) => [
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role?->name ?? 'Team member',
                'initial' => $member->avatarInitial(),
            ])->values(),
        ],
        'notifications' => [
            'email' => (bool) ($settingsNotify['email'] ?? $settingsNotify['email_alerts'] ?? true),
            'sms' => (bool) ($settingsNotify['sms'] ?? false),
            'push' => (bool) ($settingsNotify['push'] ?? true),
            'email_alerts' => (bool) ($settingsNotify['email_alerts'] ?? $settingsNotify['email'] ?? true),
            'product_updates' => (bool) ($settingsNotify['product_updates'] ?? true),
            'weekly_digest' => (bool) ($settingsNotify['weekly_digest'] ?? false),
            'monthly_report_email' => (bool) ($settingsNotify['monthly_report_email'] ?? false),
            'invalid_clicks_threshold' => (int) ($settingsNotify['invalid_clicks_threshold'] ?? 50),
            'risk_score_threshold' => (int) ($settingsNotify['risk_score_threshold'] ?? 80),
            'detection' => [
                'vpn' => (bool) ($settingsDetection['vpn'] ?? true),
                'proxy' => (bool) ($settingsDetection['proxy'] ?? true),
                'datacenter' => (bool) ($settingsDetection['datacenter'] ?? true),
                'bot' => (bool) ($settingsDetection['bot'] ?? true),
                'abnormal' => (bool) ($settingsDetection['abnormal'] ?? true),
            ],
            'system' => [
                'tracking_disconnected' => (bool) ($settingsSystem['tracking_disconnected'] ?? true),
                'google_disconnected' => (bool) ($settingsSystem['google_disconnected'] ?? true),
                'api_failure' => (bool) ($settingsSystem['api_failure'] ?? true),
                'domain_offline' => (bool) ($settingsSystem['domain_offline'] ?? true),
            ],
        ],
        'safety' => [
            'invalid_traffic' => (bool) ($settingsSafety['invalid_traffic'] ?? true),
            'bot_protection' => (bool) ($settingsSafety['bot_protection'] ?? true),
            'session_recording' => (bool) ($settingsSafety['session_recording'] ?? false),
            'captcha' => (bool) ($settingsSafety['captcha'] ?? false),
            'mask_passwords' => (bool) ($settingsSafety['mask_passwords'] ?? true),
            'mask_payment' => (bool) ($settingsSafety['mask_payment'] ?? true),
            'cookie_consent' => (bool) ($settingsSafety['cookie_consent'] ?? true),
            'retention_days' => (int) ($settingsSafety['retention_days'] ?? 90),
            'gdpr' => (bool) ($settingsSafety['gdpr'] ?? true),
            'ccpa' => (bool) ($settingsSafety['ccpa'] ?? false),
        ],
        'login_alerts' => (bool) ($settingsPrefs['login_alerts'] ?? true),
        'security_tab' => $settingsSecurityTab,
        'security_auto_open' => $settingsSecurityAutoOpen,
        'trusted_contacts' => $settingsContacts,
        'csrf' => csrf_token(),
        'prefsUrl' => url('/user/preferences'),
    ]))"
    x-init="initModal()"
    x-cloak
    x-show="open"
    x-transition.opacity
    @keydown.escape.window="close()"
    @open-promotix-settings.window="openModal($event.detail?.tab, $event.detail)"
    @close-promotix-settings.window="close()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pmx-settings-title"
>
    <div class="pmx-settings__backdrop" @click="close()"></div>
    <div class="pmx-settings__panel" @click.stop x-transition>
        <header class="pmx-settings__head">
            <h2 id="pmx-settings-title">Clickronix Settings</h2>
            <button type="button" class="pmx-settings__close" @click="close()" aria-label="Close settings">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="pmx-settings__body">
            <nav class="pmx-settings__nav" aria-label="Settings sections">
                @foreach ($settingsTabs as $tab)
                    <button
                        type="button"
                        class="pmx-settings__tab"
                        :class="{ 'is-active': tab === '{{ $tab['id'] }}' }"
                        @click="tab = '{{ $tab['id'] }}'"
                    >
                        <span class="pmx-settings__tab-icon" aria-hidden="true">{!! $settingsIconSvg($tab['icon']) !!}</span>
                        <span>{{ $tab['label'] }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="pmx-settings__content promotix-slim-scroll">
                {{-- GENERAL --}}
                <div x-show="tab === 'general'" x-cloak>
                    <h3 class="pmx-settings__h">General</h3>
                    <p class="pmx-settings__p">Appearance, language, timezone, and dashboard defaults.</p>

                    <div class="pmx-settings__section">
                        <p class="pmx-settings__section-title">Appearance</p>
                        <div class="pmx-settings__appear-grid">
                            <button type="button" class="pmx-settings__appear" :class="{ 'is-active': appearance === 'dark' }" @click="setAppearance('dark')">
                                <span class="pmx-settings__appear-preview pmx-settings__appear-preview--dark"></span>
                                <span>Dark</span>
                            </button>
                            <button type="button" class="pmx-settings__appear" :class="{ 'is-active': appearance === 'light' }" @click="setAppearance('light')">
                                <span class="pmx-settings__appear-preview pmx-settings__appear-preview--light"></span>
                                <span>Light</span>
                            </button>
                            <button type="button" class="pmx-settings__appear" :class="{ 'is-active': appearance === 'system' }" @click="setAppearance('system')">
                                <span class="pmx-settings__appear-preview pmx-settings__appear-preview--system"></span>
                                <span>System</span>
                            </button>
                        </div>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack">
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-lang">Language</label>
                            <select id="pmx-lang" class="pmx-settings__select" x-model="language">
                                <option value="en">English</option>
                                <option value="es">Spanish</option>
                            </select>
                        </div>
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-tz">Timezone</label>
                            <select id="pmx-tz" class="pmx-settings__select" x-model="timezone">
                                @foreach ($commonTimezones as $tz)
                                    <option value="{{ $tz }}">{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-dash">Default Dashboard</label>
                            <select id="pmx-dash" class="pmx-settings__select" x-model="defaultDashboard">
                                <option value="overview">Overview Dashboard</option>
                                <option value="paid">Paid Marketing</option>
                                <option value="bot">Bot Protection</option>
                            </select>
                        </div>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <p class="pmx-settings__section-title">Data Display</p>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="dataDisplay.show_risk_scores"><span>Show risk scores</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="dataDisplay.show_technical_ip"><span>Show technical IP information</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="dataDisplay.show_advanced_columns"><span>Show advanced columns</span></label>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <p class="pmx-settings__section-title">Other Options</p>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Auto refresh dashboard</p>
                                <p class="pmx-settings__hint">Refresh charts and tables automatically</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="otherOptions.auto_refresh"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Show tips on dashboard</p>
                                <p class="pmx-settings__hint">Contextual tips for new features</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="otherOptions.dashboard_tips"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Play sound on alerts</p>
                                <p class="pmx-settings__hint">Audible cue for critical alerts</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="otherOptions.alert_sound"><span></span></label>
                        </div>
                    </div>

                    <div class="pmx-settings__actions">
                        <button type="button" class="pmx-settings__cta" @click="saveGeneral()" :disabled="generalBusy" x-text="generalBusy ? 'Saving…' : 'Save changes'"></button>
                        <p class="pmx-settings__hint" x-text="generalStatus" x-show="generalStatus"></p>
                    </div>
                </div>

                {{-- NOTIFICATIONS --}}
                <div x-show="tab === 'notifications'" x-cloak>
                    <h3 class="pmx-settings__h">Notifications</h3>
                    <p class="pmx-settings__p">Channels, alert thresholds, and detection rules.</p>

                    <div class="pmx-settings__card pmx-settings__stack">
                        <p class="pmx-settings__section-title">Channels</p>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Email</p>
                                <p class="pmx-settings__hint">{{ $settingsUser?->email ?? 'Account email' }}</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="notifications.email"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">SMS</p>
                                <p class="pmx-settings__hint">{{ $settingsUser?->phone ?: 'Add a phone on Account' }}</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="notifications.sms"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Push</p>
                                <p class="pmx-settings__hint">Browser and in-app push alerts</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="notifications.push"><span></span></label>
                        </div>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <p class="pmx-settings__section-title">Alert Rules</p>
                        <div class="pmx-settings__field-row">
                            <label class="pmx-settings__label">Notify when invalid clicks &gt;</label>
                            <input type="number" min="0" max="100000" class="pmx-settings__input pmx-settings__input--sm" x-model.number="notifications.invalid_clicks_threshold">
                        </div>
                        <div class="pmx-settings__field-row">
                            <label class="pmx-settings__label">Notify when risk score &gt;</label>
                            <input type="number" min="0" max="100" class="pmx-settings__input pmx-settings__input--sm" x-model.number="notifications.risk_score_threshold">
                        </div>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <p class="pmx-settings__section-title">Detection Alerts</p>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.detection.vpn"><span>VPN detected</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.detection.proxy"><span>Proxy detected</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.detection.datacenter"><span>Datacenter traffic</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.detection.bot"><span>Bot behavior</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.detection.abnormal"><span>Abnormal rate / repeated clicks</span></label>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <p class="pmx-settings__section-title">System Alerts</p>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.system.tracking_disconnected"><span>Tracking disconnected</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.system.google_disconnected"><span>Google Ads disconnected</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.system.api_failure"><span>API failure / error</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="notifications.system.domain_offline"><span>Domain offline</span></label>
                    </div>

                    <div class="pmx-settings__actions">
                        <button type="button" class="pmx-settings__cta" @click="saveNotifications()" :disabled="notifyBusy" x-text="notifyBusy ? 'Saving…' : 'Save notifications'"></button>
                        <p class="pmx-settings__hint" x-text="notifyStatus" x-show="notifyStatus"></p>
                    </div>
                </div>

                {{-- DATA REPORTS --}}
                <div x-show="tab === 'reports'" x-cloak>
                    <h3 class="pmx-settings__h">Data Reports</h3>
                    <p class="pmx-settings__p">Generate exports for traffic quality, ads, bots, and more.</p>

                    <div class="pmx-settings__range">
                        <button type="button" class="pmx-settings__chip-btn" :class="{ 'is-active': reportRange === 'today' }" @click="setReportRange('today')">Today</button>
                        <button type="button" class="pmx-settings__chip-btn" :class="{ 'is-active': reportRange === 'yesterday' }" @click="setReportRange('yesterday')">Yesterday</button>
                        <button type="button" class="pmx-settings__chip-btn" :class="{ 'is-active': reportRange === '7d' }" @click="setReportRange('7d')">Last 7 Days</button>
                        <button type="button" class="pmx-settings__chip-btn" :class="{ 'is-active': reportRange === '30d' }" @click="setReportRange('30d')">Last 30 Days</button>
                        <button type="button" class="pmx-settings__chip-btn" :class="{ 'is-active': reportRange === 'custom' }" @click="setReportRange('custom')">
                            <svg style="width:13px;height:13px;margin-right:5px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z"/></svg>
                            Custom dates
                        </button>
                    </div>

                    <div class="pmx-settings__card" style="margin-bottom:12px">
                        <div class="pmx-settings__report-controls">
                            <label class="pmx-settings__field">
                                <span class="pmx-settings__label">From</span>
                                <input type="date" class="pmx-settings__input" x-model="reportCustomFrom" @change="reportRange = 'custom'">
                            </label>
                            <label class="pmx-settings__field">
                                <span class="pmx-settings__label">To</span>
                                <input type="date" class="pmx-settings__input" x-model="reportCustomTo" @change="reportRange = 'custom'">
                            </label>
                            <label class="pmx-settings__field">
                                <span class="pmx-settings__label">Report</span>
                                <select class="pmx-settings__select" x-model="reportGroup">
                                    <option value="dashboard_ips">Main dashboard IP stats</option>
                                    <option value="advanced">Advanced View stats</option>
                                    <option value="bot">Analytics Dashboard</option>
                                    <option value="bot_advanced">Analytics Traffic Control</option>
                                </select>
                            </label>
                            <label class="pmx-settings__field">
                                <span class="pmx-settings__label">Format</span>
                                <select class="pmx-settings__select" x-model="reportFormat">
                                    <option value="csv">CSV</option>
                                    <option value="xlsx">XLSX</option>
                                </select>
                            </label>
                            <label class="pmx-settings__check" style="align-self:end;margin-bottom:2px">
                                <input type="checkbox" x-model="monthlyEmailToggle" @change="persistMonthlyEmailToggle()">
                                <span>Email me a monthly report</span>
                            </label>
                            <button
                                type="button"
                                class="pmx-settings__cta pmx-settings__report-download"
                                @click="downloadReport()"
                                :disabled="reportBusy"
                                x-text="reportBusy ? 'Preparing…' : ('Download ' + (reportFormat === 'xlsx' ? 'XLSX' : 'CSV'))"
                            ></button>
                        </div>
                    </div>

                    <p class="pmx-settings__hint" x-show="reportStatus" x-text="reportStatus" style="margin-top:0;margin-bottom:8px"></p>
                    <p class="pmx-settings__hint" style="margin-top:4px">Pick a date range, then download stats for the dashboard, Advanced View, Analytics Dashboard, or Analytics Traffic Control.</p>

                </div>

                {{-- BILLING --}}
                <div x-show="tab === 'billing'" x-cloak>
                    <h3 class="pmx-settings__h">Billing</h3>
                    <p class="pmx-settings__p">Plan, payment method, and recent invoices.</p>

                    <div class="pmx-settings__card">
                        <div class="pmx-settings__row" style="align-items:flex-start">
                            <div>
                                <p class="pmx-settings__label">{{ $settingsPlan?->name ?? 'No active plan' }}</p>
                                @if ($settingsSubscription)
                                    <p class="pmx-settings__hint">
                                        @if ($settingsSubscription->amount_cents)
                                            ${{ number_format(((int) $settingsSubscription->amount_cents) / 100, 2) }}
                                            / {{ $settingsSubscription->billing_interval ?: 'month' }}
                                        @elseif ($settingsPlan?->price_cents ?? null)
                                            ${{ number_format(((int) $settingsPlan->price_cents) / 100, 2) }}
                                        @else
                                            See billing for pricing
                                        @endif
                                        · Status: {{ ucfirst((string) $settingsSubscription->status) }}
                                    </p>
                                    @if ($settingsSubscription->current_period_ends_at)
                                        <p class="pmx-settings__hint">Next billing: {{ $settingsSubscription->current_period_ends_at->format('M j, Y') }}</p>
                                    @endif
                                @else
                                    <p class="pmx-settings__hint">Subscribe to unlock protection limits and billing history.</p>
                                @endif
                            </div>
                            <span class="pmx-settings__badge {{ ($settingsSubscription?->status ?? '') === 'active' || ($settingsSubscription?->status ?? '') === 'trialing' ? 'is-ok' : '' }}">
                                {{ $settingsSubscription ? ucfirst((string) $settingsSubscription->status) : 'Inactive' }}
                            </span>
                        </div>
                        @if (! $settingsUser || $settingsUser->canAccess('upgrade-plan'))
                            <div class="pmx-settings__actions" style="margin-top:8px">
                                <a href="{{ $billingUrl }}" class="pmx-settings__cta">Upgrade</a>
                                <a href="{{ $billingUrl }}" class="pmx-settings__btn pmx-settings__btn--muted">Cancel</a>
                            </div>
                        @endif
                    </div>

                    <div class="pmx-settings__card" style="margin-top:12px">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Payment method</p>
                                @if ($paymentMethod)
                                    <p class="pmx-settings__hint">
                                        {{ method_exists($paymentMethod, 'maskedLabel') ? $paymentMethod->maskedLabel() : (($paymentMethod->brand ?: 'Card').' •••• '.($paymentMethod->last_four ?: '****')) }}
                                        @if ($paymentMethod->exp_month && $paymentMethod->exp_year)
                                            · Exp {{ str_pad((string) $paymentMethod->exp_month, 2, '0', STR_PAD_LEFT) }}/{{ $paymentMethod->exp_year }}
                                        @endif
                                    </p>
                                @else
                                    <p class="pmx-settings__hint">No payment method on file.</p>
                                @endif
                            </div>
                            <a href="{{ $billingUrl }}" class="pmx-settings__btn">Update</a>
                        </div>
                    </div>

                    <div class="pmx-settings__card" style="margin-top:12px; padding:0; overflow:hidden">
                        <div class="pmx-settings__table-wrap">
                            <table class="pmx-settings__table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($invoices as $inv)
                                        <tr>
                                            <td>{{ $inv->invoice_number ?: ('#'.$inv->id) }}</td>
                                            <td>{{ optional($inv->paid_at ?? $inv->created_at)->format('M j, Y') }}</td>
                                            <td>${{ number_format(((int) ($inv->amount_cents ?? 0)) / 100, 2) }}</td>
                                            <td><span class="pmx-settings__badge is-ok">{{ ucfirst((string) ($inv->status ?: 'paid')) }}</span></td>
                                            <td>
                                                @if ($receiptRouteExists)
                                                    <a href="{{ route('billing.receipt.download', $inv) }}" class="pmx-settings__btn">Download</a>
                                                @else
                                                    <a href="{{ $billingUrl }}" class="pmx-settings__btn">View</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="pmx-settings__hint" style="padding:14px">No invoices yet. <a href="{{ $billingUrl }}" class="pmx-settings__link">Open Billing</a></td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- DOMAINS --}}
                <div x-show="tab === 'domains'" x-cloak>
                    <h3 class="pmx-settings__h">Domains</h3>
                    <p class="pmx-settings__p">Tracked domains, connection status, and remaining slots.</p>

                    <div class="pmx-settings__stats">
                        <div class="pmx-settings__stat">
                            <p class="pmx-settings__stat-val">{{ $domainStats['total'] }}</p>
                            <p class="pmx-settings__stat-lbl">Total</p>
                        </div>
                        <div class="pmx-settings__stat">
                            <p class="pmx-settings__stat-val">{{ $domainStats['active'] }}</p>
                            <p class="pmx-settings__stat-lbl">Active</p>
                        </div>
                        <div class="pmx-settings__stat">
                            <p class="pmx-settings__stat-val">{{ $domainStats['remaining'] }}</p>
                            <p class="pmx-settings__stat-lbl">Remaining slots</p>
                        </div>
                        <div class="pmx-settings__stat">
                            <p class="pmx-settings__stat-val">{{ $domainStats['limit'] }}</p>
                            <p class="pmx-settings__stat-lbl">Plan limit</p>
                        </div>
                    </div>

                    <div class="pmx-settings__card" style="padding:0; overflow:hidden; margin-top:12px">
                        <div class="pmx-settings__table-wrap">
                            <table class="pmx-settings__table">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>Status</th>
                                        <th>Tracking</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($settingsDomains as $domain)
                                        <tr>
                                            <td>{{ $domain->hostname }}</td>
                                            <td><span class="pmx-settings__badge">{{ ucfirst((string) ($domain->status ?: 'active')) }}</span></td>
                                            <td>
                                                <span class="pmx-settings__badge {{ $domain->tag_connected ? 'is-ok' : '' }}">
                                                    {{ $domain->tag_connected ? 'Connected' : 'Pending' }}
                                                </span>
                                            </td>
                                            <td>
                                                @if (! $settingsUser || $settingsUser->canAccess('domain-management'))
                                                    <a href="{{ $domainsUrl }}" class="pmx-settings__btn">Manage</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="pmx-settings__hint" style="padding:14px">No domains yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if (! $settingsUser || $settingsUser->canAccess('domain-management'))
                        <div class="pmx-settings__actions">
                            <a href="{{ $domainsCreateUrl }}" class="pmx-settings__cta">Add Domain</a>
                            <a href="{{ $domainsUrl }}" class="pmx-settings__btn">Open Domains</a>
                        </div>
                    @endif
                </div>

                {{-- SAFETY --}}
                <div x-show="tab === 'safety'" x-cloak>
                    <h3 class="pmx-settings__h">Safety</h3>
                    <p class="pmx-settings__p">Traffic protection, privacy masking, and retention.</p>

                    <div class="pmx-settings__card pmx-settings__stack">
                        <p class="pmx-settings__section-title">Traffic Protection</p>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Invalid traffic protection</p>
                                <p class="pmx-settings__hint">Block and flag low-quality clicks</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="safety.invalid_traffic"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Bot protection</p>
                                <p class="pmx-settings__hint">Detect automated and headless traffic</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="safety.bot_protection"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Session recording</p>
                                <p class="pmx-settings__hint">Capture sessions for review</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="safety.session_recording"><span></span></label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">CAPTCHA / challenge</p>
                                <p class="pmx-settings__hint">Challenge suspicious visitors</p>
                            </div>
                            <label class="pmx-settings__switch"><input type="checkbox" x-model="safety.captcha"><span></span></label>
                        </div>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <p class="pmx-settings__section-title">Privacy</p>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="safety.mask_passwords"><span>Mask passwords</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="safety.mask_payment"><span>Mask payment fields</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="safety.cookie_consent"><span>Require cookie consent</span></label>
                    </div>

                    <div class="pmx-settings__card pmx-settings__stack" style="margin-top:12px">
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-retention">Data retention</label>
                            <select id="pmx-retention" class="pmx-settings__select" x-model.number="safety.retention_days">
                                <option value="30">30 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                                <option value="365">365 days</option>
                            </select>
                        </div>
                        <p class="pmx-settings__section-title" style="margin-top:8px">Consent</p>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="safety.gdpr"><span>GDPR consent enabled</span></label>
                        <label class="pmx-settings__check"><input type="checkbox" x-model="safety.ccpa"><span>CCPA consent enabled</span></label>
                    </div>

                    <div class="pmx-settings__actions">
                        <button type="button" class="pmx-settings__cta" @click="saveSafety()" :disabled="safetyBusy" x-text="safetyBusy ? 'Saving…' : 'Save safety'"></button>
                        @if (! $settingsUser || $settingsUser->canAccess('paid-marketing-detection-settings'))
                            <a href="{{ $detectionUrl }}" class="pmx-settings__btn">Detection settings</a>
                        @endif
                        <p class="pmx-settings__hint" x-text="safetyStatus" x-show="safetyStatus"></p>
                    </div>
                </div>

                {{-- SECURITY --}}
                <div x-show="tab === 'security'" x-cloak>
                    <h3 class="pmx-settings__h">Security & Login</h3>
                    <p class="pmx-settings__p">2FA, password, sessions, API keys, and login alerts.</p>
                    @include('partials.settings-security-tabs')
                </div>

                {{-- TRUSTED CONTACTS --}}
                <div x-show="tab === 'contacts'" x-cloak>
                    <h3 class="pmx-settings__h">Trusted Contacts</h3>
                    <p class="pmx-settings__p">Recovery and escalation contacts (up to 5).</p>

                    <div class="pmx-settings__card pmx-settings__stack">
                        <template x-for="(contact, idx) in trustedContacts" :key="'tc-' + idx">
                            <div class="pmx-settings__contact">
                                <div class="pmx-settings__contact-head">
                                    <p class="pmx-settings__label" x-text="'Contact ' + (idx + 1)"></p>
                                    <button
                                        type="button"
                                        class="pmx-settings__btn pmx-settings__btn--muted"
                                        x-show="trustedContacts.length > 1"
                                        @click="trustedContacts.splice(idx, 1)"
                                    >Remove</button>
                                </div>
                                <div class="pmx-settings__contact-grid">
                                    <input type="text" class="pmx-settings__input" placeholder="Name" x-model="contact.name">
                                    <input type="email" class="pmx-settings__input" placeholder="Email" x-model="contact.email">
                                    <select class="pmx-settings__select" x-model="contact.role">
                                        <option value="owner">Owner</option>
                                        <option value="admin">Admin</option>
                                        <option value="security">Security</option>
                                        <option value="billing">Billing</option>
                                    </select>
                                    <input type="text" class="pmx-settings__input" placeholder="Phone" x-model="contact.phone">
                                </div>
                                <div class="pmx-settings__perms">
                                    <label class="pmx-settings__check"><input type="checkbox" x-model="contact.permissions.alerts"><span>Alerts</span></label>
                                    <label class="pmx-settings__check"><input type="checkbox" x-model="contact.permissions.approve"><span>Approve</span></label>
                                    <label class="pmx-settings__check"><input type="checkbox" x-model="contact.permissions.billing"><span>Billing</span></label>
                                    <label class="pmx-settings__check"><input type="checkbox" x-model="contact.permissions.domains"><span>Domains</span></label>
                                </div>
                            </div>
                        </template>

                        <div class="pmx-settings__actions">
                            <button
                                type="button"
                                class="pmx-settings__btn"
                                @click="addTrustedContact()"
                                :disabled="trustedContacts.length >= 5"
                            >Add contact</button>
                            <button type="button" class="pmx-settings__cta" @click="saveTrustedContacts()" :disabled="contactsBusy" x-text="contactsBusy ? 'Saving…' : 'Save contacts'"></button>
                        </div>
                        <p class="pmx-settings__hint" x-text="contactsStatus" x-show="contactsStatus"></p>
                    </div>
                </div>

                {{-- ACCOUNT --}}
                <div x-show="tab === 'account'" x-cloak>
                    <h3 class="pmx-settings__h">Account</h3>
                    <p class="pmx-settings__p">Business profile, verification, and team access.</p>

                    <div class="pmx-settings__card" style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
                        <div style="width:48px;height:48px;border-radius:999px;overflow:hidden;border:1px solid rgba(100,0,178,.55);background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <template x-if="avatarUrl">
                                <img :src="avatarUrl" alt="" style="width:100%;height:100%;object-fit:cover;" referrerpolicy="no-referrer">
                            </template>
                            <template x-if="!avatarUrl">
                                <span class="text-[16px] font-semibold leading-none text-white/90" x-text="avatarInitial"></span>
                            </template>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <p class="pmx-settings__label" style="margin:0;">Profile photo</p>
                            <p class="pmx-settings__hint" style="margin-top:2px;" x-text="avatarHasCustom ? 'Custom upload' : (avatarUrl ? 'From Google / Gmail' : 'Upload a profile photo')"></p>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                                <button type="button" class="pmx-settings__btn" @click="$refs.settingsAvatarInput.click()" :disabled="avatarBusy" x-text="avatarBusy ? 'Uploading…' : 'Change photo'"></button>
                                <button type="button" class="pmx-settings__btn" x-show="avatarHasCustom" @click="removeAvatar()" :disabled="avatarBusy">Remove</button>
                            </div>
                            <input
                                x-ref="settingsAvatarInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                style="display:none"
                                @change="uploadAvatar($event)"
                            >
                            <p class="pmx-settings__hint" style="margin-top:6px;" x-show="avatarStatus" x-text="avatarStatus"></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ $profileUpdateUrl }}" class="pmx-settings__card pmx-settings__stack">
                        @csrf
                        @method('PATCH')
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-company">Company name</label>
                            <input id="pmx-company" type="text" name="company_name" class="pmx-settings__input" value="{{ old('company_name', $settingsUser?->company_name) }}">
                        </div>
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-website">Website URL</label>
                            <input id="pmx-website" type="url" name="website_url" class="pmx-settings__input" value="{{ old('website_url', $settingsUser?->website_url) }}">
                        </div>
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-support">Support email</label>
                            <input id="pmx-support" type="email" name="support_email" class="pmx-settings__input" value="{{ old('support_email', $settingsUser?->support_email) }}">
                        </div>
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-phone">Phone</label>
                            <input id="pmx-phone" type="text" name="phone" class="pmx-settings__input" value="{{ old('phone', $settingsUser?->phone) }}">
                        </div>
                        <div class="pmx-settings__field">
                            <label class="pmx-settings__label" for="pmx-address">Company address</label>
                            <textarea id="pmx-address" name="company_address" class="pmx-settings__textarea" rows="2">{{ old('company_address', $settingsUser?->company_address) }}</textarea>
                        </div>
                        <input type="hidden" name="name" value="{{ $settingsUser?->name }}">
                        <input type="hidden" name="email" value="{{ $settingsUser?->email }}">
                        <div class="pmx-settings__actions">
                            <button type="submit" class="pmx-settings__cta">Save business info</button>
                            <a href="{{ $profileUrl }}" class="pmx-settings__btn">Open profile</a>
                        </div>
                    </form>

                    <div class="pmx-settings__card" style="margin-top:12px">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Business verification</p>
                                <p class="pmx-settings__hint">Status placeholder — complete verification from billing/support if required.</p>
                            </div>
                            <span class="pmx-settings__badge">Pending</span>
                        </div>
                    </div>

                    <div class="pmx-settings__card" style="margin-top:12px" x-ref="teamSection">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label" id="pmx-team-title">Team members</p>
                                <p class="pmx-settings__hint">Invite and manage roles for your workspace.</p>
                            </div>
                            <button
                                type="button"
                                class="pmx-settings__btn"
                                @click="teamOpen = !teamOpen"
                                :aria-expanded="teamOpen"
                                aria-controls="pmx-team-panel"
                            >
                                <span x-text="teamOpen ? 'Hide team' : 'Manage team'"></span>
                            </button>
                        </div>
                        <div id="pmx-team-panel" class="pmx-team-inline" x-show="teamOpen" x-cloak x-transition.opacity>
                            <p class="pmx-settings__hint" style="margin:10px 0 0;">Only members in your workspace are shown here.</p>
                            <div class="pmx-team-member">
                                <span class="pmx-team-member__avatar" x-text="team.owner.initial"></span>
                                <div class="min-w-0 flex-1">
                                    <p x-text="team.owner.name"></p>
                                    <span x-text="team.owner.email"></span>
                                </div>
                                <span class="pmx-settings__badge">Owner</span>
                            </div>
                            <template x-for="member in team.members" :key="member.email">
                                <div class="pmx-team-member">
                                    <span class="pmx-team-member__avatar" x-text="member.initial"></span>
                                    <div class="min-w-0 flex-1">
                                        <p x-text="member.name"></p>
                                        <span x-text="member.email"></span>
                                    </div>
                                    <span class="pmx-settings__badge" x-text="member.role"></span>
                                </div>
                            </template>
                            <p class="pmx-settings__hint mt-3" x-show="!team.members.length">No additional team members yet.</p>
                            @if (auth()->user()?->canInviteTeamMembers())
                                <form method="POST" action="{{ route('team.invite') }}" class="pmx-team-invite">
                                    @csrf
                                    <p class="pmx-settings__label">Invite teammate</p>
                                    <input type="text" name="name" class="pmx-settings__input" placeholder="Name (optional)">
                                    <input type="email" name="email" class="pmx-settings__input" placeholder="Email" required>
                                    <button type="submit" class="pmx-settings__cta">Send invite</button>
                                </form>
                            @else
                                <p class="pmx-settings__hint mt-3">Team invite is available on Enterprise, Advanced, and Custom plans.</p>
                                <a href="{{ route('billing.index') }}" class="pmx-settings__btn" style="margin-top:10px;">Upgrade to invite</a>
                            @endif
                        </div>
                    </div>

                    <div class="pmx-settings__actions">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="pmx-settings__btn pmx-settings__btn--muted">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pmx-settings {
        position: fixed;
        inset: 0;
        z-index: 240;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .pmx-settings__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.62);
        backdrop-filter: blur(2px);
    }
    .pmx-settings__panel {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        width: min(980px, 96vw);
        max-height: min(80vh, calc(100vh - 32px));
        overflow: hidden;
        border-radius: 14px;
        border: 1px solid rgba(100, 0, 178, 0.45);
        background: #121212;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.55);
    }
    .pmx-settings__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .pmx-settings__head h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
    }
    .pmx-settings__close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        color: rgba(255, 255, 255, 0.7);
        background: transparent;
        border: 0;
        cursor: pointer;
    }
    .pmx-settings__close:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
    .pmx-settings__body {
        display: grid;
        grid-template-columns: 210px minmax(0, 1fr);
        min-height: 0;
        flex: 1;
    }
    @media (max-width: 720px) {
        .pmx-settings__body { grid-template-columns: 1fr; }
        .pmx-settings__nav {
            display: flex;
            flex-wrap: wrap;
            border-right: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            max-height: 140px;
        }
        .pmx-settings__tab { width: auto; }
    }
    .pmx-settings__nav {
        padding: 10px;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        background: #0f0f0f;
        overflow: auto;
    }
    .pmx-settings__tab {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        text-align: left;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        font-weight: 600;
        padding: 9px 10px;
        margin-bottom: 4px;
        cursor: pointer;
    }
    .pmx-settings__tab-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        color: inherit;
        line-height: 0;
    }
    .pmx-settings__tab-icon svg {
        width: 16px;
        height: 16px;
        display: block;
    }
    .pmx-settings__tab.is-active {
        background: #6400B2;
        color: #fff;
        outline: 1px solid rgba(255, 255, 255, 0.25);
    }
    .pmx-security-tabs {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        margin-bottom: 12px;
        padding-bottom: 3px;
        scrollbar-width: thin;
    }
    .pmx-security-tabs__button {
        flex: 0 0 auto;
        min-height: 34px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 7px;
        background: #181818;
        color: rgba(255, 255, 255, 0.62);
        padding: 7px 11px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
    }
    .pmx-security-tabs__button:hover {
        border-color: rgba(100, 0, 178, 0.65);
        color: #fff;
    }
    .pmx-security-tabs__button.is-active {
        border-color: #7900d8;
        background: #6400B2;
        color: #fff;
        box-shadow: 0 0 10px rgba(100, 0, 178, 0.25);
    }
    .pmx-security-panel { padding: 14px; }
    .pmx-security-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .pmx-security-panel__title {
        margin: 0;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
    }
    .pmx-security-status,
    .pmx-security-notice {
        margin: 0 0 12px;
        border: 1px solid rgba(34, 197, 94, 0.3);
        border-radius: 7px;
        background: rgba(34, 197, 94, 0.08);
        color: #bbf7d0;
        padding: 9px 10px;
        font-size: 11px;
    }
    .pmx-security-notice {
        border-color: rgba(245, 158, 11, 0.35);
        background: rgba(245, 158, 11, 0.08);
        color: #fde68a;
    }
    .pmx-security-2fa-grid {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
    }
    .pmx-security-qr {
        width: 150px;
        height: 150px;
        border-radius: 8px;
        background: #fff;
        padding: 7px;
    }
    .pmx-security-secret {
        margin-top: 5px;
        overflow-wrap: anywhere;
        color: #c4a0e8;
        font: 10px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .pmx-security-codes {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 4px 12px;
        margin: 8px 0 0;
        padding: 0;
        list-style: none;
        font: 10px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .pmx-security-form-row,
    .pmx-security-inline-form {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        margin-top: 12px;
    }
    .pmx-security-form-row .pmx-settings__field { flex: 1; }
    .pmx-security-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .pmx-security-inline-form {
        align-items: stretch;
        flex-direction: column;
        margin-top: 0;
    }
    .pmx-security-danger {
        border-color: rgba(244, 63, 94, 0.42) !important;
        color: #fda4af !important;
    }
    .pmx-security-error {
        margin: 5px 0 0;
        color: #fda4af;
        font-size: 10px;
    }
    .pmx-security-session-list {
        display: grid;
        gap: 7px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .pmx-security-session-list > li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 6px;
        background: rgba(0, 0, 0, 0.2);
        padding: 9px 10px;
    }
    .pmx-security-current {
        margin-left: 5px;
        color: #86efac;
        font-size: 9px;
        font-weight: 600;
    }
    .pmx-security-user-agent {
        max-width: 260px;
        overflow: hidden;
        color: rgba(255, 255, 255, 0.36);
        font-size: 10px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    @media (max-width: 720px) {
        .pmx-security-2fa-grid,
        .pmx-security-actions-grid { grid-template-columns: 1fr; }
        .pmx-security-form-row { align-items: stretch; flex-direction: column; }
        .pmx-security-session-list > li { align-items: flex-start; flex-direction: column; }
        .pmx-security-user-agent { max-width: 100%; }
    }
    .pmx-settings__content {
        min-height: 0;
        overflow: auto;
        padding: 16px 18px 20px;
    }
    .pmx-settings__h {
        margin: 0 0 4px;
        font-size: 18px;
        font-weight: 700;
        color: #fff;
    }
    .pmx-settings__p {
        margin: 0 0 14px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.5);
    }
    .pmx-settings__section { margin-bottom: 14px; }
    .pmx-settings__section-title {
        margin: 0 0 10px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.45);
    }
    .pmx-settings__card {
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: #181818;
        padding: 14px;
    }
    .pmx-team-inline {
        margin-top: 4px;
    }
    .pmx-team-invite {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .pmx-team-member {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 60px;
        padding: 9px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .pmx-team-member__avatar {
        display: inline-flex;
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: rgba(100, 0, 178, 0.28);
        color: #e4d4f4;
        font-size: 12px;
        font-weight: 700;
    }
    .pmx-team-member p {
        overflow: hidden;
        margin: 0;
        color: rgba(255, 255, 255, 0.92);
        font-size: 12px;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pmx-team-member span:not(.pmx-team-member__avatar):not(.pmx-settings__badge) {
        display: block;
        overflow: hidden;
        margin-top: 2px;
        color: rgba(255, 255, 255, 0.45);
        font-size: 10px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pmx-settings__stack { display: flex; flex-direction: column; }
    .pmx-settings__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0;
        min-height: 52px;
    }
    .pmx-settings__row + .pmx-settings__row { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .pmx-settings__row > :last-child {
        flex-shrink: 0;
        display: inline-flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
    }
    .pmx-settings__inline { display: inline-flex; align-items: center; gap: 8px; }
    .pmx-settings__label {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.92);
    }
    .pmx-settings__hint {
        margin: 3px 0 0;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.45);
    }
    .pmx-settings__link { color: #C4A0E8; text-decoration: underline; }
    .pmx-settings__field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
    .pmx-settings__field:last-child { margin-bottom: 0; }
    .pmx-settings__field-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
    }
    .pmx-settings__field-row + .pmx-settings__field-row { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .pmx-settings__appear-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }
    .pmx-settings__appear {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
        padding: 10px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: #181818;
        color: rgba(255, 255, 255, 0.85);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-align: left;
    }
    .pmx-settings__appear.is-active {
        border-color: #6400B2;
        box-shadow: inset 0 0 0 1px rgba(100, 0, 178, 0.55);
        background: rgba(100, 0, 178, 0.16);
    }
    .pmx-settings__appear-preview {
        display: block;
        width: 100%;
        height: 44px;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .pmx-settings__appear-preview--dark {
        background: linear-gradient(135deg, #0d0d0d 40%, #2a1040 100%);
    }
    .pmx-settings__appear-preview--light {
        background: linear-gradient(135deg, #f7f4fb 40%, #e8dff5 100%);
    }
    .pmx-settings__appear-preview--system {
        background: linear-gradient(90deg, #0d0d0d 50%, #f7f4fb 50%);
    }
    .pmx-settings__badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        color: #C4A0E8;
        background: rgba(100, 0, 178, 0.22);
        white-space: nowrap;
    }
    .pmx-settings__badge.is-ok {
        color: #9BE7B4;
        background: rgba(34, 140, 78, 0.28);
    }
    .pmx-settings__chip-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: transparent;
        color: rgba(255, 255, 255, 0.75);
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
    }
    .pmx-settings__chip-btn.is-active {
        background: #6400B2;
        border-color: #6400B2;
        color: #fff;
    }
    .pmx-settings__range {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }
    .pmx-settings__report-controls {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(140px, 1.6fr) auto;
        gap: 10px;
        align-items: end;
    }
    .pmx-settings__report-controls .pmx-settings__field {
        margin-bottom: 0;
    }
    .pmx-settings__report-controls .pmx-settings__input,
    .pmx-settings__report-controls .pmx-settings__select {
        height: 38px;
        min-height: 38px;
        box-sizing: border-box;
    }
    .pmx-settings__report-download {
        height: 38px;
        min-height: 38px;
        padding: 0 16px;
        white-space: nowrap;
        margin: 0;
    }
    .pmx-settings__report-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }
    @media (max-width: 840px) {
        .pmx-settings__report-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pmx-settings__report-controls { grid-template-columns: 1fr 1fr; }
        .pmx-settings__report-download { grid-column: 1 / -1; }
        .pmx-settings__appear-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
        .pmx-settings__report-grid { grid-template-columns: 1fr; }
        .pmx-settings__report-controls { grid-template-columns: 1fr; }
        .pmx-settings__stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .pmx-settings__report-card {
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: #181818;
        padding: 12px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .pmx-settings__stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .pmx-settings__stat {
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: #181818;
        padding: 12px;
        text-align: center;
    }
    .pmx-settings__stat-val {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    .pmx-settings__stat-lbl {
        margin: 4px 0 0;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.45);
    }
    .pmx-settings__table-wrap { overflow: auto; }
    .pmx-settings__table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.85);
    }
    .pmx-settings__table th {
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: rgba(255, 255, 255, 0.45);
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        background: #141414;
    }
    .pmx-settings__table td {
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        vertical-align: middle;
    }
    .pmx-settings__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        min-width: 108px;
        padding: 0 14px;
        border-radius: 8px;
        background: #6400B2;
        color: #fff !important;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        border: 0;
        cursor: pointer;
    }
    .pmx-settings__cta:hover { background: #7B13C8; }
    .pmx-settings__cta:disabled { opacity: 0.6; cursor: wait; }
    .pmx-settings__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        min-width: 96px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid rgba(100, 0, 178, 0.45);
        background: rgba(100, 0, 178, 0.2);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
    }
    .pmx-settings__btn:hover { background: rgba(100, 0, 178, 0.35); }
    .pmx-settings__btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .pmx-settings__btn--muted {
        border-color: rgba(255, 255, 255, 0.14);
        background: transparent;
        color: rgba(255, 255, 255, 0.7);
    }
    .pmx-settings__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-top: 12px;
    }
    .pmx-settings__switch {
        position: relative;
        width: 42px;
        height: 24px;
        display: inline-flex;
        min-width: 42px !important;
        flex-shrink: 0;
    }
    .pmx-settings__switch input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }
    .pmx-settings__switch span {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        transition: 0.15s ease;
        cursor: pointer;
    }
    .pmx-settings__switch span::after {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        top: 3px;
        border-radius: 50%;
        background: #fff;
        transition: 0.15s ease;
    }
    .pmx-settings__switch input:checked + span { background: #6400B2; }
    .pmx-settings__switch input:checked + span::after { transform: translateX(18px); }
    .pmx-settings__check {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.85);
        padding: 6px 0;
        cursor: pointer;
    }
    .pmx-settings__check input {
        width: 15px;
        height: 15px;
        accent-color: #6400B2;
    }
    .pmx-settings__contact { padding: 8px 0; }
    .pmx-settings__contact + .pmx-settings__contact { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .pmx-settings__contact-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .pmx-settings__contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 8px;
    }
    .pmx-settings__perms {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        margin-top: 8px;
    }
    @media (max-width: 640px) {
        .pmx-settings__contact-grid { grid-template-columns: 1fr; }
        .pmx-settings__field-row { flex-direction: column; align-items: stretch; }
    }
    .pmx-settings__input,
    .pmx-settings__select,
    .pmx-settings__textarea {
        width: 100%;
        min-height: 34px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: #101010;
        color: #fff;
        font-size: 12px;
        padding: 0 10px;
    }
    .pmx-settings__textarea {
        min-height: 64px;
        padding: 8px 10px;
        resize: vertical;
    }
    .pmx-settings__input--sm { width: 96px; min-width: 96px; }
    .pmx-settings__select { appearance: none; background-image: linear-gradient(45deg, transparent 50%, rgba(255,255,255,.5) 50%), linear-gradient(135deg, rgba(255,255,255,.5) 50%, transparent 50%); background-position: calc(100% - 14px) 14px, calc(100% - 9px) 14px; background-size: 5px 5px, 5px 5px; background-repeat: no-repeat; padding-right: 28px; }
    .pmx-settings__input:focus,
    .pmx-settings__select:focus,
    .pmx-settings__textarea:focus {
        outline: none;
        border-color: rgba(100, 0, 178, 0.7);
    }

    html.light-mode .pmx-settings__panel { background: #fff; border-color: #d4c4e8; }
    html.light-mode .pmx-settings__nav { background: #f7f4fb; border-color: #e7e1ef; }
    html.light-mode .pmx-settings__head,
    html.light-mode .pmx-settings__row + .pmx-settings__row,
    html.light-mode .pmx-settings__contact + .pmx-settings__contact,
    html.light-mode .pmx-settings__field-row + .pmx-settings__field-row,
    html.light-mode .pmx-settings__table th,
    html.light-mode .pmx-settings__table td { border-color: #e7e1ef; }
    html.light-mode .pmx-settings__head h2,
    html.light-mode .pmx-settings__h,
    html.light-mode .pmx-settings__label,
    html.light-mode .pmx-settings__stat-val,
    html.light-mode .pmx-settings__table,
    html.light-mode .pmx-settings__appear,
    html.light-mode .pmx-settings__check { color: #1a1524; }
    html.light-mode .pmx-settings__p,
    html.light-mode .pmx-settings__hint,
    html.light-mode .pmx-settings__section-title,
    html.light-mode .pmx-settings__stat-lbl,
    html.light-mode .pmx-settings__table th { color: #6b6478; }
    html.light-mode .pmx-settings__tab { color: #5b5568; }
    html.light-mode .pmx-settings__card,
    html.light-mode .pmx-settings__report-card,
    html.light-mode .pmx-settings__stat,
    html.light-mode .pmx-settings__appear { background: #faf8fc; border-color: #e7e1ef; }
    html.light-mode .pmx-settings__table th { background: #f3eef8; }
    html.light-mode .pmx-settings__close { color: #5b5568; }
    html.light-mode .pmx-settings__input,
    html.light-mode .pmx-settings__select,
    html.light-mode .pmx-settings__textarea {
        background: #fff;
        color: #1a1524;
        border-color: #d9d0e6;
    }
    html.light-mode .pmx-settings__chip-btn {
        color: #5b5568;
        border-color: #d9d0e6;
    }
    html.light-mode .pmx-settings__switch span { background: rgba(26, 21, 36, 0.18); }
    html.light-mode .pmx-security-tabs__button {
        border-color: #e0d7eb;
        background: #faf8fc;
        color: #5b5568;
    }
    html.light-mode .pmx-security-tabs__button.is-active {
        border-color: #6400B2;
        background: #6400B2;
        color: #fff;
    }
    html.light-mode .pmx-security-panel__title { color: #1a1524; }
    html.light-mode .pmx-security-session-list > li {
        border-color: #e7e1ef;
        background: #faf8fc;
    }
    html.light-mode .pmx-security-user-agent { color: #766e83; }
</style>

<script>
window.promotixSettingsModal = function promotixSettingsModal(seed = {}) {
    const emptyContact = () => ({
        name: '',
        email: '',
        phone: '',
        role: 'security',
        permissions: { alerts: true, approve: false, billing: false, domains: false },
    });

    return {
        open: false,
        tab: 'general',
        securityTab: seed.security_tab || 'two_factor',
        appearance: seed.appearance || 'dark',
        language: seed.language || 'en',
        timezone: seed.timezone || 'UTC',
        defaultDashboard: seed.default_dashboard || 'overview',
        dataDisplay: Object.assign({
            show_risk_scores: true,
            show_technical_ip: true,
            show_advanced_columns: false,
        }, seed.data_display || {}),
        otherOptions: Object.assign({
            auto_refresh: true,
            dashboard_tips: true,
            alert_sound: false,
        }, seed.other_options || {}),
        notifications: Object.assign({
            email: true,
            sms: false,
            push: true,
            email_alerts: true,
            product_updates: true,
            weekly_digest: false,
            invalid_clicks_threshold: 50,
            risk_score_threshold: 80,
            detection: { vpn: true, proxy: true, datacenter: true, bot: true, abnormal: true },
            system: { tracking_disconnected: true, google_disconnected: true, api_failure: true, domain_offline: true },
        }, seed.notifications || {}),
        safety: Object.assign({
            invalid_traffic: true,
            bot_protection: true,
            session_recording: false,
            captcha: false,
            mask_passwords: true,
            mask_payment: true,
            cookie_consent: true,
            retention_days: 90,
            gdpr: true,
            ccpa: false,
        }, seed.safety || {}),
        loginAlerts: seed.login_alerts !== false,
        trustedContacts: Array.isArray(seed.trusted_contacts) && seed.trusted_contacts.length
            ? seed.trusted_contacts.map((c) => Object.assign(emptyContact(), c, {
                permissions: Object.assign(emptyContact().permissions, (c && c.permissions) || {}),
            }))
            : [emptyContact()],
        csrf: seed.csrf || '',
        prefsUrl: seed.prefsUrl || '/user/preferences',
        reportDownloadUrl: seed.reportDownloadUrl || '/reports',
        reportUrls: seed.reportUrls || {},
        avatarUrl: seed.avatar?.url || '',
        avatarInitial: seed.avatar?.initial || '?',
        avatarHasCustom: Boolean(seed.avatar?.has_custom),
        avatarUpdateUrl: seed.avatar?.update_url || '/profile/avatar',
        avatarDestroyUrl: seed.avatar?.destroy_url || '/profile/avatar',
        avatarBusy: false,
        avatarStatus: '',
        teamOpen: false,
        team: Object.assign({ owner: { name: 'Workspace owner', email: '', role: 'Owner', initial: '?' }, members: [] }, seed.team || {}),
        reportRange: '7d',
        reportGroup: 'dashboard_ips',
        reportFormat: 'csv',
        reportCustomFrom: '',
        reportCustomTo: '',
        reportBusy: false,
        reportStatus: '',
        monthlyEmailToggle: (() => {
            try {
                const seed = @json((bool) ($settingsNotify['monthly_report_email'] ?? false));
                if (seed) return true;
                return localStorage.getItem('promotix-monthly-report-email') === '1';
            } catch (_) { return false; }
        })(),
        generalStatus: '',
        generalBusy: false,
        notifyStatus: '',
        notifyBusy: false,
        safetyStatus: '',
        safetyBusy: false,
        contactsStatus: '',
        contactsBusy: false,

        avatarErrorMessage(payload, fallback) {
            const firstError = payload?.errors?.avatar;
            if (Array.isArray(firstError) && firstError[0]) return firstError[0];
            return payload?.message || fallback;
        },
        syncAvatarImages() {
            if (!this.avatarUrl) return;
            document.querySelectorAll('img[data-promotix-avatar]').forEach((img) => {
                img.src = this.avatarUrl;
            });
        },
        async uploadAvatar(event) {
            const input = event?.target;
            const file = input?.files?.[0];
            if (!file || this.avatarBusy) return;

            this.avatarBusy = true;
            this.avatarStatus = '';
            try {
                const body = new FormData();
                body.append('avatar', file);
                const res = await fetch(this.avatarUpdateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(this.avatarErrorMessage(payload, 'Could not upload photo.'));

                this.avatarUrl = payload.avatar_url || '';
                this.avatarHasCustom = Boolean(payload.has_custom_avatar);
                this.avatarStatus = payload.message || 'Profile photo updated.';
                this.syncAvatarImages();
            } catch (error) {
                this.avatarStatus = error?.message || 'Could not upload photo.';
            } finally {
                this.avatarBusy = false;
                if (input) input.value = '';
            }
        },
        async removeAvatar() {
            if (!this.avatarHasCustom || this.avatarBusy) return;

            this.avatarBusy = true;
            this.avatarStatus = '';
            try {
                const res = await fetch(this.avatarDestroyUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(this.avatarErrorMessage(payload, 'Could not remove photo.'));

                this.avatarUrl = payload.avatar_url || '';
                this.avatarHasCustom = false;
                this.avatarStatus = payload.message || 'Profile photo removed.';
                this.syncAvatarImages();
            } catch (error) {
                this.avatarStatus = error?.message || 'Could not remove photo.';
            } finally {
                this.avatarBusy = false;
            }
        },
        reportDate(offsetDays = 0) {
            const date = new Date(Date.now() + (Number(offsetDays) * 86400000));
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: this.timezone || 'UTC',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });
            const parts = Object.fromEntries(
                formatter.formatToParts(date)
                    .filter((part) => part.type !== 'literal')
                    .map((part) => [part.type, part.value])
            );

            return `${parts.year}-${parts.month}-${parts.day}`;
        },
        reportDateRange() {
            if (this.reportCustomFrom && this.reportCustomTo) {
                return {
                    from: this.reportCustomFrom <= this.reportCustomTo ? this.reportCustomFrom : this.reportCustomTo,
                    to: this.reportCustomFrom <= this.reportCustomTo ? this.reportCustomTo : this.reportCustomFrom,
                };
            }
            if (this.reportRange === 'yesterday') {
                const yesterday = this.reportDate(-1);
                return { from: yesterday, to: yesterday };
            }

            const fromOffset = this.reportRange === '30d' ? -29 : (this.reportRange === '7d' ? -6 : 0);
            return { from: this.reportDate(fromOffset), to: this.reportDate(0) };
        },
        setReportRange(range) {
            this.reportRange = range;
            if (range === 'custom') return;
            const next = this.computedReportRange(range);
            this.reportCustomFrom = next.from;
            this.reportCustomTo = next.to;
        },
        computedReportRange(range) {
            if (range === 'yesterday') {
                const yesterday = this.reportDate(-1);
                return { from: yesterday, to: yesterday };
            }
            const fromOffset = range === '30d' ? -29 : (range === '7d' ? -6 : 0);
            return { from: this.reportDate(fromOffset), to: this.reportDate(0) };
        },
        buildReportDownloadUrl() {
            const group = this.reportGroup || 'dashboard_ips';
            const base = this.reportUrls[group] || this.reportDownloadUrl;
            const url = new URL(base, window.location.origin);
            const range = this.reportDateRange();
            url.searchParams.set('from', range.from);
            url.searchParams.set('to', range.to);
            if (group === 'bot') {
                url.searchParams.set('source', 'dashboard');
            } else if (group === 'bot_advanced') {
                url.searchParams.set('source', 'advanced');
            }
            url.searchParams.set('format', this.reportFormat === 'xlsx' ? 'xlsx' : 'csv');
            // Analytics exports are CSV-only today; avoid a mismatched .xlsx download name.
            if ((group === 'bot' || group === 'bot_advanced') && this.reportFormat === 'xlsx') {
                url.searchParams.set('format', 'csv');
            }

            return url.toString();
        },
        async persistMonthlyEmailToggle() {
            try {
                localStorage.setItem('promotix-monthly-report-email', this.monthlyEmailToggle ? '1' : '0');
            } catch (_) {}
            try {
                await this.savePrefs({ notifications: { monthly_report_email: Boolean(this.monthlyEmailToggle) } });
            } catch (_) {}
        },
        filenameFromDisposition(header, fallback) {
            const match = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(header || '');
            const raw = decodeURIComponent((match && (match[1] || match[2])) || fallback);
            return raw.replace(/[/\\]/g, '');
        },
        async downloadReport() {
            if (this.reportBusy) return;
            this.reportBusy = true;
            this.reportStatus = 'Preparing download…';
            try {
                const url = this.buildReportDownloadUrl();
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'text/csv,application/octet-stream,*/*',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) {
                    throw new Error('Download failed (' + res.status + '). Try a shorter date range.');
                }
                const blob = await res.blob();
                if (!blob || blob.size < 8 || (blob.type || '').includes('text/html')) {
                    throw new Error('The report did not finish. Try a shorter date range.');
                }
                const fallback = (this.reportGroup || 'report') + '.' + (this.reportFormat === 'xlsx' ? 'xlsx' : 'csv');
                const filename = this.filenameFromDisposition(res.headers.get('content-disposition'), fallback);
                const href = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = href;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(href), 1500);
                this.reportStatus = 'Download started.';
            } catch (error) {
                this.reportStatus = error?.message || 'Download failed. Please try again.';
            } finally {
                this.reportBusy = false;
            }
        },
        initModal() {
            this.setReportRange(this.reportRange || '7d');
            if (seed.security_auto_open) {
                this.tab = 'security';
                this.securityTab = seed.security_tab || 'two_factor';
                this.open = true;
                document.body.style.overflow = 'hidden';
            }
        },
        openModal(tab, detail) {
            if (tab) this.tab = tab;
            this.open = true;
            this.teamOpen = Boolean(detail?.team);
            document.body.style.overflow = 'hidden';
            if (this.teamOpen) {
                this.$nextTick(() => this.$refs.teamSection?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
            }
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },
        async savePrefs(payload) {
            const res = await fetch(this.prefsUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('save_failed');
            return res.json();
        },
        applyAppearanceLocal(value) {
            const root = document.documentElement;
            if (value === 'light') {
                root.classList.add('light-mode');
                root.classList.remove('dark');
            } else if (value === 'dark') {
                root.classList.remove('light-mode');
                root.classList.add('dark');
            } else {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                root.classList.toggle('light-mode', !prefersDark);
                root.classList.toggle('dark', prefersDark);
            }
        },
        async setAppearance(value) {
            this.appearance = value;
            this.applyAppearanceLocal(value);
            try {
                await this.savePrefs({ appearance: value });
            } catch (e) {}
        },
        async saveGeneral() {
            this.generalBusy = true;
            this.generalStatus = 'Saving…';
            try {
                await this.savePrefs({
                    appearance: this.appearance,
                    language: this.language,
                    timezone: this.timezone,
                    default_dashboard: this.defaultDashboard,
                    data_display: this.dataDisplay,
                    other_options: this.otherOptions,
                });
                this.applyAppearanceLocal(this.appearance);
                this.generalStatus = 'Saved';
            } catch (e) {
                this.generalStatus = 'Could not save';
            } finally {
                this.generalBusy = false;
            }
        },
        async saveNotifications() {
            this.notifyBusy = true;
            this.notifyStatus = 'Saving…';
            try {
                const payload = Object.assign({}, this.notifications, {
                    email_alerts: !!this.notifications.email,
                });
                await this.savePrefs({ notifications: payload });
                this.notifyStatus = 'Saved';
            } catch (e) {
                this.notifyStatus = 'Could not save';
            } finally {
                this.notifyBusy = false;
            }
        },
        async saveSafety() {
            this.safetyBusy = true;
            this.safetyStatus = 'Saving…';
            try {
                await this.savePrefs({ safety: this.safety });
                this.safetyStatus = 'Saved';
            } catch (e) {
                this.safetyStatus = 'Could not save';
            } finally {
                this.safetyBusy = false;
            }
        },
        async saveLoginAlerts() {
            try {
                await this.savePrefs({ login_alerts: !!this.loginAlerts });
            } catch (e) {}
        },
        addTrustedContact() {
            if (this.trustedContacts.length >= 5) return;
            this.trustedContacts.push(emptyContact());
        },
        async saveTrustedContacts() {
            this.contactsBusy = true;
            this.contactsStatus = '';
            try {
                await this.savePrefs({ trusted_contacts: this.trustedContacts });
                this.contactsStatus = 'Contacts saved';
            } catch (e) {
                this.contactsStatus = 'Could not save contacts';
            } finally {
                this.contactsBusy = false;
            }
        },
    };
};
</script>
