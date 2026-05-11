@props([
    'label' => '',
    'value' => '—',
    'tone'  => 'neutral', // forwarded to optional pill on the right
    'pill'  => null,      // optional pill text
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-4 border-b border-night-700/60 py-3 last:border-0']) }}>
    <span class="text-sm text-night-300">{{ $label }}</span>
    <div class="flex items-center gap-2">
        <span class="text-sm font-semibold text-white">{{ $value }}</span>
        @if ($pill)
            <x-ui.pill :tone="$tone">{{ $pill }}</x-ui.pill>
        @endif
    </div>
</div>
