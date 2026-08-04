{{-- ChatGPT-style Settings modal — wires existing pages only (no new backends) --}}
@php
    $settingsUser = auth()->user();
    $settingsTz = $settingsUser ? \App\Support\UserTimezone::forUser($settingsUser) : 'UTC';
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
    x-data="promotixSettingsModal()"
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
                {{-- General — existing theme + timezone --}}
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

                {{-- Notifications — existing rightbar feed --}}
                <div x-show="tab === 'notifications'" x-cloak>
                    <h3 class="pmx-settings__h">Notifications</h3>
                    <p class="pmx-settings__p">Alerts already load in the right panel bell. Preference controls can plug in here later.</p>
                    <div class="pmx-settings__card">
                        <div class="pmx-settings__row">
                            <div>
                                <p class="pmx-settings__label">Activity alerts</p>
                                <p class="pmx-settings__hint">Shown under the notifications bell</p>
                            </div>
                            <button type="button" class="pmx-settings__btn" onclick="document.getElementById('rightbar-notify-toggle')?.click(); window.dispatchEvent(new CustomEvent('close-promotix-settings'));">Open alerts</button>
                        </div>
                    </div>
                </div>

                {{-- Data Reports --}}
                <div x-show="tab === 'reports'" x-cloak>
                    <h3 class="pmx-settings__h">Data Reports</h3>
                    <p class="pmx-settings__p">Export and review reporting from the existing Reports page.</p>
                    <div class="pmx-settings__card">
                        <a href="{{ route('reports.index') }}" class="pmx-settings__cta">Open Reports →</a>
                    </div>
                </div>

                {{-- Billing --}}
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

                {{-- Domains --}}
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

                {{-- Safety → Detection --}}
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

                {{-- Security --}}
                <div x-show="tab === 'security'" x-cloak>
                    <h3 class="pmx-settings__h">Security & Login</h3>
                    <p class="pmx-settings__p">Password and login security from your profile page.</p>
                    <div class="pmx-settings__card">
                        <a href="{{ route('profile.edit') }}" class="pmx-settings__cta">Open Security settings →</a>
                    </div>
                </div>

                {{-- Trusted Contacts — no backend yet; show shape only --}}
                <div x-show="tab === 'contacts'" x-cloak>
                    <h3 class="pmx-settings__h">Trusted Contacts</h3>
                    <p class="pmx-settings__p">This tab is reserved for trusted contacts. No new backend yet — structure only for demos.</p>
                    <div class="pmx-settings__card">
                        <p class="pmx-settings__hint">Coming next: invite / manage trusted contacts for escalations.</p>
                    </div>
                </div>

                {{-- Account --}}
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
            display: flex !important;
            flex-direction: row !important;
            overflow-x: auto;
            border-right: 0 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
    }
    .pmx-settings__nav {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 10px;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        background: #0e0e0e;
        overflow-y: auto;
    }
    .pmx-settings__tab {
        text-align: left;
        border: 0;
        background: transparent;
        color: rgba(255, 255, 255, 0.68);
        font-size: 12px;
        font-weight: 500;
        padding: 9px 10px;
        border-radius: 8px;
        white-space: nowrap;
        cursor: pointer;
    }
    .pmx-settings__tab:hover { background: rgba(100, 0, 178, 0.18); color: #fff; }
    .pmx-settings__tab.is-active {
        background: #6400B2;
        color: #fff;
    }
    .pmx-settings__content {
        padding: 18px 18px 22px;
        overflow-y: auto;
        min-height: 0;
    }
    .pmx-settings__h {
        margin: 0 0 6px;
        font-size: 18px;
        font-weight: 700;
        color: #fff;
    }
    .pmx-settings__p {
        margin: 0 0 14px;
        font-size: 12px;
        line-height: 1.45;
        color: rgba(255, 255, 255, 0.55);
    }
    .pmx-settings__card {
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: #181818;
        padding: 14px;
    }
    .pmx-settings__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
    }
    .pmx-settings__row + .pmx-settings__row {
        border-top: 1px solid rgba(255, 255, 255, 0.06);
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
        font-size: 10px;
        font-weight: 600;
        color: #C4A0E8;
        background: rgba(100, 0, 178, 0.22);
        border-radius: 999px;
        padding: 4px 8px;
    }
    .pmx-settings__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0 14px;
        border-radius: 8px;
        background: #6400B2;
        color: #fff !important;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
    }
    .pmx-settings__cta:hover { background: #7B13C8; }
    .pmx-settings__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid rgba(100, 0, 178, 0.45);
        background: rgba(100, 0, 178, 0.2);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
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
</style>

<script>
window.promotixSettingsModal = function promotixSettingsModal() {
    return {
        open: false,
        tab: 'general',
        openModal(tab) {
            if (tab) this.tab = tab;
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },
    };
};
</script>
