{{-- Figma: dark calendar button on filter bar (replaces inline From/To inputs) --}}
<div class="figma-filter-calendar-host relative flex shrink-0 flex-col justify-center px-[10px]" x-data="figmaDateRangePicker" x-init="init()" @click.outside="calendarOpen = false">
    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55 sr-only">Dates</span>
    <button
        type="button"
        @click="toggleCalendar()"
        class="figma-filter-calendar-btn"
        aria-label="Pick date range"
        :aria-expanded="calendarOpen"
        :title="rangeLabel()"
    >
        <svg class="h-[16px] w-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.6"/>
            <path stroke-width="1.6" stroke-linecap="round" d="M8 3v3M16 3v3M4 10h16"/>
            <circle cx="8" cy="14" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="14" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="16" cy="14" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="8" cy="17.5" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="17.5" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="16" cy="17.5" r="0.9" fill="currentColor" stroke="none"/>
        </svg>
    </button>
    @include('partials.figma-date-range-popover')
</div>
