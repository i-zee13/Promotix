{{-- Analytics rightbar Quick Actions (legacy include → analytics routes) --}}
@php
    $isTrafficControl = request()->routeIs('analytics.traffic-control', 'bot-protection.advanced');
    $dashRoute = Route::has('analytics.dashboard') ? route('analytics.dashboard') : route('bot-protection.dashboard');
    $trafficRoute = Route::has('analytics.traffic-control') ? route('analytics.traffic-control') : route('bot-protection.advanced');
@endphp
<div class="figma-rightbar-center mt-[8px] border-t-2 border-[#FF6600]/50 pt-[14px]">
    <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
    <div class="mx-auto grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
        @if ($isTrafficControl)
            <a href="{{ $dashRoute }}" class="paid-quick-action" title="Dashboard">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
                <span>Dashboard</span>
            </a>
        @else
            <a href="{{ $trafficRoute }}" class="paid-quick-action" title="Traffic Control">
                @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[16px] w-[16px]'])
                <span>Traffic Control</span>
            </a>
        @endif
        <a href="{{ route('paid-marketing.detection-settings') }}" class="paid-quick-action" title="Detection (Paid Adv)">
            @include('partials.sidebar-icon', ['name' => 'shield', 'class' => 'h-[16px] w-[16px]'])
            <span>Detection</span>
        </a>
        <a href="{{ route('paid-marketing.detailed') }}" class="paid-quick-action" title="Paid Advanced View">
            @include('partials.sidebar-icon', ['name' => 'repeat', 'class' => 'h-[16px] w-[16px]'])
            <span>Paid Advanced</span>
        </a>
        <a href="{{ route('domains.index') }}" class="paid-quick-action" title="Domains">
            @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[16px] w-[16px]'])
            <span>Domains</span>
        </a>
        <a href="{{ route('paid-marketing.dashboard') }}" class="paid-quick-action" title="Paid Marketing">
            @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
            <span>Paid Ads</span>
        </a>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings',{detail:{tab:'reports'}}))" class="paid-quick-action" title="Reports">
            @include('partials.sidebar-icon', ['name' => 'box', 'class' => 'h-[16px] w-[16px]'])
            <span>Reports</span>
        </button>
    </div>
    <p class="mx-auto mt-[10px] w-full max-w-[168px] text-[9px] leading-snug text-white/40">
        Analytics shows visitor intelligence only. IP blocking stays in Paid Advertising / Detection.
    </p>
</div>
