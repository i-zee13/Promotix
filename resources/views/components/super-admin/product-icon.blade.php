@props(['size' => 63])

@php
    $src = asset('images/icons8-product-100.png');
    $hasIcon = file_exists(public_path('images/icons8-product-100.png'));
@endphp

<span {{ $attributes->class(['figma-sa-products-icon']) }} aria-hidden="true">
    @if ($hasIcon)
        <img
            src="{{ $src }}"
            alt=""
            class="figma-sa-products-icon-img"
            width="{{ $size }}"
            height="{{ $size }}"
            loading="lazy"
            decoding="async"
        />
    @else
        {{-- Figma: icons8-product-100 (cardboard box) --}}
        <svg class="figma-sa-products-icon-fallback" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect width="100" height="100" rx="6" fill="#ece8e1"/>
            <path d="M18 36L50 20L82 36V78H18V36Z" fill="#c9a66b" stroke="#a8844f" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M18 36L50 52L82 36" stroke="#a8844f" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M50 52V78" stroke="#a8844f" stroke-width="1.5"/>
            <rect x="42" y="44" width="16" height="5" rx="1" fill="#e8dcc8" stroke="#b99862" stroke-width="1"/>
            <path d="M24 42H38M62 42H76" stroke="#b99862" stroke-width="2" stroke-linecap="round" opacity="0.55"/>
        </svg>
    @endif
</span>
