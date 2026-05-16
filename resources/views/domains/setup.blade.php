@extends('layouts.admin')

@section('title', 'Implement tracking tag')
@section('subtitle', $domain->hostname)

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]">
    <section class="mx-auto w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[20px] flex flex-wrap items-center justify-between gap-[12px]">
            <div>
                <h1 class="text-[28px] font-semibold text-[#a9a9a9] sm:text-[36px]">Tracking setup</h1>
                <p class="mt-[6px] text-[13px] text-[#a9a9a9]">{{ $domain->hostname }}</p>
            </div>
            <a href="{{ route('domains.index') }}" class="rounded-[6px] border border-white/30 bg-[#6400B2] px-[16px] py-[8px] text-[12px] font-semibold text-white">← Domains</a>
        </div>
    <div class="space-y-6" x-data="domainSetup()">
        {{-- Toast --}}
        <div class="fixed bottom-4 right-4 z-[60] rounded-xl border border-night-700 bg-night-900 px-4 py-3 text-sm text-white shadow-card-lg"
             x-show="toast.open" x-cloak x-transition>
            <span x-text="toast.message"></span>
        </div>

        {{-- Method picker --}}
        <div class="rounded-[10px] border border-white/25 bg-[#6400B2] p-[18px] text-white shadow-[0_0_18px_rgba(100,0,179,.25)]">
            <h2 class="text-[18px] font-semibold">Choose setup method</h2>
            <p class="mt-[4px] text-[12px] text-white/80">Pick the path that fits your stack</p>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @php
                    $methods = [
                        ['key' => 'gtm',    'title' => 'Google Tag Manager', 'desc' => 'Provide GTM snippet'],
                        ['key' => 'wp',     'title' => 'WordPress Plugin',   'desc' => 'Keys for WP plugin'],
                        ['key' => 'manual', 'title' => 'Direct installation','desc' => 'Paste into <head>'],
                        ['key' => 'email',  'title' => 'Email developer',    'desc' => 'Send instructions'],
                    ];
                @endphp
                @foreach ($methods as $m)
                    <button type="button" @click="tab='{{ $m['key'] }}'"
                            class="brand-card-flat text-left transition hover:border-brand-400"
                            :class="tab==='{{ $m['key'] }}' ? 'ring-2 ring-brand-400 border-brand-400' : ''">
                        <p class="font-semibold text-white">{{ $m['title'] }}</p>
                        <p class="mt-1 text-xs text-night-400">{{ $m['desc'] }}</p>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Manual --}}
        @php
            $scriptUrl = url('/tag/' . $domain->domain_key . '.js');
            $noscriptUrl = url('/tag/' . $domain->domain_key . '.html');
        @endphp
        <div class="rounded-[10px] border border-white/15 bg-[#151515] p-[18px] text-white" x-show="tab==='manual'" x-cloak>
            <h3 class="text-[16px] font-semibold">Direct installation</h3>
            <p class="mt-[4px] text-[12px] text-[#a9a9a9]">Paste at the start of the &lt;head&gt;</p>
            <div class="mt-[14px] space-y-4">
                <div>
                    <p class="brand-label mb-1.5">Head script</p>
                    <div class="flex gap-2">
                        <textarea readonly rows="3" class="brand-input font-mono text-xs">&lt;script async src=&quot;{{ $scriptUrl }}&quot; class=&quot;pm_tag&quot;&gt;&lt;/script&gt;</textarea>
                        <x-ui.button type="button" variant="primary"
                                     @click="copyText(`<script async src=&quot;{{ $scriptUrl }}&quot; class=&quot;pm_tag&quot;></script>`)">
                            Copy
                        </x-ui.button>
                    </div>
                </div>

                <div>
                    <p class="brand-label mb-1.5">Body noscript</p>
                    <div class="flex gap-2">
                        <textarea readonly rows="3" class="brand-input font-mono text-xs">&lt;noscript&gt;&lt;iframe src=&quot;{{ $noscriptUrl }}&quot; width=&quot;0&quot; height=&quot;0&quot; style=&quot;display:none&quot;&gt;&lt;/iframe&gt;&lt;/noscript&gt;</textarea>
                        <x-ui.button type="button" variant="primary"
                                     @click="copyText(`<noscript><iframe src=&quot;{{ $noscriptUrl }}&quot; width=&quot;0&quot; height=&quot;0&quot; style=&quot;display:none&quot;></iframe></noscript>`)">
                            Copy
                        </x-ui.button>
                    </div>
                
                </div>
            </div>
        </div>

        {{-- WP plugin --}}
        @php
            $wpPluginSlug = 'promotix-tag';
            $wpBase = 'https://' . $domain->hostname;
            $wpAdminUrl = $wpBase . '/wp-admin/';
            $wpPluginInstallPath = '/wp-admin/plugin-install.php?tab=plugin-information&plugin=' . $wpPluginSlug;
            $wpAdminPluginInstallUrl = $wpBase . '/wp-login.php?redirect_to=' . urlencode($wpBase . $wpPluginInstallPath);
        @endphp
        <x-ui.card title="WordPress plugin" subtitle="Install our plugin in WordPress, then paste these keys" x-show="tab==='wp'" x-cloak>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-night-300">Download &amp; install the plugin, then open wp-admin.</p>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="primary" size="sm" href="{{ route('domains.wp-plugin', $domain) }}">Download (.zip)</x-ui.button>
                    <x-ui.button variant="outline" size="sm" href="{{ $wpAdminUrl }}" target="_blank" rel="noopener noreferrer">Open wp-admin</x-ui.button>
                    <x-ui.button variant="primary" size="sm" href="{{ $wpAdminPluginInstallUrl }}" target="_blank" rel="noopener noreferrer">Install plugin</x-ui.button>
                    <x-ui.button variant="outline" size="sm" type="button" @click="verifyWordpress('{{ $domain->id }}')">Verify plugin</x-ui.button>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ([
                    ['Domain key', $domain->domain_key],
                    ['Secret key', $domain->secret_key],
                    ['Authentication key', $domain->authentication_key],
                ] as [$label, $value])
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-night-700 bg-night-900/60 px-4 py-3">
                        <div class="min-w-0">
                            <p class="brand-kpi-label">{{ $label }}</p>
                            <p class="mt-1 truncate font-mono text-sm text-white">{{ $value }}</p>
                        </div>
                        <button type="button" class="brand-btn-soft px-3 py-2 text-xs" @click="copyText('{{ $value }}')">Copy</button>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- GTM --}}
        @php
            $gtmSnippet = "<script>(function(){var s=document.createElement('script');s.async=true;s.src='".url('/tag/' . $domain->domain_key . ".js")."';document.head.appendChild(s);}())</script>";
        @endphp
        <x-ui.card title="Google Tag Manager" subtitle="Add a Custom HTML tag with the snippet below" x-show="tab==='gtm'" x-cloak>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="brand-card-flat lg:col-span-1">
                    <p class="text-sm font-semibold text-white">Option 1 <span class="text-xs font-normal text-night-400">(recommended)</span></p>
                    <p class="mt-1 text-sm text-night-200">Direct Installation</p>
                    <button type="button" class="brand-btn-primary mt-4 w-full" disabled
                            title="This will be enabled when GTM auto-connect is implemented.">
                        Connect with Google Tag Manager
                    </button>
                    <p class="mt-3 text-xs text-night-400">Keep caching plugins from excluding this tag for accurate tracking.</p>
                </div>

                <div class="brand-card-flat lg:col-span-2">
                    <p class="text-sm font-semibold text-white">Manual installation</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-night-200">
                        <li>In Google Tag Manager select <span class="text-white">Custom HTML</span>.</li>
                        <li>Add the invocation tag provided below.</li>
                        <li>Check the <span class="text-white">support document.write</span> checkbox.</li>
                        <li>Set tag firing priority to <span class="text-white">9999</span>.</li>
                        <li>Set the trigger to fire on <span class="text-white">Initialization — All Pages</span>.</li>
                        <li>Save and publish the changes.</li>
                    </ol>
                </div>
            </div>

            <div class="mt-4">
                <label class="brand-label mb-1.5">GTM container ID</label>
                <div class="mb-4 flex gap-2">
                    <input id="gtm-container-id" type="text" value="{{ $domain->gtm_container_id }}" placeholder="GTM-XXXXXXX" class="brand-input">
                    <x-ui.button type="button" variant="primary" @click="saveGtm('{{ $domain->id }}')">Save</x-ui.button>
                </div>
                <p class="brand-label mb-1.5">Invocation tag</p>
                <div class="flex gap-2">
                    <textarea readonly rows="5" class="brand-input font-mono text-xs">{{ $gtmSnippet }}</textarea>
                    <x-ui.button type="button" variant="primary" @click="copyText(@js($gtmSnippet))">Copy</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        {{-- Tracking params --}}
        <x-ui.card title="Tracking parameters" subtitle="Choose which UTM keys are forwarded">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term'] as $param)
                    <label class="flex items-center gap-2 rounded-xl border border-night-700 bg-night-900/60 px-3 py-2 text-sm text-night-100">
                        <input type="checkbox" class="utm-toggle rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400"
                               data-param="{{ $param }}"
                               @checked(($domain->tracking_params[$param] ?? true) === true)>
                        {{ $param }}
                    </label>
                @endforeach
            </div>
            <div class="mt-4">
                <x-ui.button type="button" variant="primary" size="sm" @click="saveTrackingParams('{{ $domain->id }}')">Save tracking params</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Email developer --}}
        <x-ui.card title="Email developer" subtitle="Send install instructions for this domain" x-show="tab==='email'" x-cloak>
            <div class="flex gap-2">
                <input id="developer-email" type="email" placeholder="developer@company.com" class="brand-input">
                <x-ui.button type="button" variant="primary" @click="sendDeveloperEmail('{{ $domain->id }}')">Send</x-ui.button>
            </div>
        </x-ui.card>
    </div>
    </section>
