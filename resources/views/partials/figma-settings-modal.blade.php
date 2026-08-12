{{-- ChatGPT-style Settings modal — Phase 2 prefs + sheet surfaces --}}
@php
    $settingsUser = auth()->user();
    $settingsTz = $settingsUser ? \App\Support\UserTimezone::forUser($settingsUser) : 'UTC';
    $settingsPrefs = (array) ($settingsUser?->ui_preferences ?? []);
    $settingsNotify = (array) ($settingsPrefs['notifications'] ?? []);
    $settingsContacts = array_values(array_pad(
        array_slice((array) ($settingsPrefs['trusted_contacts'] ?? []), 0, 3),
        3,
        ['name' => '', 'email' => '', 'phone' => '']
    ));
    $settingsTabs = [
        ['id' => 'general', 'label' => 'General'],
        ['id' => 'notifications', 'label' => 'Notifications'],
        ['id' => 'reports', 'label' => 'Data Reports'],
        ['id' => 'billing', 'label' => 'Billing'],
        ['id' => 'domains', 'label' => 'Domains'],
        ['id' => 'safety', 'label' => 'Safety'],
        ['id' => 'security', 'label' => 'Security & Login'],
        ['id' => 'contacts', 'label' => 'Trusted Contacts'],
        ['id' => 'account', 'label' => 'Account'],
    ];
@endphp

<div
    id="promotix-settings-modal"
    class="pmx-settings"
    x-data="promotixSettingsModal(@js([
        'notifications' => [
            'email_alerts' => (bool) ($settingsNotify['email_alerts'] ?? true),
            'product_updates' => (bool) ($settingsNotify['product_updates'] ?? true),
            'weekly_digest' => (bool) ($settingsNotify['weekly_digest'] ?? false),
        ],
        'login_alerts' => (bool) ($settingsPrefs['login_alerts'] ?? true),
        'trusted_contacts' => $settingsContacts,
        'csrf' => csrf_token(),
        'prefsUrl' => url('/user/preferences'),
    ]))"
    x-cloak
    x-show="open"
    x-transition.opacity
    @keydown.escape.window="close()"
    @open-promotix-settings.window="openModal($event.detail?.tab)"
    @close-promotix-settings.window="close()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pmx-settings-title"
