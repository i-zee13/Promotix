@extends('layouts.admin')

@section('title', 'Implement tracking tag')
@section('subtitle', $domain->hostname)

@section('content')
@php
    $scriptUrl = url('/tag/' . $domain->domain_key . '.js');
    $noscriptUrl = url('/tag/' . $domain->domain_key . '.html');
    $headSnippet = '<script src="' . $scriptUrl . '" class="pm_tag"></script>';
    $bodySnippet = '<noscript><iframe src="' . $noscriptUrl . '" width="0" height="0" style="display:none"></iframe></noscript>';
    $gtmSnippet = "<script>(function(){var s=document.createElement('script');s.src='".url('/tag/' . $domain->domain_key . ".js");s.className='pm_tag';document.head.appendChild(s);}())</script>";
    $gtmIconPath = public_path('images/google-tag-manager.svg');
    $wpIconPath = public_path('images/wordpress.svg');
    $gtmIconSrc = url('/images/google-tag-manager.svg') . (is_file($gtmIconPath) ? '?v=' . filemtime($gtmIconPath) : '');
    $wpIconSrc = url('/images/wordpress.svg') . (is_file($wpIconPath) ? '?v=' . filemtime($wpIconPath) : '');
    $methods = [
        ['key' => 'gtm', 'title' => 'Google Tag Manager', 'icon' => 'gtm'],
        ['key' => 'wp', 'title' => 'WordPress Plugin', 'icon' => 'wp'],
        ['key' => 'manual', 'title' => 'Direct Installation', 'icon' => 'code'],
        ['key' => 'email', 'title' => 'Email my developer', 'icon' => 'email'],
    ];
@endphp

