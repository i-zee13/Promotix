@props([
    'variant' => 'default', // default | flat | elevated
    'title' => null,
    'subtitle' => null,
])

@php
    $variantClass = match ($variant) {
        'flat'     => 'brand-card-flat',
        'elevated' => 'brand-card-elevated',
        default    => 'brand-card',
    };
@endphp

<section {{ $attributes->merge(['class' => $variantClass]) }}>
    @if ($title || $subtitle || isset($actions))
        <header class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-lg font-semibold text-white">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-1 text-sm text-night-300">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}
</section>