</div>

    <script>
        function domainSetup() {
            return {
                tab: 'manual',
                toast: { open: false, message: '' },
                showToast(message) {
                    this.toast.message = message;
                    this.toast.open = true;
                    clearTimeout(this._toastTimer);
                    this._toastTimer = setTimeout(() => (this.toast.open = false), 2000);
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
                        el.style.top = '0';
                        el.style.left = '-9999px';
                        document.body.appendChild(el);
                        el.focus();
                        el.select();
                        el.setSelectionRange(0, el.value.length);
                        const ok = document.execCommand('copy');
                        document.body.removeChild(el);
                        if (ok) { this.showToast('Copied'); return; }
                    } catch (e) {}
                    try { window.prompt('Copy to clipboard:', text); this.showToast('Copy manually'); } catch (e) {}
                },
                async saveGtm(domainId) {
                    const value = document.getElementById('gtm-container-id')?.value || '';
                    const res = await fetch(`/domains/${domainId}/gtm`, {
                        method: 'PUT',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
                        body: JSON.stringify({gtm_container_id: value})
                    });
                    if (res.ok) this.showToast('GTM ID saved');
                },
                async saveTrackingParams(domainId) {
                    const toggles = Array.from(document.querySelectorAll('.utm-toggle'));
                    const tracking_params = {};
                    toggles.forEach((el) => tracking_params[el.dataset.param] = !!el.checked);
                    const res = await fetch(`/domains/${domainId}/tracking-params`, {
                        method: 'PUT',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
                        body: JSON.stringify({tracking_params})
                    });
                    if (res.ok) this.showToast('Tracking params saved');
                },
                async sendDeveloperEmail(domainId) {
                    const email = document.getElementById('developer-email')?.value || '';
                    const res = await fetch(`/domains/${domainId}/email-developer`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
                        body: JSON.stringify({email})
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
                }
            };
        }
    </script>
@endsection
