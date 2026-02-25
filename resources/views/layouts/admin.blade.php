<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-dark text-gray-100 antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar (only for admin users) --}}
        @if(auth()->check() && auth()->user()->is_admin)
        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 -translate-x-full transform border-r border-dark-border bg-dark transition-transform duration-200 lg:relative lg:translate-x-0"
            aria-label="Main navigation"
        >
            <div class="flex h-full flex-col">
                <div class="flex h-16 items-center gap-2 border-b border-dark-border px-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent">
                        <span class="text-lg font-bold text-white">P</span>
                    </div>
                    <span class="font-semibold text-white">Digital Promotix</span>
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
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('users') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('users') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Users &amp; Teams</a>
                    <a href="{{ route('saas-products') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('saas-products') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">SaaS Products</a>
                    <a href="{{ route('plans') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('plans') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Plans &amp; Pricing</a>
                    <a href="{{ route('subscriptions') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('subscriptions') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Subscriptions</a>
                    <a href="{{ route('payments') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('payments') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Payments</a>
                    <a href="{{ route('domains-trackers') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('domains-trackers') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Domains &amp; Trackers</a>
                    <a href="{{ route('traffic-bot-logs') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('traffic-bot-logs') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Traffic &amp; Bot Logs</a>
                    <a href="{{ route('automation') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('automation') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Automation</a>
                    <a href="{{ route('integrations') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('integrations') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Integrations</a>
                    <a href="{{ route('support-system') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('support-system') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Support System</a>
                    <a href="{{ route('analytics') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('analytics') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Analytics</a>
                    <a href="{{ route('security-logs') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('security-logs') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">Security &amp; Logs</a>
                    <a href="{{ route('system-settings') }}" class="flex items-center gap-3 rounded-[20px] px-4 py-3 text-sm font-medium transition {{ request()->routeIs('system-settings') ? 'bg-accent text-white' : 'text-gray-300 hover:bg-dark-card hover:text-white' }}">System Settings</a>
                </nav>
            </div>
        </aside>
        @endif

        {{-- Overlay for mobile --}}
        <div
            id="sidebar-overlay"
            class="fixed inset-0 z-30 bg-black/50 opacity-0 transition-opacity duration-200 lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Main content --}}
        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Top header --}}
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-dark-border bg-dark px-4 lg:px-8">
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        id="sidebar-toggle"
                        class="rounded-lg p-2 text-gray-400 hover:bg-dark-card hover:text-white lg:hidden"
                        aria-label="Toggle sidebar"
                        aria-expanded="false"
                        aria-controls="sidebar"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-xl font-bold text-white">@yield('title', 'Dashboard')</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="#" class="rounded-lg p-2 text-gray-400 hover:bg-dark-card hover:text-white" aria-label="Help">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </a>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-white" aria-label="User menu">
                        <span class="text-sm font-medium">U</span>
                    </button>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
                toggle.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
                toggle.setAttribute('aria-expanded', 'false');
            }

            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('-translate-x-full')) {
                    openSidebar();
                } else {
                    closeSidebar();
                }
            });

            overlay.addEventListener('click', closeSidebar);

            if (window.matchMedia('(max-width: 1023px)').matches) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('pointer-events-none');
            } else {
                sidebar.classList.remove('-translate-x-full');
            }

            window.matchMedia('(min-width: 1024px)').addEventListener('change', function (e) {
                if (e.matches) {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                } else {
                    sidebar.classList.add('-translate-x-full');
                }
            });
        });
    </script>
</body>
</html>
