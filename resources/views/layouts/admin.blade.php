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
    <style>
        .figma-sidebar-settings.is-active,
        .figma-sidebar-settings:hover {
            background: rgba(100, 0, 178, 0.25);
            color: #fff;
        }
        .figma-shell { --figma-right: 220px; }
        .figma-shell.figma-rightbar-collapsed { --figma-right: 0px; padding-right: 0; }

        /* Compact blocks (icons / tools / quick actions) stay centered */
        .figma-rightbar-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        .figma-rightbar-center h2 { text-align: center; }
        .figma-rightbar-center .grid {
            margin-left: auto;
            margin-right: auto;
        }

        /* Full-width panels (detection, IP investigate, etc.) */
        .figma-rightbar-stretch {
            width: 100% !important;
            max-width: none !important;
            align-self: stretch;
        }
    </style>
</head>
<body class="figma-body min-h-screen overflow-x-hidden font-sans antialiased">
@include('partials.promotix-page-loader')
@php
    // Date range lives on each dashboard page filter bar — not the top header.
    $usesDashboardDateRange = false;
    $user = auth()->user();
    $brandCompany = $branding['company_name'] ?? 'Digital Promotix';
    $navGroups = [
        'HOME' => [
            ['label' => 'Overview', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard'],
        ],
        'PAID ADVERTISING' => [
            ['label' => 'Dashboard', 'route' => 'paid-marketing.dashboard', 'icon' => 'chart', 'permission' => 'paid-marketing-dashboard'],
            ['label' => 'Advanced View', 'route' => 'paid-marketing.detailed', 'icon' => 'eye', 'permission' => 'paid-marketing-detailed'],
            ['label' => 'Platform Integrate', 'route' => 'integrations', 'icon' => 'plug', 'permission' => 'paid-marketing-platform-connections'],
            ['label' => 'Detection Panel', 'route' => 'paid-marketing.detection-settings', 'icon' => 'shield-check', 'permission' => 'paid-marketing-detection-settings'],
        ],
        'ANALYTICS' => [
            ['label' => 'Dashboard', 'route' => 'bot-protection.dashboard', 'icon' => 'home', 'permission' => 'bot-protection'],
            ['label' => 'Traffic Control', 'route' => 'bot-protection.advanced', 'icon' => 'eye', 'permission' => 'bot-protection'],
        ],
        'SITE MANAGEMENT' => [
            ['label' => 'Domains', 'route' => 'domains.index', 'icon' => 'globe', 'permission' => 'domain-management'],
        ],
    ];
    $toolLinks = [
        ['route' => 'paid-marketing.detection-settings', 'icon' => 'shield-check', 'label' => 'Detection'],
        ['route' => 'paid-marketing.detailed', 'icon' => 'repeat', 'label' => 'Advanced'],
        ['route' => 'domains.index', 'icon' => 'tag', 'label' => 'Domains'],
        ['route' => 'integrations', 'icon' => 'plug', 'label' => 'Integrations'],
        ['route' => 'paid-marketing.dashboard', 'icon' => 'chart', 'label' => 'Paid Ads'],
        ['route' => 'bot-protection.dashboard', 'icon' => 'chart', 'label' => 'Analytics'],
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
                    <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.12a7.5 7.5 0 0115 0"/></svg>
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
                        @if ($group === 'SITE MANAGEMENT' && (! $user || $user->canAccess('domain-management')))
                            <a href="{{ route('domains.index', ['add' => 1]) }}" class="figma-add-domain-btn mt-[8px] flex h-[32px] w-full max-w-[188px] items-center justify-center gap-[6px] rounded-[8px] border text-[13px] font-bold uppercase transition hover:bg-[#6400B2] hover:text-white">
                                <span class="flex h-[16px] w-[16px] items-center justify-center rounded-full border text-[11px] leading-none">+</span>
                                ADD DOMAIN
                            </a>
                            @if (! $user?->is_super_admin && ! $user?->is_admin && $user?->activeSubscription()?->status === 'trialing')
                                <a href="{{ route('upgrade-plan') }}" class="figma-add-domain-btn mt-[8px] flex h-[32px] w-full max-w-[188px] items-center justify-center gap-[6px] rounded-[8px] border border-[#9A1AFF] bg-[#6400B2]/20 text-[13px] font-bold uppercase text-white transition hover:bg-[#6400B2]">
                                    <span class="text-[14px] leading-none">↑</span>
                                    UPGRADE PLAN
                                </a>
                            @endif
                        @endif
                    </div>
                @endforeach
            </nav>

            <footer class="figma-sidebar-footer mt-auto shrink-0 border-t border-white/10 pt-[10px] pb-[2px]">
                <div class="figma-sidebar-controls mb-[6px] flex items-center justify-between gap-[8px]">
                    <div>
                        <span id="theme-toggle-label" class="figma-sidebar-theme-label mb-[3px] block text-[9px] leading-none">Dark Mode</span>
                        <button id="theme-toggle" type="button" class="figma-theme-toggle" aria-label="Theme toggle">
                            <span class="figma-toggle-track"><span class="figma-toggle-thumb"></span></span>
                        </button>
                    </div>
                    <div class="figma-sidebar-settings-wrap relative shrink-0">
                        <button
                            type="button"
                            id="figma-sidebar-settings-btn"
                            class="figma-sidebar-settings flex h-[32px] w-[32px] items-center justify-center rounded-[7px] transition hover:bg-[#6400B2]/25"
                            aria-label="Open settings"
                            aria-haspopup="dialog"
                            onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings'))"
                        >
                            @include('partials.sidebar-icon', ['name' => 'settings', 'class' => 'h-[17px] w-[17px]'])
                        </button>
                    </div>
                </div>
                @include('partials.sidebar-logo')
            </footer>
        </div>
    </aside>

    <div id="figma-sidebar-overlay" class="figma-sidebar-overlay"></div>

    <header class="figma-header flex items-center justify-between px-[10px] sm:px-[14px]">
        <div class="flex min-w-0 items-center gap-[13px] text-white/85">
            {{-- Mobile only: open nav drawer. Desktop left sidebar stays fixed (no toggle). --}}
            <button id="figma-sidebar-toggle" type="button" class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-[4px] hover:bg-white/10 lg:hidden" aria-label="Open menu">
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
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
                    <span class="flex h-full w-[30px] shrink-0 items-center justify-center overflow-hidden border-r border-[#6400B2] bg-white/10">
                        @include('partials.user-avatar', ['avatarUser' => $user])
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

    <aside class="figma-rightbar px-[10px] pb-[14px] pt-[16px]">
        @hasSection('rightbar')
            @yield('rightbar')
        @else
        <div class="figma-rightbar-default">
        @include('partials.figma-rightbar-header-actions')

        <div class="figma-rightbar-stretch mt-[16px] border-b-2 border-[#5a2a99] pb-[14px]">
            @include('partials.figma-rightbar-ip-investigation')
        </div>

        <div class="figma-rightbar-center mt-[14px] pt-[4px]">
            <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">Tools</h2>
            <div class="mx-auto grid w-full max-w-[156px] grid-cols-3 gap-x-[18px] gap-y-[18px]">
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
            <a href="{{ route('billing.index') }}" class="figma-rightbar-extra figma-rightbar-billing mt-[16px] block w-full max-w-[168px] rounded-[5px] bg-[#6603B3] p-[8px] text-white">
                <div class="figma-rightbar-billing__cols">
                    <div class="figma-rightbar-billing__col">
                        <span class="figma-rightbar-billing__label">Invalid / Blocked</span>
                        <span class="figma-rightbar-billing__value" id="rightbar-blocked-count">—</span>
                    </div>
                    <div class="figma-rightbar-billing__col">
                        <span class="figma-rightbar-billing__label">Invalid visits</span>
                        <span class="figma-rightbar-billing__value" id="rightbar-invalid-count">—</span>
                    </div>
                </div>
                <div class="figma-rightbar-billing__cta mt-[8px] rounded-[5px] bg-[#171515] px-[8px] py-[6px] text-center text-[7px] leading-tight">
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
@include('partials.figma-settings-modal')

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/overview/summary', { headers: { Accept: 'application/json' } })
        .then((r) => r.ok ? r.json() : null)
        .then((data) => {
            const blockedEl = document.getElementById('rightbar-blocked-count');
            const invalidEl = document.getElementById('rightbar-invalid-count');
            if ((!blockedEl && !invalidEl) || !data) return;
            const blocked = data.botProtection?.blockedHits ?? 0;
            const invalid = data.paidAdvertising?.invalidVisits ?? 0;
            if (blockedEl) blockedEl.textContent = Number(blocked).toLocaleString();
            if (invalidEl) invalidEl.textContent = Number(invalid).toLocaleString();
        })
        .catch(() => {});
    const shell = document.getElementById('figma-shell');
    const overlay = document.getElementById('figma-sidebar-overlay');
    const themeToggle = document.getElementById('theme-toggle');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const rightbarKey = 'promotix-figma-rightbar-collapsed';
    const themeKey = 'promotix-theme';
    const isDesktopRightbar = () => window.matchMedia('(min-width: 1280px)').matches;

    // Left sidebar stays open — no desktop collapse / toggle.
    try { localStorage.removeItem('promotix-figma-sidebar-collapsed'); } catch (_) {}
    shell?.classList.remove('figma-sidebar-collapsed');

    document.getElementById('figma-sidebar-toggle')?.addEventListener('click', () => {
        // Mobile drawer only (button is lg:hidden).
        if (window.matchMedia('(min-width: 1024px)').matches) return;
        shell?.classList.toggle('figma-sidebar-open');
    });

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

    overlay?.addEventListener('click', () => shell?.classList.remove('figma-sidebar-open'));
    window.matchMedia('(min-width: 1280px)').addEventListener('change', syncRightbar);
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
