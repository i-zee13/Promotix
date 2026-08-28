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
    $darkLogoPath = public_path('images/clickronix-logo-dark.png');
    $lightLogoPath = public_path('images/clickronix-logo-light.png');
    $darkLogoUrl = url('/images/clickronix-logo-dark.png') . (is_file($darkLogoPath) ? '?v=' . filemtime($darkLogoPath) : '');
    $lightLogoUrl = is_file($lightLogoPath)
        ? url('/images/clickronix-logo-light.png') . '?v=' . filemtime($lightLogoPath)
        : $darkLogoUrl;
    $logoUrl = in_array($variant, ['light'], true) ? $lightLogoUrl : $darkLogoUrl;
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="Clickronix">
    <img
        src="{{ $logoUrl }}"
        alt="Clickronix"
        class="block w-auto max-w-full object-contain object-left"
        style="height: {{ (int) $height }}px;"
        loading="eager"
        decoding="async"
        referrerpolicy="no-referrer"
    >
</a>
