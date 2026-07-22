@props([
    'tone' => 'deactivated',
    'label' => '',
])

<span {{ $attributes->merge(['class' => 'figma-sa-subs-status-pill is-tone-' . $tone]) }}>{{ $label !== '' ? $label : $slot }}</span>
