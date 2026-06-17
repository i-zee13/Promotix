@php
    $darkLogoPath = public_path('images/logo.png');
    $lightLogoPath = public_path('images/logo-light.svg');
    $darkLogoUrl = url('/images/logo.png') . (is_file($darkLogoPath) ? '?v=' . filemtime($darkLogoPath) : '');
    $lightLogoUrl = is_file($lightLogoPath)
        ? url('/images/logo-light.svg') . '?v=' . filemtime($lightLogoPath)
        : $darkLogoUrl;
@endphp
<img
    src="{{ $darkLogoUrl }}"
    alt="Digital Promotix"
    width="236"
    height="96"
    class="figma-sidebar-logo figma-sidebar-logo--dark mx-auto h-[48px] w-[118px] object-contain"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
<img
    src="{{ $lightLogoUrl }}"
    alt="Digital Promotix"
    width="236"
    height="96"
    class="figma-sidebar-logo figma-sidebar-logo--light mx-auto h-[48px] w-[118px] object-contain"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
