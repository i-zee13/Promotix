@php
    use App\Http\Controllers\TwoFactorController;
    use App\Support\TotpAuthenticator;

    $user = $user ?? auth()->user();
    $enabled = $user?->hasTwoFactorEnabled() ?? false;
    $pendingSecret = old('setup') ? null : (session('two_factor.setup_secret') ?: (($user?->two_factor_secret && ! $enabled) ? $user->two_factor_secret : null));
    $otpauth = $pendingSecret ? TotpAuthenticator::provisioningUri($pendingSecret, (string) $user->email, config('app.name', 'Clickronix')) : null;
    $qrUrl = $otpauth ? TotpAuthenticator::qrImageUrl($otpauth) : null;
    $sessions = TwoFactorController::sessionsFor(request());
    $apiKeys = $user?->apiKeys ?? collect();
    $recoveryFlash = session('two_factor.recovery_codes');
    $plainApiKey = session('api_key.plain');
@endphp

<article id="security-controls-section" class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px]">
    <h2 class="text-[16px] font-semibold text-white">Security controls</h2>
    <p class="mt-[4px] text-[12px] text-[#a9a9a9]">2FA, sessions, and API keys from the Security &amp; Login sheet.</p>

    @if (session('status') && ! in_array(session('status'), ['profile-updated'], true))
        <p class="mt-[10px] rounded-[6px] border border-emerald-500/30 bg-emerald-500/10 px-[10px] py-[8px] text-[11px] text-emerald-200">{{ session('status') }}</p>
    @endif

    {{-- 2FA --}}
    <div class="mt-[14px] space-y-[12px] text-[12px]">
        <div class="rounded-[6px] border border-white/10 bg-black/25 px-[12px] py-[12px]">
            <div class="flex flex-wrap items-start justify-between gap-[12px]">
                <div>
                    <p class="font-semibold text-white">Two-factor authentication</p>
                    <p class="mt-[2px] text-white/55">Authenticator-app challenge at login (Google Authenticator / Authy).</p>
                </div>
                @if ($enabled)
                    <span class="shrink-0 rounded-[4px] bg-emerald-500/20 px-[8px] py-[3px] text-[10px] font-semibold uppercase text-emerald-300">Enabled</span>
                @elseif ($pendingSecret)
                    <span class="shrink-0 rounded-[4px] bg-amber-500/20 px-[8px] py-[3px] text-[10px] font-semibold uppercase text-amber-200">Confirm code</span>
                @else
                    <span class="shrink-0 rounded-[4px] bg-white/10 px-[8px] py-[3px] text-[10px] font-semibold uppercase text-white/60">Off</span>
                @endif
            </div>

            @if ($recoveryFlash)
                <div class="mt-[10px] rounded-[6px] border border-amber-400/30 bg-amber-500/10 p-[10px]">
                    <p class="font-semibold text-amber-100">Save these recovery codes now</p>
                    <ul class="mt-[6px] grid grid-cols-1 gap-[4px] font-mono text-[11px] text-white/85 sm:grid-cols-2">
                        @foreach ($recoveryFlash as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($pendingSecret && ! $enabled)
                <div class="mt-[12px] grid gap-[12px] sm:grid-cols-[160px_minmax(0,1fr)]">
                    @if ($qrUrl)
                        <img src="{{ $qrUrl }}" alt="2FA QR code" class="h-[160px] w-[160px] rounded-[8px] bg-white p-[8px]">
                    @endif
                    <div>
                        <p class="text-white/70">1. Scan QR in your authenticator app</p>
                        <p class="mt-[6px] text-white/70">2. Or enter secret manually:</p>
                        <p class="mt-[4px] break-all font-mono text-[11px] text-[#c4a0e8]">{{ $pendingSecret }}</p>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-[10px] flex flex-wrap items-end gap-[8px]">
                            @csrf
                            <div>
                                <label class="text-[10px] uppercase text-white/45">Authenticator code</label>
                                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="12"
                                       class="mt-[4px] w-[140px] rounded-[6px] border border-white/20 bg-[#101010] px-[10px] py-[7px] text-white">
                            </div>
                            <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[7px] font-semibold text-white hover:bg-[#7B13C8]">Confirm & enable</button>
                        </form>
                        @error('code')<p class="mt-[6px] text-[11px] text-rose-300">{{ $message }}</p>@enderror
                    </div>
                </div>
            @elseif (! $enabled)
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-[10px]">
                    @csrf
                    <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[7px] font-semibold text-white hover:bg-[#7B13C8]">Enable 2FA</button>
                </form>
            @else
                <div class="mt-[10px] flex flex-wrap gap-[8px]">
                    <form method="POST" action="{{ route('two-factor.recovery') }}">
                        @csrf
                        <input type="password" name="password" required placeholder="Password" class="mr-[6px] rounded-[6px] border border-white/20 bg-[#101010] px-[10px] py-[7px] text-white">
                        <button type="submit" class="rounded-[6px] border border-white/20 px-[10px] py-[7px] text-white/85 hover:bg-white/10">New recovery codes</button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        <input type="password" name="password" required placeholder="Password" class="mr-[6px] rounded-[6px] border border-white/20 bg-[#101010] px-[10px] py-[7px] text-white">
                        <button type="submit" class="rounded-[6px] border border-rose-400/40 px-[10px] py-[7px] text-rose-200 hover:bg-rose-500/15">Disable 2FA</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Active sessions --}}
        <div class="rounded-[6px] border border-white/10 bg-black/25 px-[12px] py-[12px]">
            <div class="flex flex-wrap items-start justify-between gap-[12px]">
                <div>
                    <p class="font-semibold text-white">Active sessions</p>
                    <p class="mt-[2px] text-white/55">Log out other devices that are signed in.</p>
                </div>
            </div>
            <ul class="mt-[10px] space-y-[6px]">
                @forelse ($sessions as $session)
                    <li class="flex flex-wrap items-center justify-between gap-[8px] rounded-[4px] bg-white/5 px-[8px] py-[6px] text-[11px]">
                        <span class="text-white/80">
                            {{ $session['ip'] ?: '—' }}
                            <span class="text-white/40">· {{ $session['last_activity'] ?: '—' }}</span>
                            @if ($session['is_current'])
                                <span class="ml-[4px] text-emerald-300">(this device)</span>
                            @endif
                        </span>
                        <span class="max-w-[240px] truncate text-white/40" title="{{ $session['user_agent'] }}">{{ $session['user_agent'] }}</span>
                    </li>
                @empty
                    <li class="text-white/40">No session rows available (session driver may not store user_id).</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('two-factor.sessions.destroy-others') }}" class="mt-[10px] flex flex-wrap items-center gap-[8px]">
                @csrf
                <input type="password" name="password" required placeholder="Confirm password" class="rounded-[6px] border border-white/20 bg-[#101010] px-[10px] py-[7px] text-white">
                <button type="submit" class="rounded-[6px] border border-white/20 px-[10px] py-[7px] text-white/85 hover:bg-white/10">Log out other sessions</button>
            </form>
        </div>

        {{-- API keys --}}
        <div class="rounded-[6px] border border-white/10 bg-black/25 px-[12px] py-[12px]">
            <div class="flex flex-wrap items-start justify-between gap-[12px]">
                <div>
                    <p class="font-semibold text-white">API keys</p>
                    <p class="mt-[2px] text-white/55">Programmatic access tokens for reporting APIs.</p>
                </div>
            </div>

            @if ($plainApiKey)
                <div class="mt-[10px] rounded-[6px] border border-amber-400/30 bg-amber-500/10 p-[10px]">
                    <p class="font-semibold text-amber-100">Copy this key now</p>
                    <p class="mt-[4px] break-all font-mono text-[11px] text-white">{{ $plainApiKey }}</p>
                </div>
            @endif

            <ul class="mt-[10px] space-y-[6px]">
                @forelse ($apiKeys as $key)
                    <li class="flex flex-wrap items-center justify-between gap-[8px] rounded-[4px] bg-white/5 px-[8px] py-[6px] text-[11px]">
                        <span class="text-white/85">{{ $key->name }} <span class="font-mono text-white/40">{{ $key->token_prefix }}…</span></span>
                        <form method="POST" action="{{ route('two-factor.api-keys.destroy', $key) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-300 hover:underline">Revoke</button>
                        </form>
                    </li>
                @empty
                    <li class="text-white/40">No API keys yet.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('two-factor.api-keys.store') }}" class="mt-[10px] flex flex-wrap items-center gap-[8px]">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="Key name (e.g. Reporting)" class="rounded-[6px] border border-white/20 bg-[#101010] px-[10px] py-[7px] text-white">
                <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[12px] py-[7px] font-semibold text-white hover:bg-[#7B13C8]">Create key</button>
            </form>
        </div>

        {{-- Login alerts --}}
        <div class="rounded-[6px] border border-white/10 bg-black/25 px-[12px] py-[12px]">
            <div class="flex flex-wrap items-center justify-between gap-[12px]">
                <div>
                    <p class="font-semibold text-white">Login alerts</p>
                    <p class="mt-[2px] text-white/55">Preference saved in Settings → Security (email on new device).</p>
                </div>
                <a href="#" onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings',{detail:{tab:'security'}})); return false;" class="rounded-[6px] border border-white/20 px-[10px] py-[7px] text-white/85 hover:bg-white/10">Open Settings</a>
            </div>
        </div>
    </div>
</article>
