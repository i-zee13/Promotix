@php
    $brandName = \App\Support\PortalBrand::name();
    $logos = \App\Support\PortalBrand::logoUrls();
@endphp
@if ($logos)
<img
    src="{{ $logos['dark'] }}"
    alt="{{ $brandName }}"
    width="490"
    height="175"
    class="figma-sidebar-logo figma-sidebar-logo--dark h-[44px] w-auto max-w-[220px] object-contain object-left"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
<img
    src="{{ $logos['light'] }}"
    alt="{{ $brandName }}"
    width="490"
    height="175"
    class="figma-sidebar-logo figma-sidebar-logo--light h-[44px] w-auto max-w-[220px] object-contain object-left"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
@else
<span class="figma-sidebar-wordmark inline-flex items-center gap-[8px] text-white">
    @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[22px] w-[22px] shrink-0 text-[var(--brand-primary,#FF6600)]'])
    <span class="text-[15px] font-extrabold uppercase tracking-[0.06em]">{{ $brandName }}</span>
</span>
@endif
