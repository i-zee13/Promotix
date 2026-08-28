@php
    $iconPath = public_path('images/clickronix-icon.png');
    $iconUrl = url('/images/clickronix-icon.png') . (is_file($iconPath) ? '?v=' . filemtime($iconPath) : '');
@endphp
<span class="figma-sidebar-logo-lockup" aria-label="Clickronix">
    <img
        src="{{ $iconUrl }}"
        alt=""
        width="52"
        height="52"
        class="figma-sidebar-logo-mark"
        loading="eager"
        decoding="async"
        referrerpolicy="no-referrer"
    >
    <span class="figma-sidebar-logo-word">
        <span class="figma-sidebar-logo-word__click">CLICK</span><span class="figma-sidebar-logo-word__ronix">RONIX</span>
    </span>
</span>
