{{-- Country --}}
<div class="figma-geo-combobox min-w-[150px] flex-1" @click.outside="closeCountry()">
                                                <span class="figma-geo-combobox-label">Country</span>
                                                <button type="button" class="figma-geo-combobox-trigger" @click="toggleCountry()" :aria-expanded="countryOpen">
                                                    <span class="truncate" x-text="draft.country_name || 'Select country'"></span>
                                                    <svg class="figma-geo-combobox-chevron" :class="countryOpen && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                <div x-show="countryOpen" x-cloak x-transition class="figma-geo-combobox-menu">
                                                    <input x-ref="countrySearch" type="search" x-model="countryQuery" @input="searchCountries()" placeholder="Search country…" class="figma-geo-combobox-search">
                                                    <div class="figma-geo-combobox-options promotix-slim-scroll">
                                                        <p x-show="countryLoading" class="figma-geo-combobox-empty">Loading…</p>
                                                        <template x-for="item in countryItems" :key="item.code">
                                                            <button type="button" class="figma-geo-combobox-option" :class="draft.country === item.code && 'is-active'" @click="pickCountry(item)" x-text="item.name"></button>
                                                        </template>
                                                        <p x-show="!countryLoading && countryItems.length === 0" class="figma-geo-combobox-empty">No countries found</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Region --}}
                                            <div class="figma-geo-combobox min-w-[150px] flex-1" x-show="showState" x-cloak @click.outside="closeState()">
                                                <span class="figma-geo-combobox-label">Region</span>
                                                <button type="button" class="figma-geo-combobox-trigger" @click="toggleState()" :disabled="stateLoading" :aria-expanded="stateOpen">
                                                    <span class="truncate" x-text="draft.state_name || 'All regions'"></span>
                                                    <svg class="figma-geo-combobox-chevron" :class="stateOpen && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                <div x-show="stateOpen" x-cloak x-transition class="figma-geo-combobox-menu">
                                                    <input x-ref="stateSearch" type="search" x-model="stateQuery" placeholder="Search region…" class="figma-geo-combobox-search">
                                                    <div class="figma-geo-combobox-options promotix-slim-scroll">
                                                        <button type="button" class="figma-geo-combobox-option" :class="!draft.state && 'is-active'" @click="pickState(null)">All regions</button>
                                                        <template x-for="item in filteredStates" :key="item.code">
                                                            <button type="button" class="figma-geo-combobox-option" :class="draft.state === item.code && 'is-active'" @click="pickState(item)" x-text="item.name"></button>
                                                        </template>
                                                        <p x-show="!stateLoading && filteredStates.length === 0" class="figma-geo-combobox-empty">No regions found</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- City --}}
                                            <div class="figma-geo-combobox min-w-[150px] flex-1" x-show="showCity" x-cloak @click.outside="closeCity()">
                                                <span class="figma-geo-combobox-label">City</span>
                                                <button type="button" class="figma-geo-combobox-trigger" @click="toggleCity()" :disabled="cityLoading" :aria-expanded="cityOpen">
                                                    <span class="truncate" x-text="draft.city_name || 'All cities'"></span>
                                                    <svg class="figma-geo-combobox-chevron" :class="cityOpen && 'is-open'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                <div x-show="cityOpen" x-cloak x-transition class="figma-geo-combobox-menu">
                                                    <input x-ref="citySearch" type="search" x-model="cityQuery" @input="searchCities()" placeholder="Search city…" class="figma-geo-combobox-search">
                                                    <div class="figma-geo-combobox-options promotix-slim-scroll">
                                                        <p x-show="cityLoading" class="figma-geo-combobox-empty">Loading…</p>
                                                        <button type="button" class="figma-geo-combobox-option" :class="!draft.city && 'is-active'" @click="pickCity('')">All cities</button>
                                                        <template x-for="name in cityItems" :key="name">
                                                            <button type="button" class="figma-geo-combobox-option" :class="draft.city === name && 'is-active'" @click="pickCity(name)" x-text="name"></button>
                                                        </template>
                                                        <p x-show="!cityLoading && cityItems.length === 0" class="figma-geo-combobox-empty">No cities found</p>
                                                    </div>
                                                </div>
                                            </div>
