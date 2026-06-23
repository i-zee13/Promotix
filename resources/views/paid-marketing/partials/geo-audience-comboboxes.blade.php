{{-- Country (multi-select) --}}
<div class="figma-geo-combobox min-w-[150px] flex-1" @click.outside="closeCountry()">
    <span class="figma-geo-combobox-label">Countries</span>
    <button type="button" class="figma-geo-combobox-trigger" @click="toggleCountry()" :aria-expanded="countryOpen">
        <span class="truncate" x-text="countryTriggerLabel()"></span>
        <svg class="figma-geo-combobox-chevron" :class="countryOpen && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="countryOpen" x-cloak x-transition class="figma-geo-combobox-menu">
        <input x-ref="countrySearch" type="search" x-model="countryQuery" @input="searchCountries()" placeholder="Search country…" class="figma-geo-combobox-search">
        <div class="figma-geo-combobox-options promotix-slim-scroll">
            <p x-show="countryLoading" class="figma-geo-combobox-empty">Loading…</p>
            <template x-for="item in filteredCountryItems" :key="item.code">
                <label class="figma-geo-combobox-check">
                    <input type="checkbox" :checked="isCountrySelected(item.code)" @change="toggleCountryItem(item)">
                    <span x-text="item.name"></span>
                </label>
            </template>
            <p x-show="!countryLoading && filteredCountryItems.length === 0" class="figma-geo-combobox-empty">No countries found</p>
        </div>
    </div>
</div>

{{-- Region (multi-select, grouped by country) --}}
<div class="figma-geo-combobox min-w-[150px] flex-1" x-show="showState" x-cloak @click.outside="closeState()">
    <span class="figma-geo-combobox-label">Regions</span>
    <button type="button" class="figma-geo-combobox-trigger" @click="toggleState()" :disabled="stateLoading" :aria-expanded="stateOpen">
        <span class="truncate" x-text="stateTriggerLabel()"></span>
        <svg class="figma-geo-combobox-chevron" :class="stateOpen && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="stateOpen" x-cloak x-transition class="figma-geo-combobox-menu">
        <input x-ref="stateSearch" type="search" x-model="stateQuery" placeholder="Search region…" class="figma-geo-combobox-search">
        <div class="figma-geo-combobox-options promotix-slim-scroll">
            <p x-show="stateLoading" class="figma-geo-combobox-empty">Loading…</p>
            <button type="button" class="figma-geo-combobox-option figma-geo-combobox-option--muted" @click="clearAllStates()">All regions (no filter)</button>
            <template x-for="group in filteredStateGroups" :key="group.country_code">
                <div>
                    <div class="figma-geo-combobox-group-head" x-text="group.country_name"></div>
                    <template x-for="item in group.states" :key="group.country_code + '-' + item.code">
                        <label class="figma-geo-combobox-check figma-geo-combobox-check--indented">
                            <input type="checkbox" :checked="isStateSelected(group.country_code, item.code)" @change="toggleStateItem(item, group)">
                            <span x-text="item.name"></span>
                        </label>
                    </template>
                </div>
            </template>
            <p x-show="!stateLoading && filteredStateGroups.length === 0" class="figma-geo-combobox-empty">No regions found</p>
        </div>
    </div>
</div>

{{-- City (multi-select, grouped by region) --}}
<div class="figma-geo-combobox min-w-[150px] flex-1" x-show="showCity" x-cloak @click.outside="closeCity()">
    <span class="figma-geo-combobox-label">Cities</span>
    <button type="button" class="figma-geo-combobox-trigger" @click="toggleCity()" :disabled="cityLoading" :aria-expanded="cityOpen">
        <span class="truncate" x-text="cityTriggerLabel()"></span>
        <svg class="figma-geo-combobox-chevron" :class="cityOpen && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="cityOpen" x-cloak x-transition class="figma-geo-combobox-menu">
        <input x-ref="citySearch" type="search" x-model="cityQuery" @input="searchCities()" placeholder="Search city…" class="figma-geo-combobox-search">
        <div class="figma-geo-combobox-options promotix-slim-scroll">
            <p x-show="cityLoading" class="figma-geo-combobox-empty">Loading…</p>
            <button type="button" class="figma-geo-combobox-option figma-geo-combobox-option--muted" @click="clearAllCities()">All cities (no filter)</button>
            <template x-for="group in filteredCityGroups" :key="group.country_code + '-' + group.state_code">
                <div>
                    <div class="figma-geo-combobox-group-head">
                        <span x-text="group.country_name"></span>
                        <span class="figma-geo-combobox-group-sub" x-text="' · ' + group.state_name"></span>
                    </div>
                    <template x-for="name in group.cities" :key="group.country_code + '-' + group.state_code + '-' + name">
                        <label class="figma-geo-combobox-check figma-geo-combobox-check--indented">
                            <input type="checkbox" :checked="isCitySelected(group.country_code, group.state_code, name)" @change="toggleCityItem(name, group)">
                            <span x-text="name"></span>
                        </label>
                    </template>
                </div>
            </template>
            <p x-show="!cityLoading && filteredCityGroups.length === 0" class="figma-geo-combobox-empty">No cities found</p>
        </div>
    </div>
</div>
