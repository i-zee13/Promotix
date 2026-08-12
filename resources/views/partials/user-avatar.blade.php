@php
    $avatarUser = $avatarUser ?? ($user ?? auth()->user());
    $avatarUrl = $avatarUser?->avatarUrl();
    $avatarInitial = $avatarUser?->avatarInitial() ?? '?';
    $avatarTextClass = $avatarTextClass ?? 'text-[11px] font-semibold leading-none text-white/90';
@endphp
@if ($avatarUrl)
    <img
        src="{{ $avatarUrl }}"
        alt=""
        class="h-full w-full object-cover"
        referrerpolicy="no-referrer"
        loading="lazy"
    >
@else
    <span class="{{ $avatarTextClass }}">{{ $avatarInitial }}</span>
@endif
