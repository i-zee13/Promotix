@props([
    // Visual context the brand mark is rendered against.
    //   'dark'   → white + orange logo for dark backgrounds
    //   'purple' → same as dark (readable on purple cards)
    //   'light'  → dark + orange logo for white / light backgrounds
    'variant' => 'dark',
    // Height in pixels. Width is auto.
    'height' => 40,
])

@php
    $brandName = \App\Support\PortalBrand::name();
    $logos = \App\Support\PortalBrand::logoUrls();
    $logoUrl = $logos
        ? ($variant === 'light' ? $logos['light'] : $logos['dark'])
        : null;
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="{{ $brandName }}">
    @if ($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt="{{ $brandName }}"
            class="block w-auto max-w-full object-contain object-left"
            style="height: {{ (int) $height }}px;"
            loading="eager"
            decoding="async"
            referrerpolicy="no-referrer"
        >
    @else
        <span class="inline-flex items-center gap-2 font-extrabold uppercase tracking-[0.06em] text-white" style="font-size: {{ max(14, (int) $height - 12) }}px;">
            {{ $brandName }}
        </span>
    @endif
</a>
