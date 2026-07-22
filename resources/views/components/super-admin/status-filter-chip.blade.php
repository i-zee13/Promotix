@props([
    'tone' => 'all',
    'label' => '',
])

<button type="button" {{ $attributes->merge(['class' => 'figma-sa-users-filter-option']) }}>
    {{ $label !== '' ? $label : $slot }}
</button>
