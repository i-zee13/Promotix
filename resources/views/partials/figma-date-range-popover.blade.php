{{-- Mobile: Google Ads–style full-screen date list. Desktop: anchored popover. --}}

{{-- Desktop / tablet popover (anchored to trigger host) --}}
<div
    x-show="calendarOpen && !isMobile()"
    x-cloak
    x-transition
    class="figma-gads-calendar figma-gads-calendar--desktop {{ $popoverClass ?? 'absolute right-0 top-[calc(100%+6px)] z-[120]' }}"
    @keydown.escape.window="if (calendarOpen && !isMobile()) cancelCalendar()"
>
    <div class="figma-gads-calendar__body">
        <aside class="figma-gads-calendar__presets">
            <ul class="figma-gads-calendar__preset-list">
                <template x-for="preset in presets" :key="preset.id">
                    <li>
                        <button
                            type="button"
                            class="figma-gads-calendar__preset"
                            :class="{ 'is-active': activePreset === preset.id }"
                            @click="selectPreset(preset.id)"
                            x-text="preset.label"
                        ></button>
                    </li>
                </template>
            </ul>

            <div class="figma-gads-calendar__custom-days">
                <label class="figma-gads-calendar__days-row">
                    <input type="number" min="1" max="366" x-model.number="daysUpToToday" @change="applyDaysUpTo('today')" class="figma-gads-calendar__days-input">
                    <span>days up to today</span>
                </label>
                <label class="figma-gads-calendar__days-row">
                    <input type="number" min="1" max="366" x-model.number="daysUpToYesterday" @change="applyDaysUpTo('yesterday')" class="figma-gads-calendar__days-input">
                    <span>days up to yesterday</span>
                </label>
            </div>

            <div class="figma-gads-calendar__compare">
                <span>Compare</span>
                <button
                    type="button"
                    class="figma-gads-calendar__toggle"
                    :class="{ 'is-on': compareEnabled }"
                    role="switch"
                    :aria-checked="compareEnabled"
                    @click="compareEnabled = !compareEnabled"
                >
                    <span class="figma-gads-calendar__toggle-thumb"></span>
                </button>
            </div>
        </aside>

        <section class="figma-gads-calendar__main">
            <div class="figma-gads-calendar__inputs">
                <label class="figma-gads-calendar__field">
                    <span>Start date*</span>
                    <input
                        type="text"
                        :value="draftFromLabel()"
                        @change="parseDraftInput('from', $event.target.value)"
                        class="figma-gads-calendar__date-input"
                    >
                </label>
                <label class="figma-gads-calendar__field">
                    <span>End date*</span>
                    <input
                        type="text"
                        :value="draftToLabel()"
                        @change="parseDraftInput('to', $event.target.value)"
                        class="figma-gads-calendar__date-input"
                    >
                </label>
            </div>

            <div class="figma-gads-calendar__month-nav">
                <span class="figma-gads-calendar__month-current" x-text="(months.find(m => m.key === scrollMonthKey) || months[0] || {}).label || ''"></span>
                <div class="figma-gads-calendar__month-arrows">
                    <button type="button" @click="jumpMonth(-1)" aria-label="Previous month">&lsaquo;</button>
                    <button type="button" @click="jumpMonth(1)" aria-label="Next month">&rsaquo;</button>
                </div>
            </div>

            <div class="figma-gads-calendar__scroller" x-ref="monthScroller">
                <template x-for="month in months" :key="month.key">
                    <div class="figma-gads-calendar__month" :data-month-key="month.key">
                        <h4 class="figma-gads-calendar__month-title" x-text="month.label"></h4>
                        <div class="figma-gads-calendar__weekdays">
                            <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                        </div>
                        <div class="figma-gads-calendar__grid">
                            <template x-for="day in month.cells" :key="day.iso + '-' + day.inMonth">
                                <button
                                    type="button"
                                    class="figma-gads-calendar__day"
                                    :disabled="!day.inMonth"
                                    :class="{
                                        'is-outside': !day.inMonth,
                                        'is-today': day.isToday,
                                        'is-range': day.inRange,
                                        'is-start': day.isStart,
                                        'is-end': day.isEnd,
                                    }"
                                    @click="selectDay(day.iso, day.inMonth)"
                                    x-text="day.day"
                                ></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="figma-gads-calendar__footer">
                <button type="button" class="figma-gads-calendar__btn" @click="cancelCalendar()">Cancel</button>
                <button type="button" class="figma-gads-calendar__btn figma-gads-calendar__btn--primary" @click="applyCalendar()">Apply</button>
            </div>
        </section>
    </div>
