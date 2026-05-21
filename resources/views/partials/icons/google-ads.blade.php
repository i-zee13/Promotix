@props(['class' => 'h-[48px] w-[48px]'])

@php
    $iconPath = public_path('images/google-ads.svg');
    $iconSrc = url('/images/google-ads.svg') . (is_file($iconPath) ? '?v=' . filemtime($iconPath) : '');
@endphp

<img
    src="{{ $iconSrc }}"
    alt=""
    {{ $attributes->merge(['class' => trim($class . ' object-contain shrink-0')]) }}
    width="64"
    height="64"
    aria-hidden="true"
    loading="lazy"
/>
