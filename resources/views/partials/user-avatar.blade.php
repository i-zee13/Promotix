@php
    $avatarUser = $avatarUser ?? ($user ?? auth()->user());
    $avatarUrl = $avatarUser?->avatarUrl();
    $avatarInitial = $avatarUser?->avatarInitial() ?? '?';
    $avatarTextClass = $avatarTextClass ?? 'text-[11px] font-semibold leading-none text-white/90';
    $avatarAlt = trim((string) ($avatarUser?->name ?: $avatarUser?->email ?: 'User'));
@endphp
@if ($avatarUrl)
    <img
        src="{{ $avatarUrl }}"
        data-promotix-avatar
        @if ($avatarUser)
            data-avatar-user-id="{{ $avatarUser->getKey() }}"
        @endif
        alt="{{ $avatarAlt }}"
        class="promotix-user-avatar-img h-full w-full rounded-full object-cover"
        referrerpolicy="no-referrer"
        loading="lazy"
        onerror="this.classList.add('!hidden'); this.nextElementSibling?.classList.remove('hidden');"
    >
    <span class="promotix-user-avatar-fallback {{ $avatarTextClass }} hidden">{{ $avatarInitial }}</span>
@else
    <span class="promotix-user-avatar-fallback {{ $avatarTextClass }}">{{ $avatarInitial }}</span>
@endif
