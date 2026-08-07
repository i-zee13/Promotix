{{-- Nano Google Ads reconnect chip — keep inline styles for Vite-staleness safety. --}}
@php
    $chipHref = $href ?? route('integrations.google.redirect');
    $chipLabel = $label ?? 'Reconnect';
    $chipTitle = $title ?? 'Reconnect Google Ads — token expired or sync blocked';
    $chipClass = $class ?? '';
@endphp
<a
    href="{{ $chipHref }}"
    class="google-reconnect-chip {{ $chipClass }}"
    title="{{ $chipTitle }}"
>
    <svg class="google-reconnect-chip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M20 9A8 8 0 006.34 6.34M4 15a8 8 0 0013.66 2.66"/>
    </svg>
    <span>{{ $chipLabel }}</span>
</a>
<style>
    .google-reconnect-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 20px;
        padding: 0 7px;
        border-radius: 4px;
        border: 1px solid rgba(251, 191, 36, 0.45);
        background: rgba(251, 191, 36, 0.14);
        color: #fcd34d;
        font-size: 10px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
    }
    .google-reconnect-chip:hover {
        background: rgba(251, 191, 36, 0.24);
        color: #fde68a;
    }
    .google-reconnect-chip__icon {
        width: 10px;
        height: 10px;
        flex-shrink: 0;
    }
    html.light-mode .google-reconnect-chip {
        border-color: rgba(180, 83, 9, 0.35);
        background: rgba(251, 191, 36, 0.2);
        color: #b45309;
    }
</style>
