@props([
    'name'  => 'modal',
    'title' => null,
    'size'  => 'md', // sm | md | lg | xl
])

@php
    $maxWidth = match ($size) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        default => 'max-w-lg',
    };
@endphp

<template x-teleport="body">
    <div
        x-show="$store.modals.{{ $name }}"
        x-cloak
        x-transition.opacity
        @keydown.escape.window="$store.modals.{{ $name }} = false"
        class="brand-modal-overlay"
    >
        <div
            x-show="$store.modals.{{ $name }}"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            @click.outside="$store.modals.{{ $name }} = false"
            class="brand-modal {{ $maxWidth }}"
            role="dialog"
            aria-modal="true"
        >
            @if ($title)
                <header class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="brand-modal-title">{{ $title }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white"
                        @click="$store.modals.{{ $name }} = false" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>
            @endif

            <div class="brand-modal-body">{{ $slot }}</div>

            @isset($footer)
                <footer class="mt-6 flex justify-end gap-2">{{ $footer }}</footer>
            @endisset
        </div>
    </div>
</template>
