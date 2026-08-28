<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="initial-theme" content="{{ (auth()->user()?->ui_preferences['dark_mode'] ?? true) ? 'dark' : 'light' }}">
    @auth
        <meta name="user-timezone" content="{{ \App\Support\UserTimezone::forUser(auth()->user()) }}">
    @endauth
    <title>@yield('title', 'Super Admin') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="figma-body min-h-screen overflow-x-hidden font-sans antialiased">
@php
    $user = auth()->user();
    $menu = config('super-admin.menu', []);
    $navGroups = collect(config('super-admin.groups', []))
        ->map(fn ($slugs, $label) => [
            'label' => $label,
            'items' => collect($slugs)
                ->map(fn ($slug) => array_merge(['slug' => $slug], $menu[$slug] ?? []))
                ->filter(fn ($item) => isset($item['route']))
                ->values()
                ->all(),
        ])
        ->filter(fn ($group) => count($group['items']) > 0)
        ->values()
        ->all();
@endphp

<div id="figma-shell" class="figma-shell figma-shell-super">
    <aside class="figma-sidebar px-[16px] pt-[2px] pb-[6px] xl:px-[20px] xl:pt-[3px] xl:pb-[8px]">
        <div class="figma-sidebar-inner flex min-h-[100dvh] flex-col">
            <a href="{{ route('super-admin.dashboard') }}" class="figma-sidebar-brand mb-[6px] mt-0 flex shrink-0 items-center">
                @include('partials.sidebar-logo')
            </a>

            <div class="relative mb-[10px] shrink-0">
                <span class="figma-sidebar-search-icon absolute left-[11px] top-1/2 -translate-y-1/2 text-white/70">
                    <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="search" id="super-admin-sidebar-search" placeholder="Search" class="figma-sidebar-search h-[32px] w-full max-w-[188px] rounded-[8px] border pl-[36px] pr-[10px] text-[13px] leading-none focus:border-[#6400B2] focus:ring-[#6400B2]/30" autocomplete="off">
            </div>

            <nav class="figma-nav-scrollless mt-[4px] min-h-0 flex-1 overflow-y-auto overflow-x-hidden pr-[2px]" aria-label="Super admin navigation" id="super-admin-sidebar-nav">
                @foreach ($navGroups as $group)
                    <div class="mb-[14px]" data-nav-group>
                        <p class="figma-nav-label mb-[8px] text-[11px] font-bold uppercase leading-none">{{ $group['label'] }}</p>
                        <div class="space-y-[4px]">
                            @foreach ($group['items'] as $item)
                                @php
                                    $slug = $item['slug'];
                                    $routePrefix = str($item['route'])->beforeLast('.').'.*';
                                    $active = request()->routeIs($item['route'])
                                        || ($slug !== 'dashboard' && request()->routeIs($routePrefix));
                                @endphp
                                <a href="{{ route($item['route']) }}"
                                   data-nav-label="{{ strtolower($item['label']) }}"
                                   @class([
                                       'figma-nav-link group relative flex h-[30px] max-w-[188px] items-center gap-[9px] rounded-[7px] px-[7px] text-[14px] leading-none transition',
                                       'is-active bg-[#6400B2] text-white shadow-[0_0_0_1px_rgba(100,0,179,.55)]' => $active,
                                       'hover:bg-[#6400B2]/55 hover:text-white' => ! $active,
                                   ])>
                                    @include('partials.sidebar-icon', ['name' => $item['icon'] ?? 'home', 'class' => 'h-[17px] w-[17px] shrink-0'])
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <footer class="figma-sidebar-footer mt-auto shrink-0 border-t border-white/10 pt-[10px] pb-[2px]">
                <div class="figma-sidebar-controls mb-[6px] flex max-w-[188px] items-center justify-between gap-[8px]">
                    <div>
                        <span id="theme-toggle-label" class="figma-sidebar-theme-label mb-[3px] block text-[9px] leading-none">Dark Mode</span>
                        <button id="theme-toggle" type="button" class="figma-theme-toggle" aria-label="Theme toggle">
                            <span class="figma-toggle-track"><span class="figma-toggle-thumb"></span></span>
                        </button>
                    </div>
                    <a href="{{ route('super-admin.settings.index') }}" class="figma-sidebar-settings flex h-[32px] w-[32px] shrink-0 items-center justify-center rounded-[7px] transition hover:bg-[#6400B2]/25" aria-label="System settings">
                        @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[17px] w-[17px]'])
                    </a>
                </div>
            </footer>
        </div>
    </aside>

    <div id="figma-sidebar-overlay" class="figma-sidebar-overlay"></div>

    <header class="figma-header flex items-center justify-between px-[10px] sm:px-[14px]">
        <div class="flex min-w-0 items-center gap-[13px] text-white/85">
            <button id="figma-sidebar-toggle" type="button" class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-[4px] hover:bg-white/10" aria-label="Toggle sidebar">
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span class="hidden h-[18px] w-px bg-[#5a2a99] sm:block"></span>
            <a href="{{ route('integrations') }}" class="hidden h-[26px] w-[26px] shrink-0 items-center justify-center rounded-[4px] hover:bg-white/10 sm:flex" aria-label="Connections">
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 13a5 5 0 007.07 0l2.12-2.12a5 5 0 00-7.07-7.07L11 4.93"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 11a5 5 0 00-7.07 0L4.8 13.12a5 5 0 007.07 7.07L13 19.07"/></svg>
            </a>
        </div>

        <div class="relative flex items-center gap-[8px]" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
            @include('partials.portal-switch')
            @include('partials.header-timezone')
            <div class="flex h-[27px] max-w-[60vw] items-center overflow-hidden rounded-[3px] border border-[#6400B2] bg-[#0D0D0D] text-[11px] text-white sm:max-w-none">
                <span class="flex h-full w-[30px] shrink-0 items-center justify-center overflow-hidden border-r border-[#6400B2] bg-white/10">
                    @include('partials.user-avatar', ['avatarUser' => $user])
                </span>
                <button type="button" @click="userMenuOpen = ! userMenuOpen" class="truncate px-[9px] text-left sm:px-[14px]">{{ $user?->name ?: $user?->email }}</button>
            </div>
            <div x-show="userMenuOpen" x-cloak class="figma-user-menu absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-[#6400B2]/60 bg-[#111111] py-1 shadow-card-lg">
                <a href="{{ route('super-admin.settings.index') }}" class="block px-4 py-2 text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">System settings</a>
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">Account settings</a>
                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">Customer portal</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">Logout</button></form>
            </div>
        </div>
    </header>

    <main class="figma-main figma-sa-main figma-customer-canvas">
        @yield('content')
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const shell = document.getElementById('figma-shell');
    const sidebarToggle = document.getElementById('figma-sidebar-toggle');
    const overlay = document.getElementById('figma-sidebar-overlay');
    const themeToggle = document.getElementById('theme-toggle');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const sidebarKey = 'promotix-super-sidebar-collapsed';
    const themeKey = 'promotix-theme';
    const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

    function syncSidebar() {
        const collapsed = localStorage.getItem(sidebarKey) === '1';
        shell?.classList.toggle('figma-sidebar-collapsed', isDesktop() && collapsed);
        if (!isDesktop()) shell?.classList.remove('figma-sidebar-open');
    }
    sidebarToggle?.addEventListener('click', () => {
        if (isDesktop()) {
            const next = !(localStorage.getItem(sidebarKey) === '1');
            localStorage.setItem(sidebarKey, next ? '1' : '0');
            syncSidebar();
        } else {
            shell?.classList.toggle('figma-sidebar-open');
        }
    });
    overlay?.addEventListener('click', () => shell?.classList.remove('figma-sidebar-open'));
    window.matchMedia('(min-width: 1024px)').addEventListener('change', syncSidebar);
    syncSidebar();

    function setTheme(theme) {
        document.documentElement.classList.toggle('light-mode', theme === 'light');
        themeToggle?.classList.toggle('figma-theme-toggle--on', theme === 'dark');
        const label = document.getElementById('theme-toggle-label');
        if (label) label.textContent = theme === 'dark' ? 'Dark Mode' : 'Light Mode';
        localStorage.setItem(themeKey, theme);
    }
    setTheme(localStorage.getItem(themeKey) || document.querySelector('meta[name="initial-theme"]')?.content || 'dark');
    themeToggle?.addEventListener('click', async () => {
        const nextTheme = document.documentElement.classList.contains('light-mode') ? 'dark' : 'light';
        setTheme(nextTheme);
        try {
            await fetch('/user/preferences', { method: 'PUT', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'}, body: JSON.stringify({dark_mode: nextTheme === 'dark'}) });
        } catch (e) {}
    });

    const sidebarSearch = document.getElementById('super-admin-sidebar-search');
    const nav = document.getElementById('super-admin-sidebar-nav');
    sidebarSearch?.addEventListener('input', () => {
        const q = sidebarSearch.value.trim().toLowerCase();
        nav?.querySelectorAll('[data-nav-group]').forEach((group) => {
            let visible = 0;
            group.querySelectorAll('[data-nav-label]').forEach((link) => {
                const label = link.getAttribute('data-nav-label') || '';
                const show = !q || label.includes(q);
                link.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            group.style.display = visible > 0 ? '' : 'none';
        });
    });
});
</script>
@include('partials.timezone-sync')
</body>
</html>