<div class="figma-domain-setup min-h-[calc(100vh-49px)]" x-data="domainSetup(@js([
    'domainKey' => $domain->domain_key,
    'secretKey' => $domain->secret_key,
    'authKey' => $domain->authentication_key,
]))">
    <div class="fixed bottom-4 right-4 z-[60] rounded-lg border border-white/20 bg-[#101010] px-4 py-3 text-sm text-white shadow-lg"
         x-show="toast.open" x-cloak x-transition>
        <span x-text="toast.message"></span>
    </div>

    <section class="mx-auto w-full max-w-[980px] px-[16px] pb-[40px] pt-[32px] sm:px-[24px] xl:pt-[56px]">
        <div class="figma-domain-setup__hero mb-[28px]">
            <a href="{{ route('domains.index') }}" class="mb-[16px] inline-flex items-center gap-[6px] text-[11px] text-white/70 hover:text-white">
                <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to domains
            </a>
            <h1 class="text-[26px] font-semibold leading-tight text-white sm:text-[32px]">Implement your tracking tag</h1>
            <p class="mx-auto mt-[12px] max-w-[720px] text-[12px] leading-relaxed text-white/80 sm:text-[13px]">
                The tracking tag will enable Clickpromo to monitor and block invalid bot activity. Follow the next steps to install it.
                Note, the code is unique per website.
            </p>
            <p class="mt-[14px] text-[13px] text-white/90">
                Select setup method for <span class="font-semibold text-white">({{ $domain->hostname }})</span>
            </p>
        </div>

        <div class="figma-domain-setup__method-grid mb-[22px]">
            @foreach ($methods as $m)
                <button
                    type="button"
                    @click="tab='{{ $m['key'] }}'"
                    class="figma-domain-setup__method-card"
                    :class="tab === '{{ $m['key'] }}' ? 'figma-domain-setup__method-card--active' : 'figma-domain-setup__method-card--idle'"
                >
                    <span class="figma-domain-setup__method-icon">
                        @if ($m['icon'] === 'gtm')
                            <img src="{{ $gtmIconSrc }}" alt="" width="40" height="40" loading="lazy" class="figma-domain-setup__method-img">
                        @elseif ($m['icon'] === 'wp')
                            <img src="{{ $wpIconSrc }}" alt="" width="40" height="40" loading="lazy" class="figma-domain-setup__method-img">
                        @elseif ($m['icon'] === 'code')
                            <svg class="figma-domain-setup__method-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        @else
                            <svg class="figma-domain-setup__method-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        @endif
                    </span>
                    <span class="figma-domain-setup__method-label">{{ $m['title'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Google Tag Manager --}}
        <div class="figma-domain-setup__panel" x-show="tab === 'gtm'" x-cloak>
            <div class="figma-domain-setup__panel-columns">
                <div class="figma-domain-setup__panel-col flex flex-col justify-between gap-[18px]">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/55">Option 1 (recommended)</p>
                        <div class="mt-[6px] flex flex-wrap items-center gap-[10px]">
                            <h2 class="text-[18px] font-semibold text-white">Direct Installation</h2>
                            @if ($domain->tag_connected)
                                <span class="figma-domain-setup__badge">Tag Installed</span>
                            @endif
                        </div>
                        <button type="button" class="figma-domain-setup__btn-gtm mt-[18px]" disabled title="GTM auto-connect coming soon">
                            Connect with Google Tag Manager
                        </button>
                    </div>
                    <p class="figma-domain-setup__disclaimer">
                        By using this integration, you authorize data transfer to Google. Review Google's API Services User Data Policy.
                        Keep caching plugins from excluding this tag for accurate tracking.
                    </p>
                </div>
                <div class="figma-domain-setup__panel-col">
                    <h3 class="figma-domain-setup__instructions-title">Manual installation:</h3>
                    <ol class="figma-domain-setup__steps mt-[12px] max-h-[200px] overflow-y-auto pr-[6px]">
                        <li>In Google Tag Manager select <strong>Custom HTML</strong>.</li>
                        <li>Add the invocation tag provided below.</li>
                        <li>Check the <strong>support document.write</strong> checkbox.</li>
                        <li>Set tag firing priority to <strong>9999</strong>.</li>
                        <li>Set the trigger to fire on <strong>Initialization — All Pages</strong>.</li>
                        <li>Save and publish the changes.</li>
                    </ol>
                    <div class="mt-[16px]">
                        <label class="mb-[6px] block text-[11px] font-medium text-white/75" for="gtm-container-id">GTM container ID</label>
                        <div class="mb-[14px] flex flex-wrap gap-[8px]">
                            <input id="gtm-container-id" type="text" value="{{ $domain->gtm_container_id }}" placeholder="GTM-XXXXXXX" class="figma-domain-setup__input max-w-[280px]">
                            <button type="button" class="figma-domain-setup__btn-primary" @click="saveGtm('{{ $domain->id }}')">Save</button>
                        </div>
                        <p class="mb-[6px] text-[11px] font-medium text-white/75">Invocation tag</p>
                        <div class="figma-domain-setup__dotted-box">{{ $gtmSnippet }}</div>
                        <button type="button" class="figma-domain-setup__copy-link" @click="copyText(@js($gtmSnippet))">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- WordPress Plugin --}}
        <div class="figma-domain-setup__panel figma-domain-setup__panel--instructions" x-show="tab === 'wp'" x-cloak>
            <h2 class="figma-domain-setup__instructions-title">Instructions</h2>
            <p class="figma-domain-setup__instruction-step">
                1. <a href="{{ route('domains.wp-plugin', $domain) }}" class="text-white underline hover:text-white/90">Install Clickpromo Wordpress plugin</a>
            </p>
            <div class="mt-[16px] flex flex-wrap items-center justify-between gap-[10px]">
                <p class="figma-domain-setup__instruction-step mb-0">2. &gt; Paste these Keys in the Clickpromo Wordpress plugin:</p>
                <button type="button" class="figma-domain-setup__copy-all" @click="copyAllKeys()">
                    <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Copy all
                </button>
            </div>
            @foreach ([
                ['Domain Key', $domain->domain_key],
                ['Secret key', $domain->secret_key],
                ['Authentication Key', $domain->authentication_key],
            ] as [$label, $value])
                <div class="figma-domain-setup__key-row">
                    <span class="figma-domain-setup__key-label">{{ $label }}</span>
                    <div class="figma-domain-setup__dotted-box min-h-[44px]">{{ $value }}</div>
                    <button type="button" class="figma-domain-setup__copy-link shrink-0" @click="copyText(@js($value))">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Copy
                    </button>
                </div>
            @endforeach
            <div class="mt-[20px]">
                <button type="button" class="figma-domain-setup__btn-primary" @click="verifyWordpress('{{ $domain->id }}')">Verify plugin</button>
            </div>
        </div>

        {{-- Direct Installation --}}
        <div class="figma-domain-setup__panel figma-domain-setup__panel--instructions" x-show="tab === 'manual'" x-cloak>
            <h2 class="figma-domain-setup__instructions-title">Instructions</h2>
            <p class="figma-domain-setup__instruction-step">1. Paste the code at the beginning of the <strong>&lt;Head&gt;</strong></p>
            <div class="figma-domain-setup__dotted-box">{{ $headSnippet }}</div>
            <button type="button" class="figma-domain-setup__copy-link" @click="copyText(@js($headSnippet))">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Copy
            </button>
            <p class="figma-domain-setup__instruction-step mt-[20px]">2. Paste the code at the beginning of the <strong>&lt;body&gt;</strong></p>
            <div class="figma-domain-setup__dotted-box">{{ $bodySnippet }}</div>
            <button type="button" class="figma-domain-setup__copy-link" @click="copyText(@js($bodySnippet))">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Copy
            </button>
        </div>

        {{-- Email my developer --}}
        <div class="figma-domain-setup__panel figma-domain-setup__panel--instructions" x-show="tab === 'email'" x-cloak>
            <h2 class="figma-domain-setup__instructions-title">Instructions</h2>
            <p class="figma-domain-setup__instruction-step">
                Email the tracking tag instructions to yourself or to your website manager
            </p>
            <div class="figma-domain-setup__email-row">
                <input id="developer-email" type="email" placeholder="Enter email address" class="figma-domain-setup__email-input">
                <button type="button" class="figma-domain-setup__btn-send" @click="sendDeveloperEmail('{{ $domain->id }}')">
                    <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send
                </button>
            </div>
        </div>

        <details class="mt-[18px] rounded-[10px] border border-white/20 bg-black/15 px-[18px] py-[14px] text-white">
            <summary class="cursor-pointer text-[13px] font-medium text-white/90">Tracking parameters (UTM)</summary>
            <div class="mt-[14px] grid grid-cols-2 gap-[10px] sm:grid-cols-4">
                @foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term'] as $param)
                    <label class="flex items-center gap-[8px] rounded-[8px] border border-white/15 bg-black/20 px-[12px] py-[10px] text-[12px] text-white/90">
                        <x-figma-toggle
                            class="utm-toggle"
                            data-param="{{ $param }}"
                            :checked="($domain->tracking_params[$param] ?? true) === true"
                            :show-labels="false"
                        />
                        {{ $param }}
                    </label>
                @endforeach
            </div>
            <button type="button" class="figma-domain-setup__btn-primary mt-[14px]" @click="saveTrackingParams('{{ $domain->id }}')">Save tracking params</button>
        </details>

        <div class="mt-[28px] flex justify-end">
            <a href="{{ route('domains.index') }}" class="figma-domain-setup__btn-done">Done</a>
        </div>
    </section>
