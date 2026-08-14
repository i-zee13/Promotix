@php
    use App\Http\Controllers\TwoFactorController;
    use App\Support\TotpAuthenticator;

    $securityUser = $settingsUser ?? auth()->user();
    $securityEnabled = $securityUser?->hasTwoFactorEnabled() ?? false;
    $securityPendingSecret = session('two_factor.setup_secret')
        ?: (($securityUser?->two_factor_secret && ! $securityEnabled) ? $securityUser->two_factor_secret : null);
    $securityOtpAuth = $securityPendingSecret
        ? TotpAuthenticator::provisioningUri($securityPendingSecret, (string) $securityUser->email, config('app.name', 'Clickronix'))
        : null;
    $securityQrUrl = $securityOtpAuth ? TotpAuthenticator::qrImageUrl($securityOtpAuth) : null;
    $securitySessions = $securityUser ? TwoFactorController::sessionsFor(request()) : [];
    $securityApiKeys = ($securityUser && \Illuminate\Support\Facades\Schema::hasTable('user_api_keys'))
        ? $securityUser->apiKeys()->latest('id')->get()
        : collect();
    $securityRecoveryCodes = session('two_factor.recovery_codes');
    $securityPlainApiKey = session('api_key.plain');
@endphp

<div class="pmx-security-tabs" role="tablist" aria-label="Security settings">
    @foreach ([
        'two_factor' => 'Two-factor authentication',
        'password' => 'Password',
        'sessions' => 'Active sessions',
        'api_keys' => 'API keys',
        'login_alerts' => 'Login alerts',
    ] as $securityTabId => $securityTabLabel)
        <button
            type="button"
            class="pmx-security-tabs__button"
            :class="{ 'is-active': securityTab === '{{ $securityTabId }}' }"
            @click="securityTab = '{{ $securityTabId }}'"
            role="tab"
            :aria-selected="securityTab === '{{ $securityTabId }}'"
        >{{ $securityTabLabel }}</button>
    @endforeach
</div>

@if (session('status') && session('settings.security_tab'))
    <p class="pmx-security-status">{{ session('status') }}</p>
@endif

{{-- Two-factor authentication --}}
<section x-show="securityTab === 'two_factor'" x-cloak class="pmx-settings__card pmx-security-panel">
    <div class="pmx-security-panel__head">
        <div>
            <h4 class="pmx-security-panel__title">Two-factor authentication</h4>
            <p class="pmx-settings__hint">Protect login with Google Authenticator, Authy, or another TOTP app.</p>
        </div>
        <span class="pmx-settings__badge {{ $securityEnabled ? 'is-ok' : '' }}">
            {{ $securityEnabled ? 'Enabled' : ($securityPendingSecret ? 'Confirm code' : 'Disabled') }}
        </span>
    </div>

    @if ($securityRecoveryCodes)
        <div class="pmx-security-notice">
            <p class="pmx-settings__label">Save these recovery codes now</p>
            <ul class="pmx-security-codes">
                @foreach ($securityRecoveryCodes as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($securityPendingSecret && ! $securityEnabled)
        <div class="pmx-security-2fa-grid">
            @if ($securityQrUrl)
                <img src="{{ $securityQrUrl }}" alt="2FA QR code" class="pmx-security-qr">
            @endif
            <div>
                <p class="pmx-settings__hint">1. Scan this QR code in your authenticator app.</p>
                <p class="pmx-settings__hint" style="margin-top:8px">2. Or enter this secret manually:</p>
                <p class="pmx-security-secret">{{ $securityPendingSecret }}</p>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="pmx-security-form-row">
                    @csrf
                    <input type="hidden" name="settings_security_tab" value="two_factor">
                    <div class="pmx-settings__field">
                        <label class="pmx-settings__label" for="settings-2fa-code">Authenticator code</label>
                        <input id="settings-2fa-code" type="text" name="code" required maxlength="12" inputmode="numeric" autocomplete="one-time-code" class="pmx-settings__input">
                    </div>
                    <button type="submit" class="pmx-settings__cta">Confirm &amp; enable</button>
                </form>
                @error('code')<p class="pmx-security-error">{{ $message }}</p>@enderror
            </div>
        </div>
    @elseif (! $securityEnabled)
        <form method="POST" action="{{ route('two-factor.enable') }}" class="pmx-settings__actions">
            @csrf
            <input type="hidden" name="settings_security_tab" value="two_factor">
            <button type="submit" class="pmx-settings__cta">Enable 2FA</button>
        </form>
    @else
        <div class="pmx-security-actions-grid">
            <form method="POST" action="{{ route('two-factor.recovery') }}" class="pmx-security-inline-form">
                @csrf
                <input type="hidden" name="settings_security_tab" value="two_factor">
                <input type="password" name="password" required autocomplete="current-password" placeholder="Confirm password" class="pmx-settings__input">
                <button type="submit" class="pmx-settings__btn">Generate recovery codes</button>
            </form>
            <form method="POST" action="{{ route('two-factor.disable') }}" class="pmx-security-inline-form">
                @csrf
                <input type="hidden" name="settings_security_tab" value="two_factor">
                <input type="password" name="password" required autocomplete="current-password" placeholder="Confirm password" class="pmx-settings__input">
                <button type="submit" class="pmx-settings__btn pmx-security-danger">Disable 2FA</button>
            </form>
        </div>
    @endif
</section>

{{-- Password --}}
<section x-show="securityTab === 'password'" x-cloak class="pmx-settings__card pmx-security-panel">
    <div class="pmx-security-panel__head">
        <div>
            <h4 class="pmx-security-panel__title">Change password</h4>
            <p class="pmx-settings__hint">Use a strong, unique password for this account.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('password.update') }}" class="pmx-settings__stack">
        @csrf
        @method('PUT')
        <input type="hidden" name="settings_security_tab" value="password">
        <div class="pmx-settings__field">
            <label class="pmx-settings__label" for="settings-current-password">Current password</label>
            <input id="settings-current-password" name="current_password" type="password" autocomplete="current-password" class="pmx-settings__input">
            @foreach ($errors->updatePassword->get('current_password') as $message)
                <p class="pmx-security-error">{{ $message }}</p>
            @endforeach
        </div>
        <div class="pmx-settings__field">
            <label class="pmx-settings__label" for="settings-new-password">New password</label>
            <input id="settings-new-password" name="password" type="password" autocomplete="new-password" class="pmx-settings__input">
            @foreach ($errors->updatePassword->get('password') as $message)
                <p class="pmx-security-error">{{ $message }}</p>
            @endforeach
        </div>
        <div class="pmx-settings__field">
            <label class="pmx-settings__label" for="settings-password-confirmation">Confirm new password</label>
            <input id="settings-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="pmx-settings__input">
        </div>
        <div class="pmx-settings__actions">
            <button type="submit" class="pmx-settings__cta">Update password</button>
        </div>
    </form>
