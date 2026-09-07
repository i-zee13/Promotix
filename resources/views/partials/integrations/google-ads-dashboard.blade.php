{{-- Spec Image 1: corrected Google Ads-only Platform Integration cards --}}
@php
    $summary = $googleAdsSummary ?? [];
    $tracking = $trackingInstallation ?? [];
    $accountConnected = (bool) ($summary['account_connected'] ?? false);
    $oauthConnected = (bool) ($summary['connected'] ?? false);
@endphp

<div class="pi-first-row pi-first-row--spec">
    <section class="pi-connect-card">
        <div class="pi-spec-grid">
            {{-- Google Ads Account --}}
            <article class="pi-panel pi-panel--account">
                <div class="flex items-start justify-between gap-[10px]">
                    <div class="flex min-w-0 items-start gap-[14px]">
                        <div class="flex h-[56px] w-[56px] shrink-0 items-center justify-center rounded-[8px] bg-white">
                            @include('partials.icons.google', ['class' => 'h-[32px] w-[32px]'])
                        </div>
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold text-white">Google Ads</p>
                            <span class="pi-status-pill mt-[6px]" :class="googleAdsSummary.account_connected ? 'is-on' : 'is-off'">
                                <span class="pi-status-dot"></span>
                                <span x-text="googleAdsSummary.account_connected ? 'Account Connected' : (googleAdsSummary.connected ? 'OAuth only' : 'Not connected')"></span>
                            </span>
                            <p class="mt-[8px] truncate font-mono text-[11px] text-white/70" x-show="googleAdsSummary.customer_id">
                                Customer ID: <span class="text-white/90" x-text="googleAdsSummary.customer_id"></span>
                            </p>
                            <p class="mt-[4px] truncate text-[11px] text-white/55" x-show="googleAdsSummary.email" x-text="googleAdsSummary.email"></p>
                        </div>
                    </div>
                </div>
                <div class="mt-[14px] flex flex-wrap gap-[8px]">
                    <template x-if="googleAdsSummary.sync_url">
                        <form method="POST" :action="googleAdsSummary.sync_url">
                            @csrf
                            <button type="submit" class="pi-primary-btn">Campaign Sync</button>
                        </form>
                    </template>
                    <button type="button" class="pi-ghost-btn" @click="openIpExclusionsModal()">Protection Rules</button>
                    <button type="button" class="pi-text-link" @click="openConnectGoogleModal()">
                        <span x-text="googleAdsSummary.connected ? '+ Add Connection' : 'Connect Google Ads'"></span>
                    </button>
                </div>
            </article>

            {{-- Tracking Installation — Script / AW / GTM separate --}}
            <article class="pi-panel pi-panel--tracking">
                <div class="mb-[10px] flex items-center justify-between gap-[8px]">
                    <h3 class="text-[14px] font-semibold text-white">Tracking Installation</h3>
                    <button type="button" class="pi-primary-btn" @click="openInstallTagsModal()">Connect GTM</button>
                </div>
                <div class="space-y-[8px]">
                    <div class="pi-track-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-[12px] font-medium text-white">Google Tag</p>
                            <p class="truncate font-mono text-[10px] text-white/55" x-text="trackingInstallation.google_tag.id"></p>
                        </div>
                        <span class="pi-status-pill" :class="trackingInstallation.google_tag.ok ? 'is-on' : 'is-off'">
                            <span class="pi-status-dot"></span>
                            <span x-text="trackingInstallation.google_tag.status"></span>
                        </span>
                        <button type="button" class="pi-text-link shrink-0" @click="openInstallTagsModal('google_tag')">View details</button>
                    </div>
                    <div class="pi-track-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-[12px] font-medium text-white">GTM Container</p>
                            <p class="truncate font-mono text-[10px] text-white/55" x-text="trackingInstallation.gtm.id"></p>
                        </div>
                        <span class="pi-status-pill" :class="trackingInstallation.gtm.ok ? 'is-on' : (trackingInstallation.gtm.unpublished ? 'is-warn' : 'is-off')">
                            <span class="pi-status-dot"></span>
                            <span x-text="trackingInstallation.gtm.status"></span>
                        </span>
                        <button type="button" class="pi-text-link shrink-0" @click="openInstallTagsModal('gtm')">View details</button>
                    </div>
                    <div class="pi-track-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-[12px] font-medium text-white">Clickronix Script</p>
                            <p class="truncate font-mono text-[10px] text-white/55" x-text="trackingInstallation.script.id"></p>
                        </div>
                        <span class="pi-status-pill" :class="trackingInstallation.script.ok ? 'is-on' : 'is-off'">
                            <span class="pi-status-dot"></span>
                            <span x-text="trackingInstallation.script.status"></span>
                        </span>
                        <button type="button" class="pi-text-link shrink-0" @click="openInstallTagsModal('script')">View details</button>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <div class="pi-side-stack">
        <section class="pi-side-card">
            <div class="mb-[12px] flex items-center justify-between gap-[8px]">
                <h2 class="text-[14px] font-semibold text-white">Connection Health</h2>
                <a href="#connected-platforms" class="text-[11px] font-semibold text-[#B893D8] hover:text-white">View All</a>
            </div>
            <div class="space-y-[8px]">
                <template x-for="item in healthItems" :key="item.key">
                    <div class="pi-status-row">
                        <span class="min-w-0 flex-1 truncate text-[12px] text-white/90" x-text="item.label"></span>
                        <span class="pi-status-pill" :class="item.ok ? 'is-on' : (item.warn ? 'is-warn' : 'is-off')">
                            <span class="pi-status-dot"></span>
                            <span x-text="item.stateLabel || (item.ok ? 'Connected' : 'Pending')"></span>
                        </span>
                    </div>
                </template>
            </div>
            <button type="button" class="pi-text-link mt-[12px]" @click="openTestProtectionModal()">Test Integration →</button>
        </section>
    </div>
</div>

<style>
    .pi-first-row--spec {
        display: grid;
        gap: 14px;
        align-items: stretch;
    }
    @media (min-width: 1100px) {
        .pi-first-row--spec {
            grid-template-columns: minmax(0, 1.7fr) minmax(260px, 0.75fr);
        }
    }
    .pi-spec-grid {
        display: grid;
        gap: 12px;
    }
    @media (min-width: 900px) {
        .pi-spec-grid {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
        }
    }
    .pi-track-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(0, 0, 0, 0.22);
    }
    .pi-status-pill.is-warn {
        background: rgba(255, 102, 0, 0.22);
        color: #ffd0b0;
    }
</style>
