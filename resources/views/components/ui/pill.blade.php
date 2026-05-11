@props([
    'tone' => 'neutral', // neutral | purple | success | warning | danger
])

@php
    $toneClass = match ($tone) {
        'purple'  => 'brand-pill-purple',
        'success' => 'brand-pill-success',
        'warning' => 'brand-pill-warning',
        'danger'  => 'brand-pill-danger',
        default   => 'brand-pill-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => "brand-pill {$toneClass}"]) }}>
    {{ $slot }}
</span>
