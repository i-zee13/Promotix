<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="initial-theme" content="{{ (auth()->user()?->ui_preferences['dark_mode'] ?? true) ? 'dark' : 'light' }}">
    <title>@yield('title', 'Super Admin') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="brand-page">
    <div class="flex min-h-screen">
        @auth
        @php
            $menu = config('super-admin.menu', []);
            $user = auth()->user();
        @endphp

        {{-- Sidebar --}}
        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-40 w-72 shrink-0 -translate-x-full border-r border-night-700/60 bg-night-950 transition-transform duration-200 ease-out"
            aria-label="Super admin navigation"
        >
            <div class="flex h-full flex-col">
                {{-- Brand --}}
                <div class="flex h-16 items-center justify-between gap-2 border-b border-night-700/60 px-5">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 shadow-card">
                            <span class="text-lg font-bold text-white">P</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">Digital Promotix</p>
                            <p class="text-[11px] uppercase tracking-wider text-brand-200">Super Admin</p>
                        </div>
                    </div>
                    <button type="button" id="sidebar-close" class="shrink-0 rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white" aria-label="Collapse sidebar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                    </button>
                </div>

                {{-- Search --}}
                <div class="px-4 pt-4">
                    <label for="super-search" class="sr-only">Search</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-night-400" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input
                            id="super-search"
                            type="search"
                            placeholder="Search"
                            class="w-full rounded-xl border border-night-700 bg-night-900 py-2 pl-9 pr-3 text-sm text-white placeholder-night-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                        >
                    </div>
                </div>

                {{-- Nav --}}
                <nav class="flex-1 overflow-y-auto px-3 pb-6 pt-4" aria-label="Super admin sidebar">
                    <div class="space-y-0.5">
                        @foreach ($menu as $slug => $item)
                            @php
                                $routePrefix = str($item['route'])->beforeLast('.');
                                $isActive = request()->routeIs($item['route'])
                                    || ($slug !== 'dashboard' && request()->routeIs($routePrefix.'.*'));
                                $itemClasses = $isActive
                                    ? 'bg-brand-500 text-white shadow-card'
                                    : 'text-night-200 hover:bg-night-800 hover:text-white';
                            @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $itemClasses }}">
                                @include('partials.sidebar-icon', ['name' => $item['icon'] ?? 'home'])
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>

                {{-- Bottom CTA --}}
                <div class="border-t border-night-700/60 p-4">
                    <a href="{{ route('dashboard') }}" class="brand-btn-soft w-full">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Customer Admin
                    </a>
                </div>
            </div>
        </aside>
        @endauth

        {{-- Mobile overlay --}}
        <div
            id="sidebar-overlay"
            class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-200 pointer-events-none"
            aria-hidden="true"
        ></div>

        {{-- Main wrap --}}
        <div id="main-content-wrap" class="flex min-w-0 flex-1 flex-col transition-[margin] duration-200">
            {{-- Header --}}
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-4 border-b border-night-700/60 bg-night-950/95 px-4 backdrop-blur lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        id="sidebar-toggle"
                        class="rounded-lg p-2 text-night-300 hover:bg-night-800 hover:text-white"
                        aria-label="Toggle sidebar"
                        aria-expanded="false"
                        aria-controls="sidebar"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-base font-semibold text-white sm:text-lg">@yield('title', 'Super Admin')</h1>
                        @hasSection('subtitle')
                            <p class="truncate text-xs text-night-300">@yield('subtitle')</p>
                        @endif
                    </div>
                </div>

                <div class="relative flex items-center gap-1.5" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                    @hasSection('header-actions')
                        <div class="mr-2 hidden items-center gap-2 sm:flex">@yield('header-actions')</div>
                    @endif

                    <a href="{{ route('dashboard') }}" class="hidden rounded-xl border border-night-700 px-3 py-2 text-xs font-medium text-night-200 transition hover:border-brand-400 hover:text-white md:inline-flex">Customer Admin</a>

                    <button id="theme-toggle" type="button" class="rounded-lg p-2 text-night-300 hover:bg-night-800 hover:text-white" aria-label="Theme toggle">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m15.02 6.36-.7-.7M6.02 6.02l-.7-.7m12.72 0-.7.7M6.02 17.98l-.7.7M12 7a5 5 0 100 10 5 5 0 000-10z"/></svg>
                    </button>
                    <button type="button" class="rounded-lg p-2 text-night-300 hover:bg-night-800 hover:text-white" aria-label="Notifications">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </button>

                    {{-- User menu (proper dropdown — not a logout-form-on-click) --}}
                    <button type="button"
                            @click="userMenuOpen = ! userMenuOpen"
                            class="ml-1 flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600"
                            aria-label="User menu"
                            :aria-expanded="userMenuOpen">
                        <span class="text-sm font-semibold">{{ strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                    </button>
                    <div x-show="userMenuOpen" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-night-700 bg-night-900 py-1 shadow-card-lg"
                         role="menu">
                        <div class="border-b border-night-700/60 px-4 py-3">
                            <p class="text-xs uppercase tracking-wider text-night-400">Signed in as</p>
                            <p class="truncate text-sm font-semibold text-white">{{ auth()->user()?->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-night-200 transition hover:bg-night-800 hover:text-white" role="menuitem">Account settings</a>
                        <a href="{{ route('dashboard') }}" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-night-200 transition hover:bg-night-800 hover:text-white" role="menuitem">Switch to Customer Admin</a>
                        <div class="border-t border-night-700/60">
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-night-200 transition hover:bg-night-800 hover:text-white" role="menuitem">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-8">
                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const SIDEBAR_STORAGE_KEY = 'promotix-super-sidebar-collapsed';
            const THEME_STORAGE_KEY = 'promotix-theme';
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');
            const sidebarClose = document.getElementById('sidebar-close');
            const mainWrap = document.getElementById('main-content-wrap');
            const themeToggle = document.getElementById('theme-toggle');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (!sidebar || !mainWrap) return;

            const isLg = () => window.matchMedia('(min-width: 1024px)').matches;

            function isSidebarOpen() {
                return !sidebar.classList.contains('-translate-x-full');
            }

            function setSidebarOpen(open) {
                if (open) {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('opacity-0', 'pointer-events-none');
                    overlay.classList.add('opacity-100');
                    if (isLg()) mainWrap.classList.add('lg:ml-72');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    overlay.classList.remove('opacity-100');
                    mainWrap.classList.remove('lg:ml-72');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                }
            }

            function setCollapsed(collapsed) {
                try { localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
            }

            function initSidebar() {
                const collapsed = localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
                if (isLg()) {
                    if (collapsed) {
                        sidebar.classList.add('-translate-x-full');
                        mainWrap.classList.remove('lg:ml-72');
                        if (toggle) toggle.setAttribute('aria-expanded', 'false');
                    } else {
                        sidebar.classList.remove('-translate-x-full');
                        mainWrap.classList.add('lg:ml-72');
                        if (toggle) toggle.setAttribute('aria-expanded', 'true');
                    }
                } else {
                    sidebar.classList.add('-translate-x-full');
                    mainWrap.classList.remove('lg:ml-72');
                }
                overlay.classList.add('opacity-0', 'pointer-events-none');
            }

            initSidebar();

            if (toggle) {
                toggle.addEventListener('click', function () {
                    if (isSidebarOpen()) {
                        setSidebarOpen(false);
                        if (isLg()) setCollapsed(true);
                    } else {
                        setSidebarOpen(true);
                        if (isLg()) setCollapsed(false);
                    }
                });
            }

            if (sidebarClose) {
                sidebarClose.addEventListener('click', function () {
                    setSidebarOpen(false);
                    if (isLg()) setCollapsed(true);
                });
            }

            overlay.addEventListener('click', function () {
                setSidebarOpen(false);
            });

            window.matchMedia('(min-width: 1024px)').addEventListener('change', function (e) {
                if (e.matches) {
                    const collapsed = localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
                    if (collapsed) {
                        sidebar.classList.add('-translate-x-full');
                        mainWrap.classList.remove('lg:ml-72');
                    } else {
                        sidebar.classList.remove('-translate-x-full');
                        mainWrap.classList.add('lg:ml-72');
                    }
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    mainWrap.classList.remove('lg:ml-72');
                }
            });

            function setTheme(theme) {
                document.documentElement.classList.toggle('light-mode', theme === 'light');
                try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {}
            }

            const serverTheme = document.querySelector('meta[name="initial-theme"]')?.getAttribute('content') || 'dark';
            const cachedTheme = localStorage.getItem(THEME_STORAGE_KEY) || serverTheme;
            setTheme(cachedTheme);

            if (themeToggle) {
                themeToggle.addEventListener('click', async function () {
                    const nextTheme = document.documentElement.classList.contains('light-mode') ? 'dark' : 'light';
                    setTheme(nextTheme);
                    try {
                        await fetch('/user/preferences', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ dark_mode: nextTheme === 'dark' }),
                        });
                    } catch (e) {}
                });
            }
        });
    </script>
</body>
</html>
