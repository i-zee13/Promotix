{{-- Figma / Google Ads date trigger: icon on desktop, "Today" chip on mobile --}}
<div
    class="figma-filter-calendar-host relative flex shrink-0 flex-col justify-center px-[10px]"
    x-data="figmaDateRangePicker"
    x-init="init()"
    @click.outside="if (calendarOpen && !isMobile()) cancelCalendar()"
>
    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55 sr-only">Date range</span>
    <button
        type="button"
        @click="toggleCalendar()"
        class="figma-filter-calendar-btn figma-filter-calendar-btn--responsive"
        aria-label="Pick date range"
        :aria-expanded="calendarOpen"
        :title="rangeLabel()"
    >
        <svg class="figma-filter-calendar-btn__icon h-[16px] w-[16px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.6"/>
            <path stroke-width="1.6" stroke-linecap="round" d="M8 3v3M16 3v3M4 10h16"/>
            <circle cx="8" cy="14" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="14" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="16" cy="14" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="8" cy="17.5" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="17.5" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="16" cy="17.5" r="0.9" fill="currentColor" stroke="none"/>
        </svg>
        <span class="figma-filter-calendar-btn__label" x-text="shortRangeLabel()"></span>
    </button>
    @include('partials.figma-date-range-popover')
</div>
