{{-- Inline icons (no asset load). Usage: @include('partials.overview-suite-icon', ['name' => 'cursor-click', 'size' => 14]) --}}
@php
    $size = (int) ($size ?? 14);
@endphp
@switch($name ?? '')
    @case('cursor-click')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 3.5v6.2"/><path d="M4.8 9.7H11"/><path d="M5.6 5.6l3.4 3.4"/><path d="M12.2 10.2l8.3 3.1-3.4 1.5-1.5 3.4-3.4-8z"/>
        </svg>
        @break
    @case('shield-check')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3l7 3v5c0 4.5-2.8 7.8-7 9-4.2-1.2-7-4.5-7-9V6l7-3z"/><path d="M9.2 12.1l1.9 1.9 3.8-4"/>
        </svg>
        @break
    @case('ban')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="8"/><path d="M6.4 6.4l11.2 11.2"/>
        </svg>
        @break
    @case('gauge')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5.2 16.2a7.5 7.5 0 1113.6 0"/><path d="M12 14.5l3.2-4.2"/><circle cx="12" cy="15" r="1.2" fill="currentColor" stroke="none"/>
        </svg>
        @break
    @case('shield-dollar')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3l7 3v5c0 4.5-2.8 7.8-7 9-4.2-1.2-7-4.5-7-9V6l7-3z"/><path d="M12 8.2v7.2"/><path d="M14.2 10.1c0-.9-1-1.5-2.2-1.5s-2.2.6-2.2 1.5c0 .8.7 1.3 2.2 1.7s2.2.9 2.2 1.8c0 1-1 1.6-2.2 1.6s-2.2-.6-2.2-1.6"/>
        </svg>
        @break
    @case('users')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="9" cy="8.5" r="3"/><path d="M3.8 18.2c.7-2.5 2.7-3.9 5.2-3.9s4.5 1.4 5.2 3.9"/><circle cx="16.5" cy="9" r="2.3"/><path d="M15.2 14.4c1.8.2 3.3 1.2 4 3.2"/>
        </svg>
        @break
    @case('bug-scan')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 5.5l1.2 1.5h3.6L15 5.5"/><rect x="8" y="7" width="8" height="9.5" rx="3.5"/><path d="M8 11h8M12 7v9.5"/><path d="M5.5 10H8M16 10h2.5M5.5 14H8M16 14h2.5"/><circle cx="17.5" cy="17.5" r="2.6"/><path d="M19.3 19.3L21 21"/>
        </svg>
        @break
    @case('cpu-bolt')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 4v3M14 4v3M10 17v3M14 17v3M4 10h3M4 14h3M17 10h3M17 14h3"/><path d="M12.6 9.2L10.4 12.4h2.4L10.8 15.6"/>
        </svg>
        @break
    @case('lock')
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="6" y="11" width="12" height="9" rx="2"/><path d="M8.5 11V8.5a3.5 3.5 0 017 0V11"/><circle cx="12" cy="15.5" r="1.1" fill="currentColor" stroke="none"/>
        </svg>
        @break
    @default
        <svg class="ov-suite-icon-svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="7"/></svg>
@endswitch
