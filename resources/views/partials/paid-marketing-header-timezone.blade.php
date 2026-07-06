{{-- Compact paid-marketing timezone chip for top header (Alpine store: paidMarketingTimezone) --}}
<div
    x-data
    x-show="$store.paidMarketingTimezone.visible"
    x-cloak
    class="figma-header-timezone hidden h-[27px] max-w-[min(52vw,320px)] shrink-0 items-center gap-[5px] rounded-[3px] border border-[#6400B2] bg-[#0D0D0D] px-[8px] text-[10px] text-white/85 sm:inline-flex"
    :title="$store.paidMarketingTimezone.title"
>
    <svg class="h-[13px] w-[13px] shrink-0 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
    </svg>
    <span class="shrink-0 font-medium text-white" x-text="$store.paidMarketingTimezone.googleAbbr"></span>
    <span class="shrink-0 text-white/45">·</span>
    <span class="min-w-0 truncate" x-text="$store.paidMarketingTimezone.visitsShort"></span>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('paidMarketingTimezone', {
        visible: false,
        googleAbbr: '—',
        visitsShort: '',
        title: '',
    });
});

window.promotixSyncPaidTimezoneHeader = function (chip, panel) {
    if (typeof Alpine === 'undefined') return;

    const store = Alpine.store('paidMarketingTimezone');
    const googleFull = chip?.timezone || '';
    const abbrMatch = googleFull.match(/\(([^)]+)\)/);
    const googleAbbr = abbrMatch?.[1] || googleFull.split('/').pop() || '—';

    let visitsShort = '';
    const visitLine = panel?.visitLine || '';
    if (visitLine) {
        const parts = visitLine.split(' · ');
        const tzLabel = (parts[0] || '').replace(/\s*\([^)]*\)\s*$/, '').trim();
        const dates = (parts[1] || '').replace(/2026-/g, '').replace(/-/g, '/');
        visitsShort = dates ? `${tzLabel} ${dates}` : tzLabel;
    }

    store.googleAbbr = googleAbbr;
    store.visitsShort = visitsShort || '—';
    store.title = [
        chip?.hostname ? `Domain: ${chip.hostname}` : '',
        googleFull ? `Google Ads: ${googleFull}` : '',
        chip?.account || '',
        visitLine ? `Visits: ${visitLine}` : '',
    ].filter(Boolean).join('\n');
    store.visible = !!(googleFull || visitLine);
};
</script>
