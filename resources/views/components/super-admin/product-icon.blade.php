@props(['size' => 63, 'product' => null])

@php
    $slug = is_object($product) ? ($product->slug ?? '') : '';
@endphp

<span {{ $attributes->class(['figma-sa-products-icon']) }} aria-hidden="true">
    {{-- Vector mark (no raster) — matches Digital Promotix globe branding --}}
    <svg
        class="figma-sa-products-icon-fallback"
        width="{{ $size }}"
        height="{{ $size }}"
        viewBox="0 0 100 100"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="{{ is_object($product) ? ($product->name ?? 'Product') : 'Product' }}"
    >
        <rect width="100" height="100" rx="8" fill="#f3ecff"/>
        <g transform="translate(14, 14) scale(1.35)">
            <ellipse cx="22" cy="26" rx="9" ry="6.5" fill="#6400B2" transform="rotate(-18 22 26)"/>
            <ellipse cx="36" cy="16" rx="8" ry="5.5" fill="#7B13C8" transform="rotate(12 36 16)"/>
            <ellipse cx="40" cy="30" rx="9.5" ry="6.5" fill="#9A1AFF" transform="rotate(-8 40 30)"/>
            <ellipse cx="28" cy="38" rx="8.5" ry="5.5" fill="#6400B2" transform="rotate(22 28 38)"/>
            <ellipse cx="14" cy="32" rx="7" ry="5" fill="#7B13C8" transform="rotate(-30 14 32)"/>
            <ellipse cx="32" cy="24" rx="6.5" ry="4.5" fill="#9A1AFF" transform="rotate(8 32 24)"/>
            <path
                d="M22 26 L36 16 M36 16 L40 30 M40 30 L28 38 M28 38 L14 32 M14 32 L22 26 M32 24 L36 16 M32 24 L40 30"
                stroke="#6400B2"
                stroke-width="1.8"
                stroke-linecap="round"
            />
        </g>
        @if ($slug === 'clickronix')
            <circle cx="78" cy="22" r="10" fill="#6400B2"/>
            <path d="M74 22 L77 25 L83 19" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        @endif
    </svg>
</span>
