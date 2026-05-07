@extends('layouts.admin')

@section('title', 'Implement your tracking tag')

@section('content')
    <div class="space-y-6" x-data="domainSetup()">
        <div
            class="fixed bottom-4 right-4 z-[60] rounded-xl border border-dark-border bg-dark-card px-4 py-3 text-sm text-white shadow-lg"
            x-show="toast.open"
            x-cloak
            x-transition
        >
            <span x-text="toast.message"></span>
        </div>
        <div class="rounded-xl border border-dark-border bg-dark-card p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-400">Domain</p>
                    <p class="text-lg font-semibold text-white">{{ $domain->hostname }}</p>
                </div>
                <a href="{{ route('domains.index') }}" class="rounded-xl border border-dark-border px-4 py-2 text-sm font-medium text-gray-300 hover:bg-dark-border">Back to domains</a>
            </div>

            <div class="mt-6">
                <p class="text-sm text-gray-300">Select setup method</p>
                <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <button type="button" @click="tab='gtm'" class="rounded-xl border border-dark-border bg-dark p-4 text-left hover:bg-dark-border" :class="tab==='gtm' ? 'ring-1 ring-accent' : ''">
                        <p class="font-medium text-white">Google Tag Manager</p>
                        <p class="mt-1 text-xs text-gray-500">Provide GTM snippet</p>
                    </button>
                    <button type="button" @click="tab='wp'" class="rounded-xl border border-dark-border bg-dark p-4 text-left hover:bg-dark-border" :class="tab==='wp' ? 'ring-1 ring-accent' : ''">
                        <p class="font-medium text-white">WordPress Plugin</p>
                        <p class="mt-1 text-xs text-gray-500">Keys for WP plugin</p>
                    </button>
                    <button type="button" @click="tab='manual'" class="rounded-xl border border-dark-border bg-dark p-4 text-left hover:bg-dark-border" :class="tab==='manual' ? 'ring-1 ring-accent' : ''">
                        <p class="font-medium text-white">Direct installation</p>
                        <p class="mt-1 text-xs text-gray-500">Paste into site</p>
                    </button>
                    <button type="button" @click="tab='email'" class="rounded-xl border border-dark-border bg-dark p-4 text-left hover:bg-dark-border" :class="tab==='email' ? 'ring-1 ring-accent' : ''">
                        <p class="font-medium text-white">Email my developer</p>
                        <p class="mt-1 text-xs text-gray-500">Send installation instructions</p>
                    </button>
                </div>
            </div>
        </div>

        {{-- Manual (Direct) --}}
        <section class="rounded-xl border border-dark-border bg-dark-card p-6" x-show="tab==='manual'" x-cloak>
            <h2 class="text-lg font-semibold text-white">Direct installation</h2>
            <p class="mt-1 text-sm text-gray-400">Paste the code at the beginning of the <code>&lt;head&gt;</code>.</p>

            @php
                $scriptUrl = url('/tag/' . $domain->domain_key . '.js');
                $noscriptUrl = url('/tag/' . $domain->domain_key . '.html');
            @endphp

            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-xs text-gray-500 mb-2">Head script</p>
                    <div class="flex gap-2">
                        <textarea readonly rows="3" class="w-full rounded-xl border border-dark-border bg-dark p-3 font-mono text-xs text-white focus:outline-none">&lt;script async src=&quot;{{ $scriptUrl }}&quot; class=&quot;pm_tag&quot;&gt;&lt;/script&gt;</textarea>
                        <button type="button" class="shrink-0 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                                @click="copyText(`<script async src=&quot;{{ $scriptUrl }}&quot; class=&quot;pm_tag&quot;></script>`)">
                            Copy
                        </button>
                    </div>
                </div>

                <div>
                    <p class="text-xs text-gray-500 mb-2">Body noscript</p>
                    <div class="flex gap-2">
                        <textarea readonly rows="3" class="w-full rounded-xl border border-dark-border bg-dark p-3 font-mono text-xs text-white focus:outline-none">&lt;noscript&gt;&lt;iframe src=&quot;{{ $noscriptUrl }}&quot; width=&quot;0&quot; height=&quot;0&quot; style=&quot;display:none&quot;&gt;&lt;/iframe&gt;&lt;/noscript&gt;</textarea>
                        <button type="button" class="shrink-0 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                                @click="copyText(`<noscript><iframe src=&quot;{{ $noscriptUrl }}&quot; width=&quot;0&quot; height=&quot;0&quot; style=&quot;display:none&quot;></iframe></noscript>`)">
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- WP Plugin --}}
        <section class="rounded-xl border border-dark-border bg-dark-card p-6" x-show="tab==='wp'" x-cloak>
            <h2 class="text-lg font-semibold text-white">WordPress Plugin</h2>
            <p class="mt-1 text-sm text-gray-400">Install our plugin in WordPress, then paste these keys in the plugin settings.</p>

            @php
                // Mirrors the "install plugin" jump in ClickCease.
                // Update the plugin slug when your WP plugin slug is finalized.
                $wpPluginSlug = 'promotix-tag';
                $wpBase = 'https://' . $domain->hostname;
                $wpAdminUrl = $wpBase . '/wp-admin/';

                // If the user is not logged in, WP will show login and then redirect to the installer.
                // This is the closest we can get to "one click install" without handling credentials.
                $wpPluginInstallPath = '/wp-admin/plugin-install.php?tab=plugin-information&plugin=' . $wpPluginSlug;
                $wpAdminPluginInstallUrl = $wpBase . '/wp-login.php?redirect_to=' . urlencode($wpBase . $wpPluginInstallPath);
            @endphp

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs text-gray-500">Instructions</p>
                    <p class="text-sm text-gray-300">Download and install the plugin, then open wp-admin.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('domains.wp-plugin', $domain) }}" class="rounded-xl bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                        Download plugin (.zip)
                    </a>
                    <a href="{{ $wpAdminUrl }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-xl border border-dark-border bg-dark px-4 py-2 text-sm font-medium text-gray-200 hover:bg-dark-border">
                        Open wp-admin
                    </a>
                    <a href="{{ $wpAdminPluginInstallUrl }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-xl bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                        Install plugin
                    </a>
                    <button type="button" class="rounded-xl border border-dark-border bg-dark px-4 py-2 text-sm font-medium text-gray-200 hover:bg-dark-border"
                            @click="verifyWordpress('{{ $domain->id }}')">
                        Verify plugin
                    </button>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between gap-3 rounded-xl border border-dark-border bg-dark p-4">
                    <div>
                        <p class="text-xs text-gray-500">Domain key</p>
                        <p class="font-mono text-sm text-white">{{ $domain->domain_key }}</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-2 text-sm text-accent hover:bg-accent/10" @click="copyText('{{ $domain->domain_key }}')">Copy</button>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-xl border border-dark-border bg-dark p-4">
                    <div>
                        <p class="text-xs text-gray-500">Secret key</p>
                        <p class="font-mono text-sm text-white">{{ $domain->secret_key }}</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-2 text-sm text-accent hover:bg-accent/10" @click="copyText('{{ $domain->secret_key }}')">Copy</button>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-xl border border-dark-border bg-dark p-4">
                    <div>
                        <p class="text-xs text-gray-500">Authentication key</p>
                        <p class="font-mono text-sm text-white">{{ $domain->authentication_key }}</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-2 text-sm text-accent hover:bg-accent/10" @click="copyText('{{ $domain->authentication_key }}')">Copy</button>
                </div>
            </div>
        </section>

        {{-- GTM --}}
        <section class="rounded-xl border border-dark-border bg-dark-card p-6" x-show="tab==='gtm'" x-cloak>
            <h2 class="text-lg font-semibold text-white">Google Tag Manager</h2>
            <p class="mt-1 text-sm text-gray-400">Add a Custom HTML tag and paste the invocation tag below.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-dark-border bg-dark p-4 lg:col-span-1">
                    <p class="text-sm font-semibold text-white">Option 1 <span class="text-xs font-normal text-gray-500">(recommended)</span></p>
                    <p class="mt-1 text-sm text-gray-300">Direct Installation</p>
                    <button type="button"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                            disabled
                            title="This will be enabled when GTM auto-connect is implemented."
                    >
                        Connect with Google Tag Manager
                    </button>
                    <p class="mt-3 text-xs text-gray-500">
                        Keep caching plugins from excluding this tag for accurate tracking.
                    </p>
                </div>

                <div class="rounded-xl border border-dark-border bg-dark p-4 lg:col-span-2">
                    <p class="text-sm font-semibold text-white">Manual installation:</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-300">
                        <li>In your Google Tag Manager account, select <span class="text-white">Custom HTML</span>.</li>
                        <li>Add the invocation tag provided below.</li>
                        <li>Check the <span class="text-white">support document.write</span> checkbox.</li>
                        <li>Set the tag firing priority to <span class="text-white">9999</span>.</li>
                        <li>Set the triggering tag to fire on <span class="text-white">Initialization - All Pages</span>.</li>
                        <li>Save and publish the changes.</li>
                    </ol>
                </div>
            </div>

            @php
                $gtmSnippet = "<script>(function(){var s=document.createElement('script');s.async=true;s.src='".url('/tag/' . $domain->domain_key . ".js")."';document.head.appendChild(s);}())</script>";
            @endphp

            <div class="mt-4">
                <label class="mb-2 block text-xs text-gray-500">GTM container ID</label>
                <div class="mb-4 flex gap-2">
                    <input id="gtm-container-id" type="text" value="{{ $domain->gtm_container_id }}" placeholder="GTM-XXXXXXX" class="w-full rounded-xl border border-dark-border bg-dark p-3 text-sm text-white focus:outline-none">
                    <button type="button" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                            @click="saveGtm('{{ $domain->id }}')">Save</button>
                </div>
                <p class="mb-2 text-xs text-gray-500">Invocation tag</p>
                <div class="flex gap-2">
                    <textarea readonly rows="5" class="w-full rounded-xl border border-dark-border bg-dark p-3 font-mono text-xs text-white focus:outline-none">{{ $gtmSnippet }}</textarea>
                    <button type="button" class="shrink-0 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                            @click="copyText(@js($gtmSnippet))">
                        Copy
                    </button>
                </div>
            </div>
        </section>

        {{-- Tracking params --}}
        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <h2 class="text-lg font-semibold text-white">Tracking parameters setup</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term'] as $param)
                    <label class="flex items-center gap-2 rounded-lg border border-dark-border bg-dark px-3 py-2 text-sm text-gray-300">
                        <input type="checkbox" class="utm-toggle rounded border-dark-border bg-dark text-accent focus:ring-accent"
                               data-param="{{ $param }}"
                               @checked(($domain->tracking_params[$param] ?? true) === true)>
                        {{ $param }}
                    </label>
                @endforeach
            </div>
            <button type="button" class="mt-4 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                    @click="saveTrackingParams('{{ $domain->id }}')">Save tracking params</button>
        </section>

        {{-- Email developer --}}
        <section class="rounded-xl border border-dark-border bg-dark-card p-6" x-show="tab==='email'" x-cloak>
            <h2 class="text-lg font-semibold text-white">Email developer</h2>
            <p class="mt-1 text-sm text-gray-400">Send install instructions for this domain.</p>
            <div class="mt-4 flex gap-2">
                <input id="developer-email" type="email" placeholder="developer@company.com" class="w-full rounded-xl border border-dark-border bg-dark p-3 text-sm text-white focus:outline-none">
                <button type="button" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover"
                        @click="sendDeveloperEmail('{{ $domain->id }}')">Send</button>
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
                        if (ok) {
                            this.showToast('Copied');
                            return;
                        }
                    } catch (e) {}

                    // Last resort fallback
                    try {
                        window.prompt('Copy to clipboard:', text);
                        this.showToast('Copy manually');
                    } catch (e) {}
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

