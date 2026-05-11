@props([
    'variant' => 'primary',  // primary | soft | ghost | outline | danger
    'href'    => null,
    'type'    => 'button',
    'size'    => 'md',       // sm | md | lg
])

@php
    $variantClass = match ($variant) {
        'soft'    => 'brand-btn-soft',
        'ghost'   => 'brand-btn-ghost',
        'outline' => 'brand-btn-outline',
        'danger'  => 'brand-btn-danger',
        default   => 'brand-btn-primary',
    };
    $sizeClass = match ($size) {
        'sm' => 'px-3 py-2 text-xs',
        'lg' => 'px-5 py-3 text-base',
        default => '',
    };
    $classes = trim("{$variantClass} {$sizeClass}");
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
