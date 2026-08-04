{{-- Replaces “Digital Promotix” text in the account rightbar --}}
<div class="mb-[16px] grid max-w-[168px] grid-cols-4 gap-[9px]" role="toolbar" aria-label="Quick panel actions">
    <a
        href="{{ route('dashboard') }}"
        class="relative flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]"
        title="Notifications"
        aria-label="Notifications"
    >
        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"/>
        </svg>
        <span class="absolute right-[5px] top-[5px] h-[6px] w-[6px] rounded-full bg-white" aria-hidden="true"></span>
    </a>

    <button
        type="button"
        @click="$dispatch('open-live-agent')"
        class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]"
        title="Messages"
        aria-label="Live agent chat"
    >
        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 5h16v11H8l-4 4V5z"/>
        </svg>
    </button>

    <button
        type="button"
        class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]"
        title="Share"
        aria-label="Share"
        onclick="navigator.clipboard?.writeText(window.location.href)"
    >
        <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="18" cy="5" r="2.4" stroke-width="1.7"/>
            <circle cx="6" cy="12" r="2.4" stroke-width="1.7"/>
            <circle cx="18" cy="19" r="2.4" stroke-width="1.7"/>
            <path stroke-linecap="round" stroke-width="1.7" d="M8.2 10.8l7.6-4.6M8.2 13.2l7.6 4.6"/>
        </svg>
    </button>

    <a
        href="{{ route('profile.edit') }}"
        class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]"
        title="More"
        aria-label="More options"
    >
        <svg class="h-[16px] w-[16px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="5" r="1.8"/>
            <circle cx="12" cy="12" r="1.8"/>
            <circle cx="12" cy="19" r="1.8"/>
        </svg>
    </a>
</div>