</section>

{{-- Sessions --}}
<section x-show="securityTab === 'sessions'" x-cloak class="pmx-settings__card pmx-security-panel">
    <div class="pmx-security-panel__head">
        <div>
            <h4 class="pmx-security-panel__title">Active sessions</h4>
            <p class="pmx-settings__hint">Review devices currently signed in and log out other sessions.</p>
        </div>
    </div>
    <ul class="pmx-security-session-list">
        @forelse ($securitySessions as $securitySession)
            <li>
                <div>
                    <p class="pmx-settings__label">
                        {{ $securitySession['ip'] ?: 'Unknown IP' }}
                        @if ($securitySession['is_current'])
                            <span class="pmx-security-current">This device</span>
                        @endif
                    </p>
                    <p class="pmx-settings__hint">{{ $securitySession['last_activity'] ?: 'Unknown activity time' }}</p>
                </div>
                <p class="pmx-security-user-agent" title="{{ $securitySession['user_agent'] }}">{{ $securitySession['user_agent'] ?: 'Unknown device' }}</p>
            </li>
        @empty
            <li><p class="pmx-settings__hint">No session rows are available for the current session driver.</p></li>
        @endforelse
    </ul>
    <form method="POST" action="{{ route('two-factor.sessions.destroy-others') }}" class="pmx-security-form-row">
        @csrf
        <input type="hidden" name="settings_security_tab" value="sessions">
        <div class="pmx-settings__field">
            <label class="pmx-settings__label" for="settings-session-password">Confirm password</label>
            <input id="settings-session-password" type="password" name="password" required autocomplete="current-password" class="pmx-settings__input">
        </div>
        <button type="submit" class="pmx-settings__btn">Log out other sessions</button>
    </form>
</section>

{{-- API keys --}}
<section x-show="securityTab === 'api_keys'" x-cloak class="pmx-settings__card pmx-security-panel">
    <div class="pmx-security-panel__head">
        <div>
            <h4 class="pmx-security-panel__title">API keys</h4>
            <p class="pmx-settings__hint">Create and revoke reporting API access tokens.</p>
        </div>
    </div>
    @if ($securityPlainApiKey)
        <div class="pmx-security-notice">
            <p class="pmx-settings__label">Copy this key now — it will not be shown again.</p>
            <p class="pmx-security-secret">{{ $securityPlainApiKey }}</p>
        </div>
    @endif
    <ul class="pmx-security-session-list">
        @forelse ($securityApiKeys as $securityApiKey)
            <li>
                <div>
                    <p class="pmx-settings__label">{{ $securityApiKey->name }}</p>
                    <p class="pmx-security-secret">{{ $securityApiKey->token_prefix }}…</p>
                </div>
                <form method="POST" action="{{ route('two-factor.api-keys.destroy', $securityApiKey) }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="settings_security_tab" value="api_keys">
                    <button type="submit" class="pmx-settings__btn pmx-security-danger">Revoke</button>
                </form>
            </li>
        @empty
            <li><p class="pmx-settings__hint">No API keys yet.</p></li>
        @endforelse
    </ul>
    <form method="POST" action="{{ route('two-factor.api-keys.store') }}" class="pmx-security-form-row">
        @csrf
        <input type="hidden" name="settings_security_tab" value="api_keys">
        <div class="pmx-settings__field">
            <label class="pmx-settings__label" for="settings-api-key-name">Key name</label>
            <input id="settings-api-key-name" type="text" name="name" required maxlength="120" placeholder="e.g. Reporting" class="pmx-settings__input">
        </div>
        <button type="submit" class="pmx-settings__cta">Create key</button>
    </form>
</section>

{{-- Login alerts --}}
<section x-show="securityTab === 'login_alerts'" x-cloak class="pmx-settings__card pmx-security-panel">
    <div class="pmx-settings__row">
        <div>
            <h4 class="pmx-security-panel__title">Login alerts</h4>
            <p class="pmx-settings__hint">Receive an email when a new device or unusual sign-in is detected.</p>
        </div>
        <label class="pmx-settings__switch">
            <input type="checkbox" x-model="loginAlerts" @change="saveLoginAlerts()">
            <span></span>
        </label>
    </div>
    <p class="pmx-settings__hint" style="margin-top:12px">Alerts are sent to {{ $securityUser?->email ?: 'your account email' }}.</p>
</section>
