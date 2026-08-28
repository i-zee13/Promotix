@php
    $logoPath = public_path('images/clickronix-logo.png');
    $logoUrl = url('/images/clickronix-logo.png') . (is_file($logoPath) ? '?v=' . filemtime($logoPath) : '');
@endphp
<img
    src="{{ $logoUrl }}"
    alt="Clickronix"
    width="180"
    height="48"
    class="figma-sidebar-logo mx-auto h-[40px] w-auto max-w-[180px] object-contain object-left"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
