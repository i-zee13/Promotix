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
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <script>window.PROMOTIX_FILTER_DEBOUNCE_MS = 1500;</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php $branding = \App\Support\Branding::cssVars(); @endphp
    <style>{!! \App\Support\Branding::rootStyleBlock() !!}</style>
</head>
<body class="figma-body min-h-screen overflow-x-hidden font-sans antialiased">
@php
    // Date range lives on each dashboard page filter bar — not the top header.
    $usesDashboardDateRange = false;
@endphp
@if ($usesDashboardDateRange)
    @include('partials.promotix-page-loader')
@endif
@php
    $user = auth()->user();
    $brandCompany = $branding['company_name'] ?? 'Digital Promotix';
    $navGroups = [
        'HOME' => [
            ['label' => 'Overview', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard'],
        ],
        'TRAFFIC ANALYTICS' => [
            ['label' => 'Paid Dashboard', 'route' => 'paid-marketing.dashboard', 'icon' => 'chart', 'permission' => 'paid-marketing-dashboard'],
            ['label' => 'Bot Dashboard', 'route' => 'bot-protection.dashboard', 'icon' => 'home', 'permission' => 'bot-protection'],
        ],
        'INVALID TRAFFIC' => [
            ['label' => 'Advanced View', 'route' => 'paid-marketing.detailed', 'icon' => 'eye', 'permission' => 'paid-marketing-detailed'],
            ['label' => 'Bot Advanced', 'route' => 'bot-protection.advanced', 'icon' => 'eye', 'permission' => 'bot-protection'],
            ['label' => 'Detection Panel', 'route' => 'paid-marketing.detection-settings', 'icon' => 'shield-check', 'permission' => 'paid-marketing-detection-settings'],
        ],
        'TRACKING' => [
            ['label' => 'Platform Integrate', 'route' => 'integrations', 'icon' => 'plug', 'permission' => 'paid-marketing-platform-connections'],
            ['label' => 'Domains', 'route' => 'domains.index', 'icon' => 'globe', 'permission' => 'domain-management'],
        ],
    ];
    $toolLinks = [
        ['route' => 'paid-marketing.detection-settings', 'icon' => 'shield-check', 'label' => 'Detection'],
        ['route' => 'paid-marketing.detailed', 'icon' => 'repeat', 'label' => 'Advanced'],
        ['route' => 'domains.index', 'icon' => 'tag', 'label' => 'Domains'],
        ['route' => 'integrations', 'icon' => 'plug', 'label' => 'Integrations'],
        ['route' => 'paid-marketing.dashboard', 'icon' => 'chart', 'label' => 'Paid Ads'],
        ['route' => 'bot-protection.dashboard', 'icon' => 'chart', 'label' => 'Bots'],
        ['route' => 'domains.index', 'icon' => 'globe', 'label' => 'Sites'],
        ['route' => 'billing.index', 'icon' => 'card', 'label' => 'Billing'],
        ['route' => 'profile.edit', 'icon' => 'settings', 'label' => 'Settings'],
    ];
@endphp

<div id="figma-shell" class="figma-shell">
    @if (session('impersonator_id'))
        <div class="fixed left-0 right-0 top-0 z-50 border-b border-amber-500/40 bg-amber-500/20 px-4 py-2 text-xs text-amber-100">
            <form method="POST" action="{{ route('impersonate.stop') }}" class="flex flex-wrap items-center justify-between gap-2">
                @csrf
                <span>You are impersonating <strong>{{ $user?->email }}</strong>.</span>
                <button type="submit" class="rounded-md bg-amber-500/30 px-3 py-1 font-semibold hover:bg-amber-500/50">Stop impersonating</button>
            </form>
        </div>
    @endif

    <aside class="figma-sidebar px-[16px] pt-[12px] pb-[6px] xl:px-[20px] xl:pt-[14px] xl:pb-[8px]">
        <div class="figma-sidebar-inner flex min-h-[100dvh] flex-col">
            <a href="{{ route('dashboard') }}" class="figma-sidebar-brand mb-[8px] mt-[2px] flex shrink-0 items-center gap-[8px]">
                <span class="h-[26px] w-[26px] shrink-0 rounded-[6px] shadow-[0_0_18px_rgba(var(--brand-primary-rgb),0.7)]" style="background:var(--brand-primary);"></span>
                <span class="figma-sidebar-brand-text truncate text-[16px] font-bold leading-none">{{ $brandCompany }}</span>
            </a>

            <div class="relative mb-[10px] shrink-0">
                <span class="figma-sidebar-search-icon absolute left-[11px] top-1/2 -translate-y-1/2 text-white/70">
                    <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input id="figma-sidebar-search" type="search" placeholder="Search gclid, IP, domain…" class="figma-sidebar-search h-[32px] w-full max-w-full rounded-[8px] border pl-[36px] pr-[10px] text-[13px] leading-none focus:border-[var(--brand-primary)] focus:ring-[color-mix(in_srgb,var(--brand-primary)_30%,transparent)]">
                <div id="figma-sidebar-search-hint" class="mt-1 hidden text-[10px] text-white/50"></div>
            </div>

            <nav class="figma-nav-scrollless mt-[4px] shrink-0 overflow-hidden overflow-x-hidden pr-[2px]" aria-label="Main navigation">
                @foreach ($navGroups as $group => $items)
                    <div class="mb-[14px]">
                        <p class="figma-nav-label mb-[8px] text-[11px] font-bold uppercase leading-none">{{ $group }}</p>
                        <div class="space-y-[4px]">
                            @foreach ($items as $item)
                                @continue($user && ! $user->canAccess($item['permission']))
                                @php $active = request()->routeIs($item['route']); @endphp
                                <a href="{{ route($item['route']) }}" @class([
                                    'figma-nav-link group relative flex h-[30px] items-center gap-[9px] rounded-[7px] px-[7px] text-[14px] leading-none transition',
                                    'is-active text-white' => $active,
                                    'hover:text-white' => ! $active,
                                ]) @if($active) style="background:var(--brand-primary);box-shadow:0 0 0 1px color-mix(in srgb, var(--brand-primary) 55%, transparent);" @endif>
                                    @include('partials.sidebar-icon', ['name' => $item['icon'], 'class' => 'h-[17px] w-[17px] shrink-0'])
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="mb-[14px]">
                    <p class="figma-nav-label mb-[8px] text-[11px] font-bold uppercase leading-none">SITE MANAGEMENT</p>
                    <a href="{{ route('domains.index', ['add' => 1]) }}" class="figma-add-domain-btn flex h-[32px] w-full max-w-[188px] items-center justify-center gap-[6px] rounded-[8px] border text-[13px] font-bold uppercase transition hover:bg-[#6400B2] hover:text-white">
                        <span class="flex h-[16px] w-[16px] items-center justify-center rounded-full border text-[11px] leading-none">+</span>
                        ADD DOMAIN
                    </a>
                </div>
            </nav>

            <footer class="figma-sidebar-footer mt-auto shrink-0 border-t border-white/10 pt-[10px] pb-[2px]">
                <div class="figma-sidebar-controls mb-[6px] flex items-center justify-between gap-[8px]">
                    <div>
                        <span id="theme-toggle-label" class="figma-sidebar-theme-label mb-[3px] block text-[9px] leading-none">Dark Mode</span>
                        <button id="theme-toggle" type="button" class="figma-theme-toggle" aria-label="Theme toggle">
                            <span class="figma-toggle-track"><span class="figma-toggle-thumb"></span></span>
                        </button>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="figma-sidebar-settings flex h-[32px] w-[32px] shrink-0 items-center justify-center rounded-[7px] transition hover:bg-[#6400B2]/25" aria-label="Account settings">
                        @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[17px] w-[17px]'])
                    </a>
                </div>
                @include('partials.sidebar-logo')
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

        <div class="relative flex items-center gap-[8px]">
            @hasSection('header-actions')
                <div class="hidden items-center gap-2 md:flex">@yield('header-actions')</div>
            @endif

            @include('partials.portal-switch')

            @hasSection('header-toolbar')
                @yield('header-toolbar')
            @else
                @include('partials.header-timezone')
            @endif

            @if ($usesDashboardDateRange)
            <div class="relative hidden sm:block" x-data="figmaDateRangePicker" x-init="init()" @click.outside="calendarOpen = false" title="Date range for dashboards">
                <button
                    type="button"
                    @click="toggleCalendar()"
                    class="figma-date-range-trigger flex h-[27px] min-w-[148px] items-center gap-[8px] rounded-[3px] border border-[#6400B2] bg-[#0D0D0D] px-[8px] text-[10px] text-white hover:border-[#7B13C8]"
                    aria-label="Pick date range"
                    :aria-expanded="calendarOpen"
                >
                    <span class="truncate" x-text="rangeLabel()"></span>
                    <svg class="h-[14px] w-[14px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M4 11h16M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                </button>

                @include('partials.figma-date-range-popover', ['popoverClass' => 'figma-date-range-popover absolute right-0 top-[calc(100%+6px)] z-[120]'])
            </div>
            @endif

            <div class="relative" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                <div class="flex h-[27px] max-w-[60vw] items-center overflow-hidden rounded-[3px] border border-[#6400B2] bg-[#0D0D0D] text-[11px] text-white sm:max-w-none">
                    <span class="flex h-full w-[30px] items-center justify-center border-r border-[#6400B2] bg-white/10">
                        <svg class="h-[15px] w-[15px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 1115 0"/></svg>
                    </span>
                    <button type="button" @click="userMenuOpen = ! userMenuOpen" class="truncate px-[9px] text-left sm:px-[14px]">{{ $user?->name ?: ($user?->email ?? 'User') }}</button>
                </div>

                <div x-show="userMenuOpen" x-cloak class="figma-user-menu absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-[#6400B2]/60 bg-[#111111] py-1 shadow-card-lg">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">Account settings</a>
                    @if ($user?->is_super_admin)
                        <a href="{{ route('super-admin.dashboard') }}" class="block px-4 py-2 text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">Super Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-white/75 hover:bg-[#6400B2] hover:text-white">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="figma-main figma-customer-canvas">
        @yield('content')
    </main>

    <aside class="figma-rightbar px-[16px] pb-[16px] pt-[20px]">
        @hasSection('rightbar')
            @yield('rightbar')
        @else
        <div class="figma-rightbar-default">
        <div class="mb-[16px]">
            <p class="text-[18px] font-bold leading-none text-[#a9a9a9]">Digital Promotix</p>
            <p class="mt-[4px] text-[9px] text-white/45">Account panel</p>
        </div>

        <div id="right-notifications" class="figma-rightbar-notify space-y-[10px] border-b-2 border-[#5a2a99] pb-[12px] text-[9px] text-[#a9a9a9]"></div>

        <div class="mt-[16px]">
            <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">Add Account</h2>
            <a href="{{ route('integrations') }}" class="figma-rightbar-add-card block rounded-[3px] bg-[#6400B2] p-[6px]">
                <div class="flex h-[96px] items-center justify-center bg-[#6400B2]">
                    <svg class="h-[44px] w-[44px]" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.6-6 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/>
                        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 16.1 18.9 13 24 13c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/>
                        <path fill="#4CAF50" d="M24 44c5.4 0 10.3-2.1 14-5.5l-6.5-5.3C29.3 35.1 26.8 36 24 36c-5.2 0-9.6-3.4-11.2-8.1l-6.6 5.1C9.5 39.6 16.2 44 24 44z"/>
                        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.1 4.3-3.9 5.9l.1.1 6.5 5.3C39.8 35.8 44 30.5 44 24c0-1.3-.1-2.7-.4-3.5z"/>
                    </svg>
                </div>
                <div class="mt-[2px] flex h-[24px] items-center justify-between border border-white px-[8px] text-[11px] text-white">
                    <span>Google Account</span>
                    <span class="flex h-[11px] w-[11px] items-center justify-center rounded-full border border-white text-[8px]">+</span>
                </div>
            </a>
        </div>

        <div class="mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
            <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">Tools</h2>
            <div class="grid w-full max-w-[156px] grid-cols-3 gap-x-[18px] gap-y-[18px]">
                @foreach ($toolLinks as $tool)
                    <a href="{{ route($tool['route']) }}" title="{{ $tool['label'] }}" class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]">
                        @include('partials.sidebar-icon', ['name' => $tool['icon'], 'class' => 'h-[18px] w-[18px]'])
                    </a>
                @endforeach
                @if (request()->routeIs('paid-marketing.dashboard'))
                    <button type="button" title="Export IPs CSV" onclick="window.dispatchEvent(new CustomEvent('promotix:export-ips-csv'))" class="flex h-[31px] w-[32px] items-center justify-center rounded-[3px] bg-[#6400B2] text-white hover:bg-[#7B13C8]">
                        @include('partials.sidebar-icon', ['name' => 'download', 'class' => 'h-[18px] w-[18px]'])
                    </button>
                @endif
            </div>
            <a href="{{ route('billing.index') }}" class="figma-rightbar-extra mt-[16px] block rounded-[5px] bg-[#6603B3] p-[8px] text-white">
                <div class="flex items-center justify-between text-[7px] text-[#a9a9a9]">
                    <span>Invalid Blocked user</span>
                    <span>Return Rate</span>
                </div>
                <div class="mt-[6px] flex items-center justify-around text-[10px]" id="rightbar-blocked-stats">
                    <span>—</span>
                    <span class="text-[#d9d9d9]">blocked</span>
                    <span>—</span>
                </div>
                <div class="mt-[8px] rounded-[5px] bg-[#171515] px-[8px] py-[6px] text-center text-[7px] leading-tight">
                    Reallocated Budget Simulator
                </div>
            </a>
        </div>
        </div>
        @endif
    </aside>

    <button id="figma-rightbar-edge-toggle" type="button" class="figma-rightbar-edge-toggle" aria-label="Hide account panel" title="Hide panel">
        <svg id="figma-rightbar-icon-close" class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <svg id="figma-rightbar-icon-open" class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" hidden><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>
</div>

@include('partials.figma-notifications-script')

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/overview/summary', { headers: { Accept: 'application/json' } })
        .then((r) => r.ok ? r.json() : null)
        .then((data) => {
            const el = document.getElementById('rightbar-blocked-stats');
            if (!el || !data) return;
            const blocked = data.botProtection?.blockedHits ?? 0;
            const invalid = data.paidAdvertising?.invalidVisits ?? 0;
            el.innerHTML = `<span>${Number(blocked).toLocaleString()}</span><span class="text-[#d9d9d9]">blocked</span><span>${Number(invalid).toLocaleString()}</span>`;
        })
        .catch(() => {});
    const shell = document.getElementById('figma-shell');
    const sidebarToggle = document.getElementById('figma-sidebar-toggle');
    const overlay = document.getElementById('figma-sidebar-overlay');
    const themeToggle = document.getElementById('theme-toggle');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const sidebarKey = 'promotix-figma-sidebar-collapsed';
    const rightbarKey = 'promotix-figma-rightbar-collapsed';
    const themeKey = 'promotix-theme';
    const isDesktopSidebar = () => window.matchMedia('(min-width: 1024px)').matches;
    const isDesktopRightbar = () => window.matchMedia('(min-width: 1280px)').matches;

    function syncSidebar() {
        const collapsed = localStorage.getItem(sidebarKey) === '1';
        shell?.classList.toggle('figma-sidebar-collapsed', isDesktopSidebar() && collapsed);
        if (!isDesktopSidebar()) shell?.classList.remove('figma-sidebar-open');
    }

    function syncRightbar() {
        const collapsed = localStorage.getItem(rightbarKey) === '1';
        const desktop = isDesktopRightbar();
        shell?.classList.toggle('figma-rightbar-collapsed', desktop && collapsed);
        const edgeToggle = document.getElementById('figma-rightbar-edge-toggle');
        const iconOpen = document.getElementById('figma-rightbar-icon-open');
        const iconClose = document.getElementById('figma-rightbar-icon-close');
        if (!edgeToggle) return;
        if (!desktop) {
            edgeToggle.setAttribute('hidden', '');
            return;
        }
        edgeToggle.removeAttribute('hidden');
        if (collapsed) {
            iconOpen?.removeAttribute('hidden');
            iconClose?.setAttribute('hidden', '');
            edgeToggle.setAttribute('aria-label', 'Show account panel');
            edgeToggle.title = 'Show panel';
        } else {
            iconOpen?.setAttribute('hidden', '');
            iconClose?.removeAttribute('hidden');
            edgeToggle.setAttribute('aria-label', 'Hide account panel');
            edgeToggle.title = 'Hide panel';
        }
    }

    document.getElementById('figma-rightbar-edge-toggle')?.addEventListener('click', () => {
        if (!isDesktopRightbar()) return;
        const collapsed = localStorage.getItem(rightbarKey) === '1';
        localStorage.setItem(rightbarKey, collapsed ? '0' : '1');
        syncRightbar();
    });

    sidebarToggle?.addEventListener('click', () => {
        if (isDesktopSidebar()) {
            const next = !(localStorage.getItem(sidebarKey) === '1');
            localStorage.setItem(sidebarKey, next ? '1' : '0');
            syncSidebar();
        } else {
            shell?.classList.toggle('figma-sidebar-open');
        }
    });

    overlay?.addEventListener('click', () => shell?.classList.remove('figma-sidebar-open'));
    window.matchMedia('(min-width: 1024px)').addEventListener('change', syncSidebar);
    window.matchMedia('(min-width: 1280px)').addEventListener('change', syncRightbar);
    syncSidebar();
    syncRightbar();

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
            await fetch('/user/preferences', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: JSON.stringify({dark_mode: nextTheme === 'dark'}),
            });
        } catch (e) {}
    });
});
</script>
@include('partials.timezone-sync')
@include('partials.live-agent-chat')
@include('partials.promotix-global-ip-modal')
<script>
(() => {
    const input = document.getElementById('figma-sidebar-search');
    const hint = document.getElementById('figma-sidebar-search-hint');
    if (!input) return;

    const setHint = (text, isError = false) => {
        if (!hint) return;
        if (!text) {
            hint.classList.add('hidden');
            hint.textContent = '';
            return;
        }
        hint.classList.remove('hidden');
        hint.textContent = text;
        hint.style.color = isError ? '#fda4af' : 'rgba(255,255,255,0.55)';
    };

    const runSearch = async () => {
        const q = input.value.trim();
        if (!q) {
            setHint('');
            return;
        }
        setHint('Searching…');
        try {
            const res = await fetch(`/overview/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            const match = data.match;
            if (!match) {
                setHint(data.message || 'No match found', true);
                return;
            }

            if (match.type === 'domain') {
                window.location.href = `{{ route('dashboard') }}?domain_id=${encodeURIComponent(match.domain_id)}`;
                return;
            }
            if (match.type === 'campaign') {
                window.location.href = `{{ route('dashboard') }}?campaign=${encodeURIComponent(match.campaign)}`;
                return;
            }
            if (['ip', 'gclid', 'visitor', 'event'].includes(match.type) && match.ip) {
                setHint(`Opening ${match.type.toUpperCase()} details…`);
                window.dispatchEvent(new CustomEvent('promotix-open-ip-modal', {
                    detail: {
                        ip: match.ip,
                        type: match.type,
                        label: match.type === 'gclid' ? 'GCLID investigation' : `${match.type.toUpperCase()} investigation`,
                    },
                }));
                return;
            }
            setHint('No actionable match', true);
        } catch (e) {
            setHint('Search failed', true);
        }
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            runSearch();
        }
    });
})();
</script>
</body>
</html>
