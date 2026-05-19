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
        <svg class="figma-sa-products-icon-fallback" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect width="100" height="100" rx="8" fill="#1a1a1a"/>
            <path d="M28 38h44v36H28V38z" fill="#6a0cb3"/>
            <path d="M32 34l18-10 18 10v4H32v-4z" fill="#d9d9d9"/>
            <rect x="38" y="48" width="24" height="4" rx="1" fill="#d9d9d9"/>
        </svg>
    @endif
</span>
