@props([
    'tabs'   => [],          // [['label' => 'Daily', 'value' => 'daily'], ...]
    'active' => null,        // active value
    'name'   => null,        // optional input name (for form-driven tabs)
    'as'     => 'link',      // 'link' (with $base + ?$param=value) | 'button'
    'param'  => 'tab',       // query string key when as=link
    'base'   => null,        // base URL when as=link
])

@php
    $base = $base ?? request()->url();
@endphp

<div {{ $attributes->merge(['class' => 'brand-tabs']) }}>
    @foreach ($tabs as $tab)
        @php
            $value      = $tab['value'] ?? $tab['label'];
            $label      = $tab['label'];
            $isActive   = (string) $value === (string) $active;
            $classes    = $isActive ? 'brand-tab brand-tab-active' : 'brand-tab';
            $href       = $base . (str_contains($base, '?') ? '&' : '?') . $param . '=' . urlencode($value);
        @endphp

        @if ($as === 'link')
            <a href="{{ $href }}" class="{{ $classes }}">{{ $label }}</a>
        @else
            <button type="button" data-tab="{{ $value }}" @if($name)data-name="{{ $name }}"@endif class="{{ $classes }}">{{ $label }}</button>
        @endif
    @endforeach
</div>
