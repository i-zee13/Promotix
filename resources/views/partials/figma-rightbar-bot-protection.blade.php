{{-- Bot Protection rightbar Quick Actions (Dashboard + Advanced) --}}
@php
    $isAdvanced = request()->routeIs('bot-protection.advanced');
@endphp
<div class="mt-[8px] flex w-full flex-col items-center border-t-2 border-[#5a2a99] pt-[14px]">
    <h2 class="mb-[10px] w-full max-w-[168px] text-center text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
    <div class="mx-auto grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
        @if ($isAdvanced)
            <a href="{{ route('bot-protection.dashboard') }}" class="paid-quick-action" title="Dashboard">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
                <span>Dashboard</span>
            </a>
        @else
            <a href="{{ route('bot-protection.advanced') }}" class="paid-quick-action" title="Advanced View">
                @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[16px] w-[16px]'])
                <span>Advanced</span>
            </a>
        @endif
        <a href="{{ route('paid-marketing.detection-settings') }}" class="paid-quick-action" title="Detection">
            @include('partials.sidebar-icon', ['name' => 'shield', 'class' => 'h-[16px] w-[16px]'])
            <span>Detection</span>
        </a>
        <a href="{{ route('ip-logs') }}" class="paid-quick-action" title="IP Logs">
            @include('partials.sidebar-icon', ['name' => 'globe', 'class' => 'h-[16px] w-[16px]'])
            <span>IP Logs</span>
        </a>
        <a href="{{ route('domains.index') }}" class="paid-quick-action" title="Domains">
            @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[16px] w-[16px]'])
            <span>Domains</span>
        </a>
        <a href="{{ route('paid-marketing.dashboard') }}" class="paid-quick-action" title="Paid Marketing">
            @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
            <span>Paid Ads</span>
        </a>
        <a href="{{ route('reports.index') }}" class="paid-quick-action" title="Reports">
            @include('partials.sidebar-icon', ['name' => 'box', 'class' => 'h-[16px] w-[16px]'])
            <span>Reports</span>
        </a>
    </div>
</div>
