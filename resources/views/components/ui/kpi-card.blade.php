@props([
    'label' => '',
    'value' => '—',
    'delta' => null,        // numeric or string like "+12.4%"
    'trend' => null,        // 'up' | 'down' | null
    'hint'  => null,        // small caption under value
    'icon'  => null,        // optional inline svg slot via $icon
    'id'    => null,        // attach an id to the value element so JS can update it
])

@php
    $deltaClass = $trend === 'up'
        ? 'brand-kpi-delta-up'
        : ($trend === 'down' ? 'brand-kpi-delta-down' : 'text-night-300');
@endphp

<article {{ $attributes->merge(['class' => 'brand-kpi']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="brand-kpi-label">{{ $label }}</p>
        @isset($icon)
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500/15 text-brand-200">
                {{ $icon }}
            </div>
        @endisset
    </div>

    <p @if($id) id="{{ $id }}" @endif class="brand-kpi-value">{{ $value }}</p>

    @if ($delta !== null || $hint)
        <div class="mt-2 flex items-center gap-2 text-xs">
            @if ($delta !== null)
                <span class="font-semibold {{ $deltaClass }}">{{ $delta }}</span>
            @endif
            @if ($hint)
                <span class="text-night-400">{{ $hint }}</span>
            @endif
        </div>
    @endif
</article>
