@auth
    @php
        $headerUser = auth()->user();
        $headerTz = $headerUser?->timezone;
        $headerTzId = $headerTz ? \App\Support\UserTimezone::forUser($headerUser) : null;
    @endphp
    @if ($headerTz && $headerTzId)
        <a
            href="{{ route('profile.edit') }}#timezone-settings"
            id="header-timezone"
            class="figma-header-timezone hidden h-[27px] shrink-0 items-center gap-[6px] rounded-[3px] border border-[#6400B2] bg-[#0D0D0D] px-[8px] text-[10px] text-white/85 hover:border-[#7B13C8] hover:text-white sm:inline-flex"
            title="{{ \App\Support\UserTimezone::headerTitle($headerUser) }}"
            data-timezone="{{ $headerTzId }}"
        >
            <svg class="h-[13px] w-[13px] shrink-0 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
            </svg>
            <span class="hidden max-w-[140px] truncate md:inline" id="header-timezone-name">{{ $headerTz }}</span>
            <span class="hidden text-white/45 md:inline">·</span>
            <span id="header-timezone-clock" class="whitespace-nowrap">{{ \App\Support\UserTimezone::headerLabel($headerUser) }}</span>
        </a>
    @endif
@endauth
