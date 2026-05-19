@props([
    'checked' => false,
    'name' => null,
    'value' => '1',
    'labelOn' => 'Active',
    'labelOff' => 'Off',
    'disabled' => false,
])

@php
    $id = $attributes->get('id', 'figma-toggle-' . uniqid());
@endphp

<label @class(['figma-toggle', 'figma-toggle--disabled' => $disabled]) for="{{ $id }}">
    <input
        type="checkbox"
        id="{{ $id }}"
        {{ $attributes->class('figma-toggle-input sr-only') }}
        @checked($checked)
        @disabled($disabled)
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
    />
    <span class="figma-toggle-track" aria-hidden="true">
        <span class="figma-toggle-thumb"></span>
    </span>
    <span class="figma-toggle-label figma-toggle-label--on">{{ $labelOn }}</span>
    <span class="figma-toggle-label figma-toggle-label--off">{{ $labelOff }}</span>
</label>
