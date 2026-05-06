<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .light-mode body { background: #f3f4f6; color: #111827; }
        .light-mode #sidebar,
        .light-mode header,
        .light-mode .bg-dark-card,
        .light-mode .bg-dark { background: #ffffff !important; color: #111827 !important; }
        .light-mode .border-dark-border { border-color: #e5e7eb !important; }
        .light-mode .text-white { color: #111827 !important; }
        .light-mode .text-gray-400,
        .light-mode .text-gray-300,
        .light-mode .text-gray-500 { color: #6b7280 !important; }
    </style>
</head>
<body class="min-h-screen bg-dark text-gray-100 antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar: shown for all authenticated users; admin sees full nav, others see Dashboard only. Collapsible on all screens. --}}
        @auth
        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 -translate-x-full border-r border-dark-border bg-dark transition-transform duration-200 ease-out"
            aria-label="Main navigation"
        >
            <div class="flex h-full flex-col">
                <div class="flex h-16 items-center justify-between gap-2 border-b border-dark-border px-4">
                    <div class="flex min-w-0 items-center gap-2">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent">
                            <span class="text-lg font-bold text-white">P</span>
                        </div>
                        <span class="truncate font-semibold text-white">Digital Promotix</span>
                    </div>
                    <button type="button" id="sidebar-close" class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-dark-card hover:text-white lg:block" aria-label="Collapse sidebar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <label for="sidebar-search" class="sr-only">Search</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input
                            id="sidebar-search"
                            type="search"
                            placeholder="Search"
                            class="w-full rounded-[20px] border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        >
                    </div>
                </div>
                <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4" aria-label="Sidebar">
                    @php $menu = config('admin.menu', []); @endphp
                    @foreach ($menu as $slug => $item)
                        @if (auth()->user()->canAccess($slug))
                            <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>
        </aside>
        @endauth

        {{-- Overlay for mobile (when sidebar is open) --}}
        <div
            id="sidebar-overlay"
            class="fixed inset-0 z-30 bg-black/50 opacity-0 transition-opacity duration-200 pointer-events-none"
            aria-hidden="true"
        ></div>

        {{-- Main content (margin when sidebar is open on desktop) --}}
        <div id="main-content-wrap" class="flex min-w-0 flex-1 flex-col transition-[margin] duration-200">
            {{-- Top header --}}
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-dark-border bg-dark px-4 lg:px-8">
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        id="sidebar-toggle"
                        class="rounded-lg p-2 text-gray-400 hover:bg-dark-card hover:text-white"
                        aria-label="Toggle sidebar"
                        aria-expanded="false"
                        aria-controls="sidebar"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-xl font-bold text-white">@yield('title', 'Dashboard')</h1>
                </div>
                <div class="relative flex items-center gap-2" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                    <button id="theme-toggle" type="button" class="rounded-lg p-2 text-gray-400 hover:bg-dark-card hover:text-white" aria-label="Dark mode toggle">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m15.02 6.36-.7-.7M6.02 6.02l-.7-.7m12.72 0-.7.7M6.02 17.98l-.7.7M12 7a5 5 0 100 10 5 5 0 000-10z"/></svg>
                    </button>
                    <a href="#" class="rounded-lg p-2 text-gray-400 hover:bg-dark-card hover:text-white" aria-label="Help">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </a>
                    <button type="button" @click="userMenuOpen = ! userMenuOpen" class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-white transition hover:opacity-90" aria-label="User menu" :aria-expanded="userMenuOpen">
                        <span class="text-sm font-medium">{{ auth()->user() ? strtoupper(mb_substr(auth()->user()->name, 0, 1)) : 'U' }}</span>
                    </button>
                    {{-- User dropdown --}}
                    <div x-show="userMenuOpen" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 top-full z-50 mt-2 w-48 rounded-xl border border-dark-border bg-dark-card py-1 shadow-lg" role="menu">
                        <div class="border-b border-dark-border px-4 py-2">
                            <p class="text-xs text-gray-400">Email</p>
                            <p class="truncate text-sm font-medium text-white">{{ auth()->user()?->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-300 transition hover:bg-dark-border hover:text-white" role="menuitem">Change Photo</a>
                        <div class="border-t border-dark-border">
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-300 transition hover:bg-dark-border hover:text-white" role="menuitem">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const SIDEBAR_STORAGE_KEY = 'promotix-sidebar-collapsed';
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
                    if (isLg()) mainWrap.classList.add('lg:ml-64');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    overlay.classList.remove('opacity-100');
                    mainWrap.classList.remove('lg:ml-64');
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
                        mainWrap.classList.remove('lg:ml-64');
                        if (toggle) toggle.setAttribute('aria-expanded', 'false');
                    } else {
                        sidebar.classList.remove('-translate-x-full');
                        mainWrap.classList.add('lg:ml-64');
                        if (toggle) toggle.setAttribute('aria-expanded', 'true');
                    }
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    mainWrap.classList.remove('lg:ml-64');
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                }
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
                        mainWrap.classList.remove('lg:ml-64');
                    } else {
                        sidebar.classList.remove('-translate-x-full');
                        mainWrap.classList.add('lg:ml-64');
                    }
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    mainWrap.classList.remove('lg:ml-64');
                }
            });

            function setTheme(theme) {
                document.documentElement.classList.toggle('light-mode', theme === 'light');
                try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {}
            }

            const cachedTheme = localStorage.getItem(THEME_STORAGE_KEY) || 'dark';
            setTheme(cachedTheme);

            if (themeToggle) {
                themeToggle.addEventListener('click', async function () {
                    const nextTheme = document.documentElement.classList.contains('light-mode') ? 'dark' : 'light';
                    setTheme(nextTheme);
                    try {
                        await fetch('/api/user/preferences', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ dark_mode: nextTheme === 'dark' }),
                        });
                    } catch (e) {
                        // Local storage fallback still preserves refresh behavior.
                    }
                });
            }
        });
    </script>
</body>
</html>
