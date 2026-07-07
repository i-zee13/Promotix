@props([
    'title',
    'value',
])

<div
    {{ $attributes->merge(['class' => 'brand-kpi rounded-2xl border border-brand-500/30 bg-brand-500 p-5 text-white shadow-card']) }}
>
    <p class="text-sm font-medium opacity-90">{{ $title }}</p>
    <p class="mt-2 text-2xl font-bold tracking-tight">{{ $value }}</p>
</div>
