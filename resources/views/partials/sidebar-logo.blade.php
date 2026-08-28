@php
    $darkLogoPath = public_path('images/clickronix-logo-dark.png');
    $lightLogoPath = public_path('images/clickronix-logo-light.png');
    $darkLogoUrl = url('/images/clickronix-logo-dark.png') . (is_file($darkLogoPath) ? '?v=' . filemtime($darkLogoPath) : '');
    $lightLogoUrl = is_file($lightLogoPath)
        ? url('/images/clickronix-logo-light.png') . '?v=' . filemtime($lightLogoPath)
        : $darkLogoUrl;
@endphp
<img
    src="{{ $darkLogoUrl }}"
    alt="Clickronix"
    width="490"
    height="175"
    class="figma-sidebar-logo figma-sidebar-logo--dark h-[44px] w-auto max-w-[220px] object-contain object-left"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
<img
    src="{{ $lightLogoUrl }}"
    alt="Clickronix"
    width="490"
    height="175"
    class="figma-sidebar-logo figma-sidebar-logo--light h-[44px] w-auto max-w-[220px] object-contain object-left"
    loading="eager"
    decoding="async"
    referrerpolicy="no-referrer"
>