</div>

<script>
function domainSetup(keys = {}) {
    return {
        tab: 'gtm',
        keys,
        toast: { open: false, message: '' },
        showToast(message) {
            this.toast.message = message;
            this.toast.open = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast.open = false), 2200);
        },
        copyAllKeys() {
            const text = [
                `Domain Key: ${this.keys.domainKey || ''}`,
                `Secret key: ${this.keys.secretKey || ''}`,
                `Authentication Key: ${this.keys.authKey || ''}`,
            ].join('\n');
            this.copyText(text);
        },
        async copyText(text) {
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                    this.showToast('Copied');
                    return;
                }
            } catch (e) {}
            try {
                const el = document.createElement('textarea');
                el.value = text;
                el.setAttribute('readonly', '');
                el.style.position = 'fixed';
                el.style.left = '-9999px';
                document.body.appendChild(el);
                el.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(el);
                if (ok) { this.showToast('Copied'); return; }
            } catch (e) {}
            window.prompt('Copy to clipboard:', text);
        },
        async saveGtm(domainId) {
            const value = document.getElementById('gtm-container-id')?.value || '';
            const res = await fetch(`/domains/${domainId}/gtm`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ gtm_container_id: value }),
            });
            if (res.ok) this.showToast('GTM ID saved');
        },
        async saveTrackingParams(domainId) {
            const toggles = Array.from(document.querySelectorAll('input.utm-toggle'));
            const tracking_params = {};
            toggles.forEach((el) => { tracking_params[el.dataset.param] = !!el.checked; });
            const res = await fetch(`/domains/${domainId}/tracking-params`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ tracking_params }),
            });
            if (res.ok) this.showToast('Tracking params saved');
        },
        async sendDeveloperEmail(domainId) {
            const email = document.getElementById('developer-email')?.value || '';
            const res = await fetch(`/domains/${domainId}/email-developer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email }),
            });
            if (res.ok) this.showToast('Instructions emailed');
        },
        async verifyWordpress(domainId) {
            const res = await fetch(`/domains/${domainId}/verify-wordpress`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });
            const data = await res.json();
            this.showToast(data.message || (data.verified ? 'Verified' : 'Not verified'));
        },
    };
}
</script>
@endsection
