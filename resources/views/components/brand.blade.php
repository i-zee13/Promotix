@props([
    // Visual context the brand mark is rendered against.
    //   'dark'   → original purple logo (default — fits dark / black backgrounds)
    //   'purple' → inverted to white so it stays readable on the purple #6400B3 cards
    //   'light'  → dark text logo for white / light backgrounds
    'variant' => 'dark',
    // Height in pixels. Width is auto.
    'height' => 40,
])

@php
    $darkLogoPath = public_path('images/logo.png');
    $lightLogoPath = public_path('images/logo-light.svg');
    $darkLogoUrl = url('/images/logo.png') . (is_file($darkLogoPath) ? '?v=' . filemtime($darkLogoPath) : '');
    $lightLogoUrl = is_file($lightLogoPath)
        ? url('/images/logo-light.svg') . '?v=' . filemtime($lightLogoPath)
        : $darkLogoUrl;
    $logoUrl = $variant === 'light' ? $lightLogoUrl : $darkLogoUrl;
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="{{ config('app.name', 'Promotix') }}">
    <img
        src="{{ $logoUrl }}"
        alt="{{ config('app.name', 'Promotix') }}"
        class="block w-auto"
        style="height: {{ (int) $height }}px;"
        loading="eager"
        decoding="async"
        referrerpolicy="no-referrer"
    >
</a>