</div>

{{-- Mobile full-screen sheet (teleported so it isn't clipped by filter bar) --}}
<template x-teleport="body">
    <div
        x-show="calendarOpen && isMobile()"
        x-cloak
        class="figma-gads-calendar-mobile"
        x-transition.opacity
        @keydown.escape.window="if (calendarOpen && isMobile()) cancelCalendar()"
        @click.self="cancelCalendar()"
    >
        <div class="figma-gads-calendar-mobile__sheet" @click.stop>
            <header class="figma-gads-calendar-mobile__header">
                <button
                    type="button"
                    class="figma-gads-calendar-mobile__close"
                    @click="mobileView === 'custom' ? backToMobileList() : cancelCalendar()"
                    aria-label="Close"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <h3 class="figma-gads-calendar-mobile__title" x-text="mobileView === 'custom' ? 'Custom range' : 'Date range'"></h3>
            </header>

            <div class="figma-gads-calendar-mobile__list" x-show="mobileView === 'list'">
                <button type="button" class="figma-gads-calendar-mobile__row" @click="openCustomMobile()">
                    <span>Custom</span>
                </button>
                <template x-for="preset in presets" :key="'m-' + preset.id">
                    <button
                        type="button"
                        class="figma-gads-calendar-mobile__row"
                        :class="{ 'is-active': activePreset === preset.id }"
                        @click="selectPresetMobile(preset.id)"
                    >
                        <span x-text="preset.label"></span>
                        <svg
                            x-show="activePreset === preset.id"
                            class="figma-gads-calendar-mobile__check"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </template>
            </div>

            <div class="figma-gads-calendar-mobile__custom" x-show="mobileView === 'custom'" x-cloak>
                <div class="figma-gads-calendar__inputs">
                    <label class="figma-gads-calendar__field">
                        <span>Start date*</span>
                        <input
                            type="text"
                            :value="draftFromLabel()"
                            @change="parseDraftInput('from', $event.target.value)"
                            class="figma-gads-calendar__date-input"
                        >
                    </label>
                    <label class="figma-gads-calendar__field">
                        <span>End date*</span>
                        <input
                            type="text"
                            :value="draftToLabel()"
                            @change="parseDraftInput('to', $event.target.value)"
                            class="figma-gads-calendar__date-input"
                        >
                    </label>
                </div>
                <div class="figma-gads-calendar__month-nav">
                    <span class="figma-gads-calendar__month-current" x-text="(months.find(m => m.key === scrollMonthKey) || months[0] || {}).label || ''"></span>
                    <div class="figma-gads-calendar__month-arrows">
                        <button type="button" @click="jumpMonth(-1)" aria-label="Previous month">&lsaquo;</button>
                        <button type="button" @click="jumpMonth(1)" aria-label="Next month">&rsaquo;</button>
                    </div>
                </div>
                <div class="figma-gads-calendar-mobile__scroller" x-ref="mobileMonthScroller">
                    <template x-for="month in months" :key="'mm-' + month.key">
                        <div class="figma-gads-calendar__month" :data-month-key="month.key">
                            <h4 class="figma-gads-calendar__month-title" x-text="month.label"></h4>
                            <div class="figma-gads-calendar__weekdays">
                                <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                            </div>
                            <div class="figma-gads-calendar__grid">
                                <template x-for="day in month.cells" :key="'md-' + day.iso + '-' + day.inMonth">
                                    <button
                                        type="button"
                                        class="figma-gads-calendar__day"
                                        :disabled="!day.inMonth"
                                        :class="{
                                            'is-outside': !day.inMonth,
                                            'is-today': day.isToday,
                                            'is-range': day.inRange,
                                            'is-start': day.isStart,
                                            'is-end': day.isEnd,
                                        }"
                                        @click="selectDay(day.iso, day.inMonth)"
                                        x-text="day.day"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="figma-gads-calendar__footer">
                    <button type="button" class="figma-gads-calendar__btn" @click="backToMobileList()">Cancel</button>
                    <button type="button" class="figma-gads-calendar__btn figma-gads-calendar__btn--primary" @click="applyCalendar()">Apply</button>
                </div>
            </div>
        </div>
    </div>
</template>
