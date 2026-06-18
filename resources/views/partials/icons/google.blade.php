@props(['class' => 'h-[55px] w-[55px]'])

@php
    $iconPath = public_path('images/google.svg');
    $iconSrc = url('/images/google.svg') . (is_file($iconPath) ? '?v=' . filemtime($iconPath) : '');
@endphp

<img
    src="{{ $iconSrc }}"
    alt=""
    {{ $attributes->merge(['class' => trim($class . ' object-contain shrink-0')]) }}
    width="48"
    height="48"
    aria-hidden="true"
    loading="lazy"
/>
