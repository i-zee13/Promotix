@props([
    'title',
    'value',
])

<div
    {{ $attributes->merge(['class' => 'rounded-[20px] bg-accent p-5 text-white']) }}
>
    <p class="text-sm font-medium opacity-90">{{ $title }}</p>
    <p class="mt-2 text-2xl font-bold tracking-tight">{{ $value }}</p>
</div>
