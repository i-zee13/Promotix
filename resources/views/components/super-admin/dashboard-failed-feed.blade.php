@props(['rows' => [], 'loadMoreRoute'])

<section class="figma-sa-dash-feed figma-sa-dash-feed--compact">
    <div class="figma-sa-dash-feed-head">
        <div class="flex min-w-0 items-center gap-2">
            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" @click="open = !open" class="figma-sa-dash-select-trigger">
                        <span>Active</span>
                        <svg class="h-3 w-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-dash-dropdown-item">Active</button>
                <button type="button" class="figma-sa-dash-dropdown-item">Inactive</button>
                <button type="button" class="figma-sa-dash-dropdown-item">All</button>
            </x-super-admin.dashboard-dropdown>
            <span class="figma-sa-dash-info" title="Failed payment queue">i</span>
            <h2 class="figma-sa-dash-feed-title shrink-0">Failed payments</h2>
        </div>
        <a href="{{ $loadMoreRoute }}" class="figma-sa-dash-feed-more shrink-0">Load More</a>
    </div>
    <div class="figma-sa-dash-failed-rows">
        @forelse ($rows as $row)
            <div class="figma-sa-dash-failed-row">
                <span class="figma-sa-dash-feed-muted">{{ $row['date'] }}</span>
                <span class="figma-sa-dash-failed-email">{{ $row['email'] }}</span>
                <span class="figma-sa-dash-feed-muted text-right">{{ $row['time'] }}</span>
                <x-super-admin.dashboard-dropdown align="right">
                    <x-slot:trigger>
                        <button type="button" @click="open = !open" class="figma-sa-dash-row-menu" aria-label="Row actions">⋯</button>
                    </x-slot:trigger>
                    @if (! empty($row['url']))
                        <a href="{{ $row['url'] }}" class="figma-sa-dash-dropdown-item block text-left">View payment</a>
                    @endif
                    <a href="{{ $loadMoreRoute }}" class="figma-sa-dash-dropdown-item block text-left">Open payments</a>
                </x-super-admin.dashboard-dropdown>
            </div>
        @empty
            <p class="figma-sa-dash-feed-empty">No failed payments.</p>
        @endforelse
    </div>
</section>
