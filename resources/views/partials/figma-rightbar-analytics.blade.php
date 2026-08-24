{{-- Analytics rightbar Quick Actions (orange accent) --}}
@php
    $analyticsDashRoute = Route::has('analytics.dashboard')
        ? route('analytics.dashboard')
        : route('bot-protection.dashboard');
    $analyticsAdvancedRoute = Route::has('analytics.traffic-control')
        ? route('analytics.traffic-control')
        : route('bot-protection.advanced');
@endphp
<div class="figma-rightbar-center mt-[8px] border-t-2 border-[#FF6600]/50 pt-[14px] pa-analytics-rightbar">
    <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
    <div class="mx-auto grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
        <a href="{{ $analyticsDashRoute }}" class="paid-quick-action pa-quick-action" title="Dashboard">
            @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
            <span>Dashboard</span>
        </a>
        <a href="{{ $analyticsAdvancedRoute }}" class="paid-quick-action pa-quick-action" title="Advanced">
            @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[16px] w-[16px]'])
            <span>Advanced</span>
        </a>
        <a href="{{ $analyticsAdvancedRoute }}#journey" class="paid-quick-action pa-quick-action" title="Journeys">
            @include('partials.sidebar-icon', ['name' => 'repeat', 'class' => 'h-[16px] w-[16px]'])
            <span>Journeys</span>
        </a>
        <a href="{{ $analyticsDashRoute }}#sources" class="paid-quick-action pa-quick-action" title="Sources">
            @include('partials.sidebar-icon', ['name' => 'globe', 'class' => 'h-[16px] w-[16px]'])
            <span>Sources</span>
        </a>
        <a href="{{ $analyticsDashRoute }}#sales" class="paid-quick-action pa-quick-action" title="Sales">
            @include('partials.sidebar-icon', ['name' => 'card', 'class' => 'h-[16px] w-[16px]'])
            <span>Sales</span>
        </a>
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings',{detail:{tab:'reports'}}))"
            class="paid-quick-action pa-quick-action"
            title="Reports"
        >
            @include('partials.sidebar-icon', ['name' => 'box', 'class' => 'h-[16px] w-[16px]'])
            <span>Reports</span>
        </button>
    </div>
</div>
<style>
    .pa-analytics-rightbar .pa-quick-action,
    .pa-quick-action.paid-quick-action {
        background: #FF6600 !important;
        color: #fff !important;
        border: 0;
        cursor: pointer;
        text-decoration: none;
        width: 100%;
        font-weight: 600;
    }
    .pa-analytics-rightbar .pa-quick-action:hover,
    .pa-quick-action.paid-quick-action:hover {
        background: #FF8533 !important;
        color: #fff !important;
    }
</style>
