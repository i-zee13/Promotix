<div x-show="calendarOpen" x-cloak x-transition class="figma-date-range-popover {{ $popoverClass ?? 'absolute right-0 top-[calc(100%+6px)] z-[120]' }}">
    <div class="figma-date-range-nav">
        <button type="button" @click="prevMonth()" aria-label="Previous month">&lsaquo;</button>
        <span x-text="monthLabel()"></span>
        <button type="button" @click="nextMonth()" aria-label="Next month">&rsaquo;</button>
    </div>
    <div class="figma-date-range-weekdays">
        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
    </div>
    <div class="figma-date-range-grid">
        <template x-for="day in calendarDays()" :key="day.iso + '-' + day.inMonth">
            <button
                type="button"
                @click="selectDay(day.iso)"
                :disabled="!day.inMonth"
                :class="{
                    'is-outside': !day.inMonth,
                    'is-today': day.isToday,
                    'is-range': day.inRange,
                    'is-start': day.isStart,
                    'is-end': day.isEnd,
                }"
                class="figma-date-range-day"
                x-text="day.day"
            ></button>
        </template>
    </div>
    <p class="figma-date-range-hint" x-text="pickStart ? 'Select end date for range' : 'Click a day, or click twice for a range'"></p>
</div>