>
    <div class="pmx-settings__backdrop" @click="close()"></div>
    <div class="pmx-settings__panel" @click.stop x-transition>
        <header class="pmx-settings__head">
            <h2 id="pmx-settings-title">Settings</h2>
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
                    >{{ $tab['label'] }}</button>
                @endforeach
            </nav>

            <div class="pmx-settings__content promotix-slim-scroll">
                <div x-show="tab === 'general'" x-cloak>
                    <h3 class="pmx-settings__h">General</h3>
                    <p class="pmx-settings__p">Appearance and reporting timezone already used across dashboards.</p>
                    <div class="pmx-settings__card">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Theme</p>
                                <p class="pmx-settings__hint">Uses your saved UI preference</p>
                            </div>
                            <button type="button" class="pmx-settings__btn" onclick="document.getElementById('theme-toggle')?.click()">Toggle theme</button>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Reporting timezone</p>
                                <p class="pmx-settings__hint font-mono">{{ $settingsTz }}</p>
                            </div>
                            <span class="pmx-settings__chip">Header picker</span>
                        </div>
                    </div>
                </div>

                <div x-show="tab === 'notifications'" x-cloak>
                    <h3 class="pmx-settings__h">Notifications</h3>
                    <p class="pmx-settings__p">Choose which alerts you want. Saved to your account preferences.</p>
                    <div class="pmx-settings__card pmx-settings__stack">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Email alerts</p>
                                <p class="pmx-settings__hint">Invalid traffic / block events</p>
                            </div>
                            <label class="pmx-settings__switch">
                                <input type="checkbox" x-model="notifications.email_alerts" @change="saveNotifications()">
                                <span></span>
                            </label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Product updates</p>
                                <p class="pmx-settings__hint">Feature and release notes</p>
                            </div>
                            <label class="pmx-settings__switch">
                                <input type="checkbox" x-model="notifications.product_updates" @change="saveNotifications()">
                                <span></span>
                            </label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Weekly digest</p>
                                <p class="pmx-settings__hint">Summary of paid traffic health</p>
                            </div>
                            <label class="pmx-settings__switch">
                                <input type="checkbox" x-model="notifications.weekly_digest" @change="saveNotifications()">
                                <span></span>
                            </label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">In-app activity feed</p>
                                <p class="pmx-settings__hint">Bell panel in the right bar</p>
                            </div>
                            <button type="button" class="pmx-settings__btn" onclick="document.getElementById('rightbar-notify-toggle')?.click(); window.dispatchEvent(new CustomEvent('close-promotix-settings'));">Open alerts</button>
                        </div>
                        <p class="pmx-settings__hint" x-text="notifyStatus" x-show="notifyStatus"></p>
                    </div>
                </div>

                <div x-show="tab === 'reports'" x-cloak>
                    <h3 class="pmx-settings__h">Data Reports</h3>
                    <p class="pmx-settings__p">Export and review reporting from the existing Reports page.</p>
                    <div class="pmx-settings__card">
                        <a href="{{ route('reports.index') }}" class="pmx-settings__cta">Open Reports →</a>
                    </div>
                </div>

                <div x-show="tab === 'billing'" x-cloak>
                    <h3 class="pmx-settings__h">Billing</h3>
                    <p class="pmx-settings__p">Plans, payments, and receipts live on Billing.</p>
                    <div class="pmx-settings__card">
                        @if (! $settingsUser || $settingsUser->canAccess('upgrade-plan'))
                            <a href="{{ route('billing.index') }}" class="pmx-settings__cta">Open Billing →</a>
                        @else
                            <p class="pmx-settings__hint">Billing is not available for this account.</p>
                        @endif
                    </div>
                </div>

                <div x-show="tab === 'domains'" x-cloak>
                    <h3 class="pmx-settings__h">Domains</h3>
                    <p class="pmx-settings__p">Manage tracked domains and tracking setup.</p>
                    <div class="pmx-settings__card">
                        @if (! $settingsUser || $settingsUser->canAccess('domain-management'))
                            <a href="{{ route('domains.index') }}" class="pmx-settings__cta">Open Domains →</a>
                        @else
                            <p class="pmx-settings__hint">Domain management is not available for this account.</p>
                        @endif
                    </div>
                </div>

                <div x-show="tab === 'safety'" x-cloak>
                    <h3 class="pmx-settings__h">Safety</h3>
                    <p class="pmx-settings__p">Bot protection rules, geo blocks, and IP controls.</p>
                    <div class="pmx-settings__card">
                        @if (! $settingsUser || $settingsUser->canAccess('paid-marketing-detection-settings'))
                            <a href="{{ route('paid-marketing.detection-settings') }}" class="pmx-settings__cta">Open Detection Settings →</a>
                        @else
                            <p class="pmx-settings__hint">Detection settings are not available for this account.</p>
                        @endif
                    </div>
                </div>

                <div x-show="tab === 'security'" x-cloak>
                    <h3 class="pmx-settings__h">Security & Login</h3>
                    <p class="pmx-settings__p">Password, login history, and security rollout controls.</p>
                    <div class="pmx-settings__card pmx-settings__stack">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Password</p>
                                <p class="pmx-settings__hint">Update account password from Security.</p>
                            </div>
                            <a href="{{ route('profile.edit') }}#password-security-section" class="pmx-settings__btn">Open password</a>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Login history</p>
                                <p class="pmx-settings__hint">Recent sign-ins, IPs, browsers, and devices.</p>
                            </div>
                            <a href="{{ route('profile.edit') }}#login-history-section" class="pmx-settings__btn">View activity</a>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Login alerts</p>
                                <p class="pmx-settings__hint">Notify on new-device / unusual sign-in.</p>
                            </div>
                            <label class="pmx-settings__switch">
                                <input type="checkbox" x-model="loginAlerts" @change="saveLoginAlerts()">
                                <span></span>
                            </label>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Two-factor authentication</p>
                                <p class="pmx-settings__hint">Authenticator-app challenge (backend next).</p>
                            </div>
                            <span class="pmx-settings__chip">Coming soon</span>
                        </div>
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">API keys</p>
                                <p class="pmx-settings__hint">Programmatic access tokens (backend next).</p>
                            </div>
                            <span class="pmx-settings__chip">Coming soon</span>
                        </div>
                    </div>
                </div>

                <div x-show="tab === 'contacts'" x-cloak>
                    <h3 class="pmx-settings__h">Trusted Contacts</h3>
                    <p class="pmx-settings__p">Add up to 3 recovery / escalation contacts (saved on your account).</p>
                    <div class="pmx-settings__card pmx-settings__stack">
                        <template x-for="(contact, idx) in trustedContacts" :key="'tc-' + idx">
                            <div class="pmx-settings__contact">
                                <p class="pmx-settings__label" x-text="'Contact ' + (idx + 1)"></p>
                                <div class="pmx-settings__contact-grid">
                                    <input type="text" class="pmx-settings__input" placeholder="Name" x-model="contact.name">
                                    <input type="email" class="pmx-settings__input" placeholder="Email" x-model="contact.email">
                                    <input type="text" class="pmx-settings__input" placeholder="Phone (optional)" x-model="contact.phone">
                                </div>
                            </div>
                        </template>
                        <div class="pmx-settings__actions">
                            <button type="button" class="pmx-settings__cta" @click="saveTrustedContacts()" :disabled="contactsBusy" x-text="contactsBusy ? 'Saving…' : 'Save contacts'"></button>
                            <a href="{{ route('profile.edit') }}#profile-information-section" class="pmx-settings__btn">Open account</a>
                        </div>
                        <p class="pmx-settings__hint" x-text="contactsStatus" x-show="contactsStatus"></p>
                    </div>
                </div>

                <div x-show="tab === 'account'" x-cloak>
                    <h3 class="pmx-settings__h">Account</h3>
                    <p class="pmx-settings__p">Your profile details and account access.</p>
                    <div class="pmx-settings__card">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">{{ $settingsUser?->name ?? 'User' }}</p>
                                <p class="pmx-settings__hint">{{ $settingsUser?->email ?? '' }}</p>
                            </div>
                        </div>
                        <div class="pmx-settings__actions">
                            <a href="{{ route('profile.edit') }}" class="pmx-settings__cta">Edit profile →</a>
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
        width: min(860px, 100%);
        max-height: min(640px, calc(100vh - 32px));
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
    }
    .pmx-settings__close:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
    .pmx-settings__body {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr);
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
        }
    }
    .pmx-settings__nav {
        padding: 10px;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        background: #0f0f0f;
        overflow: auto;
    }
    .pmx-settings__tab {
        display: block;
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
    .pmx-settings__tab.is-active {
        background: #6400B2;
        color: #fff;
        outline: 1px solid rgba(255, 255, 255, 0.25);
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
    .pmx-settings__card {
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: #181818;
        padding: 14px;
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
        min-width: 108px;
        display: inline-flex;
        justify-content: flex-end;
    }
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
    .pmx-settings__chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 108px;
        min-height: 32px;
        font-size: 10px;
        font-weight: 600;
        color: #C4A0E8;
        background: rgba(100, 0, 178, 0.22);
        border-radius: 8px;
        padding: 4px 10px;
        text-align: center;
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
        min-width: 108px;
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
    .pmx-settings__btn--muted {
        border-color: rgba(255, 255, 255, 0.14);
        background: transparent;
        color: rgba(255, 255, 255, 0.7);
    }
    .pmx-settings__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
    }
    .pmx-settings__switch {
        position: relative;
        width: 42px;
        height: 24px;
        display: inline-flex;
        min-width: 42px !important;
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
    .pmx-settings__contact { padding: 8px 0; }
    .pmx-settings__contact + .pmx-settings__contact { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .pmx-settings__contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        margin-top: 8px;
    }
    @media (max-width: 640px) {
        .pmx-settings__contact-grid { grid-template-columns: 1fr; }
    }
    .pmx-settings__input {
        width: 100%;
        min-height: 34px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: #101010;
        color: #fff;
        font-size: 12px;
        padding: 0 10px;
    }
    .pmx-settings__input:focus {
        outline: none;
        border-color: rgba(100, 0, 178, 0.7);
    }
    html.light-mode .pmx-settings__panel { background: #fff; border-color: #d4c4e8; }
    html.light-mode .pmx-settings__nav { background: #f7f4fb; border-color: #e7e1ef; }
    html.light-mode .pmx-settings__head { border-color: #e7e1ef; }
    html.light-mode .pmx-settings__head h2,
    html.light-mode .pmx-settings__h,
    html.light-mode .pmx-settings__label { color: #1a1524; }
    html.light-mode .pmx-settings__p,
    html.light-mode .pmx-settings__hint { color: #6b6478; }
    html.light-mode .pmx-settings__tab { color: #5b5568; }
    html.light-mode .pmx-settings__card { background: #faf8fc; border-color: #e7e1ef; }
    html.light-mode .pmx-settings__close { color: #5b5568; }
    html.light-mode .pmx-settings__input {
        background: #fff;
        color: #1a1524;
        border-color: #d9d0e6;
    }
</style>

<script>
window.promotixSettingsModal = function promotixSettingsModal(seed = {}) {
    return {
        open: false,
        tab: 'general',
        notifications: seed.notifications || { email_alerts: true, product_updates: true, weekly_digest: false },
        loginAlerts: seed.login_alerts !== false,
        trustedContacts: Array.isArray(seed.trusted_contacts) && seed.trusted_contacts.length
            ? seed.trusted_contacts
            : [{ name: '', email: '', phone: '' }, { name: '', email: '', phone: '' }, { name: '', email: '', phone: '' }],
        csrf: seed.csrf || '',
        prefsUrl: seed.prefsUrl || '/user/preferences',
        notifyStatus: '',
        contactsStatus: '',
        contactsBusy: false,
        openModal(tab) {
            if (tab) this.tab = tab;
            this.open = true;
            document.body.style.overflow = 'hidden';
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
        async saveNotifications() {
            this.notifyStatus = 'Saving…';
            try {
                await this.savePrefs({ notifications: this.notifications });
                this.notifyStatus = 'Saved';
            } catch (e) {
                this.notifyStatus = 'Could not save';
            }
        },
        async saveLoginAlerts() {
            try {
                await this.savePrefs({ login_alerts: !!this.loginAlerts });
            } catch (e) {}
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
