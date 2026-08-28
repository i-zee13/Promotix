{{-- Inline / embedded dashboard calendar (Settings → Data Reports custom range) --}}
<div class="figma-gads-calendar figma-gads-calendar--embedded">
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
