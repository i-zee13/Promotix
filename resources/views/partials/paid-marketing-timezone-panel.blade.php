{{-- Requires Alpine getters: domainTimezoneChip, timezoneContextPanel --}}
<div class="mb-[14px] space-y-[8px]">
    <div
        x-show="domainTimezoneChip"
        x-cloak
        class="flex flex-wrap items-center gap-[8px] rounded-[10px] border border-[#6400B2]/55 bg-[#1a1028] px-[12px] py-[9px]"
    >
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
        x-show="timezoneContextPanel"
        x-cloak
        class="rounded-[10px] border border-[#6400B2]/35 bg-[#151515] px-[12px] py-[10px]"
    >
        <p class="text-[10px] font-semibold uppercase tracking-wide text-[#c4b5fd]">Reporting window</p>
        <div class="mt-[6px] grid gap-[6px] text-[11px] leading-relaxed text-[#a9a9a9] sm:grid-cols-2">
            <p>
                <span class="text-white/55">Visits counted in</span>
                <span class="ml-[4px] font-medium text-white" x-text="timezoneContextPanel?.visitLine"></span>
            </p>
            <p x-show="timezoneContextPanel?.googleLine">
                <span class="text-white/55">Google clicks in</span>
                <span class="ml-[4px] font-medium text-white" x-text="timezoneContextPanel?.googleLine"></span>
            </p>
        </div>
        <p
            x-show="timezoneContextPanel?.modeLabel"
            class="mt-[6px] text-[9px] text-white/40"
            x-text="'Profile setting: ' + (timezoneContextPanel?.modeLabel || '')"
        ></p>
    </div>
</div>
