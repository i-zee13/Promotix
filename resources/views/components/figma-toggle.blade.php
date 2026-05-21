@props([
    'checked' => false,
    'name' => null,
    'value' => '1',
    'labelOn' => 'Active',
    'labelOff' => 'Off',
    'disabled' => false,
    'variant' => 'dark',
])

@php
    $id = $attributes->get('id', 'figma-toggle-' . uniqid());
@endphp

<label @class([
    'figma-toggle',
    'figma-toggle--on-light' => $variant === 'on-light',
    'figma-toggle--disabled' => $disabled,
]) for="{{ $id }}">
    <input
        type="checkbox"
        id="{{ $id }}"
        {{ $attributes->class('figma-toggle-input') }}
        @checked($checked)
        @disabled($disabled)
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
    />
    <span class="figma-toggle-track pointer-events-none" aria-hidden="true">
        <span class="figma-toggle-thumb"></span>
    </span>
    <span class="figma-toggle-label figma-toggle-label--on">{{ $labelOn }}</span>
    <span class="figma-toggle-label figma-toggle-label--off">{{ $labelOff }}</span>
</label>
