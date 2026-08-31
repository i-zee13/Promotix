{{-- Replaces “Digital Promotix” text in the account rightbar --}}
@php
    $canInviteTeam = auth()->user()?->canInviteTeamMembers() ?? false;
    $guidanceChatActive = $guidanceChatActive ?? (
        auth()->user()
            ? \App\Support\AdminIntegrationCatalog::guidanceChatbotEnabledForUser(auth()->id())
            : false
    );
@endphp
<div class="figma-rightbar-center mb-[16px]" id="rightbar-notify-root">
    {{-- Full class names required: Tailwind JIT does not emit dynamic grid-cols-{{ n }} --}}
    <div
        class="mx-auto grid w-full max-w-[168px] gap-[9px] {{ $guidanceChatActive ? 'grid-cols-4' : 'grid-cols-3' }}"
        role="toolbar"
        aria-label="Quick panel actions"
    >
        <button
            type="button"
            id="rightbar-notify-toggle"
            class="figma-rightbar-icon-btn relative"
            title="Notifications"
            aria-label="Toggle notifications"
            aria-expanded="false"
            aria-controls="right-notifications"
        >
            <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"/>
            </svg>
            <span id="rightbar-notify-dot" class="absolute right-[5px] top-[5px] h-[6px] w-[6px] rounded-full bg-white" hidden aria-hidden="true"></span>
        </button>

        @if ($guidanceChatActive)
            <button
                type="button"
                @click="$dispatch('open-live-agent')"
                class="figma-rightbar-icon-btn"
                title="Messages"
                aria-label="Live agent chat"
            >
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 5h16v11H8l-4 4V5z"/>
                </svg>
            </button>
        @endif

        @if ($canInviteTeam)
            <button
                type="button"
                class="figma-rightbar-icon-btn"
                title="Invite teammate"
                aria-label="Invite teammate"
                onclick="window.dispatchEvent(new CustomEvent('open-portal-team-invite'))"
            >
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M18 9v3m0 0v3m0-3h3m-3 0h-3M8 11a4 4 0 100-8 4 4 0 000 8zM6 14a6 6 0 00-6 6h8"/>
                </svg>
            </button>
        @else
            <a
                href="{{ route('billing.index') }}"
                class="figma-rightbar-icon-btn figma-rightbar-icon-btn--muted"
                title="Invite teammates — Enterprise, Advanced, or Custom plan"
                aria-label="Upgrade to invite teammates"
            >
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M18 9v3m0 0v3m0-3h3m-3 0h-3M8 11a4 4 0 100-8 4 4 0 000 8zM6 14a6 6 0 00-6 6h8"/>
                </svg>
            </a>
        @endif

        <a
            href="{{ route('profile.edit') }}"
            class="figma-rightbar-icon-btn"
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

    <div
        id="right-notifications"
        class="figma-rightbar-notify mt-[12px] hidden w-full max-w-[168px] max-h-[240px] space-y-[10px] overflow-y-auto border-b-2 border-[#5a2a99] pb-[12px] text-[9px] text-[#a9a9a9] promotix-slim-scroll"
        hidden
        role="region"
        aria-label="Notifications"
    ></div>
</div>
