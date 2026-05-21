@php
    $logoPath = public_path('images/logo.png');
    $logoUrl = url('/images/logo.png') . (is_file($logoPath) ? '?v=' . filemtime($logoPath) : '');
@endphp
<img
    src="{{ $logoUrl }}"
    alt="Digital Promotix"
    width="236"
    height="96"
    class="figma-sidebar-logo mx-auto h-[48px] w-[118px] object-contain"
    loading="eager"
    decoding="async"
>
