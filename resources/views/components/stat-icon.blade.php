@props([
    'name',
    'class' => 'h-6 w-6',
])

@php
    $icons = [
        'globe' => <<<'SVG'
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18" />
            <path d="M12 3a14.5 14.5 0 0 1 0 18 14.5 14.5 0 0 1 0-18z" />
            <path d="M7.5 5.5c1.8 1.2 2.7 3.5 2.7 6.5s-.9 5.3-2.7 6.5" />
            <path d="M16.5 5.5c-1.8 1.2-2.7 3.5-2.7 6.5s.9 5.3 2.7 6.5" />
        SVG,
        'shield' => <<<'SVG'
            <path d="M12 3 5 6.5v5.2c0 4.2 2.8 7.9 7 9.3 4.2-1.4 7-5.1 7-9.3V6.5L12 3z" />
            <path d="M9.2 12.2 11 14l3.8-4" />
        SVG,
        'ban' => <<<'SVG'
            <circle cx="12" cy="12" r="9" />
            <path d="M6.2 6.2 17.8 17.8" />
        SVG,
        'clipboard-list' => <<<'SVG'
            <path d="M9 4.5h6" />
            <path d="M10 3.5h4a1 1 0 0 1 1 1V5a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z" />
            <path d="M7.5 5.5h-1A2.5 2.5 0 0 0 4 8v10.5A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5V8a2.5 2.5 0 0 0-2.5-2.5h-1" />
            <path d="M8.5 11h7M8.5 14.5h7M8.5 18h4.5" />
        SVG,
        'ticket' => <<<'SVG'
            <path d="M4.5 8.2A2.2 2.2 0 0 1 6.7 6h10.6a2.2 2.2 0 0 1 2.2 2.2v1.1a1.7 1.7 0 0 0 0 3.4v1.1A2.2 2.2 0 0 1 17.3 16H6.7a2.2 2.2 0 0 1-2.2-2.2v-1.1a1.7 1.7 0 0 0 0-3.4V8.2z" />
            <path d="M14.5 8v1.2M14.5 12.8V14" />
            <circle cx="14.5" cy="11" r="0.9" />
        SVG,
        'check-badge' => <<<'SVG'
            <path d="M12 3.2 13.7 4l1.9-.4.9 1.7 1.8.6.2 1.9 1.5 1.1-.6 1.8.6 1.8-1.5 1.1-.2 1.9-1.8.6-.9 1.7-1.9-.4L12 20.8 10.3 20l-1.9.4-.9-1.7-1.8-.6-.2-1.9-1.5-1.1.6-1.8-.6-1.8 1.5-1.1.2-1.9 1.8-.6.9-1.7 1.9.4L12 3.2z" />
            <path d="m9.2 12.1 1.8 1.8 3.9-4" />
        SVG,
        'users' => <<<'SVG'
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 18.5c.6-2.6 2.7-4 5.5-4s4.9 1.4 5.5 4" />
            <circle cx="16.5" cy="8.5" r="2.4" />
            <path d="M15 14.2c1.9.3 3.4 1.3 4 3.3" />
        SVG,
        'alert-triangle' => <<<'SVG'
            <path d="M10.3 4.9 2.9 17.2A2 2 0 0 0 4.6 20h14.8a2 2 0 0 0 1.7-2.8L13.7 4.9a2 2 0 0 0-3.4 0z" />
            <path d="M12 9.2v4.2" />
            <circle cx="12" cy="16.2" r="0.9" fill="currentColor" stroke="none" />
        SVG,
        'hourglass' => <<<'SVG'
            <path d="M7 4h10" />
            <path d="M7 20h10" />
            <path d="M8 4c0 3.2 1.5 5 4 6.5C14.5 9 16 7.2 16 4" />
            <path d="M8 20c0-3.2 1.5-5 4-6.5C14.5 15 16 16.8 16 20" />
            <path d="M9.2 10.8h5.6" />
        SVG,
    ];

    $paths = $icons[$name] ?? $icons['globe'];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths !!}
</svg>
