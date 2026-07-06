{{-- Requires Alpine getters: domainTimezoneChip, timezoneContextPanel --}}
<div class="mb-[14px]">
    <div
        x-show="domainTimezoneChip || timezoneContextPanel"
        x-cloak
        class="flex w-full max-w-[520px] flex-col gap-[10px] rounded-[10px] border border-[#6400B2]/55 bg-[#1a1028] px-[12px] py-[9px] sm:flex-row sm:items-center sm:gap-[14px]"
    >
        <div class="flex min-w-0 flex-1 items-start gap-[8px]">
            <span class="inline-flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-[#6400B2] text-white">
                <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-[#c4b5fd]">
                    Google Ads account timezone
                    <span class="font-normal normal-case text-white/45" x-show="domainTimezoneChip?.hostname" x-text="'· ' + (domainTimezoneChip?.hostname || '')"></span>
                </p>
                <p class="mt-[2px] text-[13px] font-semibold leading-snug text-white" x-text="domainTimezoneChip?.timezone || '—'"></p>
                <p
                    x-show="domainTimezoneChip?.account"
                    class="mt-[1px] truncate text-[10px] text-white/55"
                    x-text="domainTimezoneChip?.account"
                ></p>
            </div>
            <span
                x-show="domainTimezoneChip && !domainTimezoneChip.hasTimezone"
                class="shrink-0 rounded-[4px] bg-amber-500/15 px-[8px] py-[4px] text-[9px] font-semibold uppercase text-amber-200"
            >Sync required</span>
        </div>

        <div
            x-show="timezoneContextPanel?.visitLine"
            class="min-w-0 shrink-0 border-white/10 sm:border-l sm:pl-[14px]"
        >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-[#c4b5fd]">Visits counted in</p>
            <p class="mt-[2px] text-[11px] font-medium leading-snug text-white" x-text="timezoneContextPanel?.visitLine"></p>
        </div>
    </div>
</div>
