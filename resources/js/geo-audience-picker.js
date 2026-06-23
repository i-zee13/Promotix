import Alpine from 'alpinejs';

const GEO_HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function debounce(fn, ms = 300) {
    let timer = null;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}

function stateKey(countryCode, stateCode) {
    return `${countryCode}|${stateCode}`;
}

function cityKey(countryCode, stateCode, cityName) {
    return `${countryCode}|${stateCode}|${cityName}`;
}

export function geoAudiencePicker(initial = {}) {
    const endpoints = initial.endpoints || {};

    return {
        rules: Array.isArray(initial.rules) ? initial.rules : [],
        endpoints: {
            countries: endpoints.countries || '/admin/paid-marketing/geo/countries',
            states: endpoints.states || '/admin/paid-marketing/geo/states',
            cities: endpoints.cities || '/admin/paid-marketing/geo/cities',
        },
        selectedCountries: [],
        selectedStates: [],
        selectedCities: [],
        stateGroups: [],
        cityGroups: [],
        showState: false,
        showCity: false,
        countryOpen: false,
        stateOpen: false,
        cityOpen: false,
        countryQuery: '',
        stateQuery: '',
        cityQuery: '',
        countryItems: [],
        countryLoading: false,
        stateLoading: false,
        cityLoading: false,
        get jsonValue() {
            return JSON.stringify({ rules: this.rules });
        },
        init() {
            if (Array.isArray(initial.countries) && initial.countries.length > 0) {
                this.countryItems = initial.countries;
            }
            this.searchCountries = debounce(() => this.fetchCountries(), 280);
            this.searchCities = debounce(() => this.rebuildCityGroups(), 280);
        },
        get filteredCountryItems() {
            const q = this.countryQuery.trim().toLowerCase();
            if (!q) {
                return this.countryItems;
            }
            return this.countryItems.filter((item) =>
                item.name.toLowerCase().includes(q) || item.code.toLowerCase().includes(q)
            );
        },
        get filteredStateGroups() {
            const q = this.stateQuery.trim().toLowerCase();
            if (!q) {
                return this.stateGroups;
            }
            return this.stateGroups
                .map((group) => ({
                    ...group,
                    states: group.states.filter((state) =>
                        state.name.toLowerCase().includes(q) || state.code.toLowerCase().includes(q)
                    ),
                }))
                .filter((group) => group.states.length > 0 || group.country_name.toLowerCase().includes(q));
        },
        get filteredCityGroups() {
            const q = this.cityQuery.trim().toLowerCase();
            if (!q) {
                return this.cityGroups;
            }
            return this.cityGroups
                .map((group) => ({
                    ...group,
                    cities: group.cities.filter((city) => city.toLowerCase().includes(q)),
                }))
                .filter((group) => group.cities.length > 0 || group.state_name.toLowerCase().includes(q));
        },
        countryTriggerLabel() {
            if (!this.selectedCountries.length) {
                return 'Select countries';
            }
            if (this.selectedCountries.length <= 2) {
                return this.selectedCountries.map((c) => c.name).join(', ');
            }
            return `${this.selectedCountries.length} countries selected`;
        },
        stateTriggerLabel() {
            if (!this.selectedStates.length) {
                return 'All regions';
            }
            if (this.selectedStates.length <= 2) {
                return this.selectedStates.map((s) => s.name).join(', ');
            }
            return `${this.selectedStates.length} regions selected`;
        },
        cityTriggerLabel() {
            if (!this.selectedCities.length) {
                return 'All cities';
            }
            if (this.selectedCities.length <= 2) {
                return this.selectedCities.map((c) => c.name).join(', ');
            }
            return `${this.selectedCities.length} cities selected`;
        },
        isCountrySelected(code) {
            return this.selectedCountries.some((c) => c.code === code);
        },
        isStateSelected(countryCode, stateCode) {
            return this.selectedStates.some((s) => s.country_code === countryCode && s.code === stateCode);
        },
        isCitySelected(countryCode, stateCode, cityName) {
            return this.selectedCities.some((c) => c.key === cityKey(countryCode, stateCode, cityName));
        },
        async toggleCountry() {
            this.countryOpen = !this.countryOpen;
            if (this.countryOpen) {
                this.stateOpen = false;
                this.cityOpen = false;
                if (this.countryItems.length === 0) {
                    await this.fetchCountries();
                }
                await this.$nextTick();
                this.$refs.countrySearch?.focus();
            }
        },
        closeCountry() {
            this.countryOpen = false;
        },
        async fetchCountries() {
            this.countryLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.countryQuery.trim()) {
                    params.set('q', this.countryQuery.trim());
                }
                const res = await fetch(this.endpoints.countries + '?' + params.toString(), {
                    headers: GEO_HEADERS,
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    this.countryItems = [];
                    return;
                }
                const data = await res.json();
                this.countryItems = Array.isArray(data) ? data : [];
            } catch (e) {
                this.countryItems = [];
            } finally {
                this.countryLoading = false;
            }
        },
        async toggleCountryItem(item) {
            const idx = this.selectedCountries.findIndex((c) => c.code === item.code);
            if (idx >= 0) {
                this.selectedCountries.splice(idx, 1);
            } else {
                this.selectedCountries.push({ code: item.code, name: item.name });
            }
            await this.onCountriesChange();
        },
        async onCountriesChange() {
            const codes = new Set(this.selectedCountries.map((c) => c.code));
            this.selectedStates = this.selectedStates.filter((s) => codes.has(s.country_code));
            this.selectedCities = this.selectedCities.filter((c) => codes.has(c.country_code));
            this.showState = this.selectedCountries.length > 0;
            this.showCity = this.selectedStates.length > 0;
            if (!this.showState) {
                this.stateGroups = [];
                this.cityGroups = [];
                this.selectedStates = [];
                this.selectedCities = [];
                return;
            }
            await this.fetchAllStateGroups();
            if (this.selectedStates.length > 0) {
                await this.fetchAllCityGroups();
            } else {
                this.cityGroups = [];
                this.selectedCities = [];
                this.showCity = false;
            }
        },
        async fetchAllStateGroups() {
            this.stateLoading = true;
            const groups = [];
            try {
                const results = await Promise.all(
                    this.selectedCountries.map(async (country) => {
                        const res = await fetch(
                            this.endpoints.states + '?country=' + encodeURIComponent(country.code),
                            { headers: GEO_HEADERS, credentials: 'same-origin' },
                        );
                        const states = res.ok ? await res.json() : [];
                        return {
                            country_code: country.code,
                            country_name: country.name,
                            states: Array.isArray(states) ? states : [],
                        };
                    }),
                );
                for (const group of results) {
                    if (group.states.length > 0) {
                        groups.push(group);
                    }
                }
            } catch (e) {
                // keep empty groups
            } finally {
                this.stateGroups = groups;
                this.stateLoading = false;
            }
        },
        toggleState() {
            if (this.stateLoading || !this.showState) {
                return;
            }
            this.stateOpen = !this.stateOpen;
            if (this.stateOpen) {
                this.countryOpen = false;
                this.cityOpen = false;
                this.stateQuery = '';
                this.$nextTick(() => this.$refs.stateSearch?.focus());
            }
        },
        closeState() {
            this.stateOpen = false;
        },
        async toggleStateItem(state, group) {
            const idx = this.selectedStates.findIndex(
                (s) => s.country_code === group.country_code && s.code === state.code,
            );
            if (idx >= 0) {
                this.selectedStates.splice(idx, 1);
            } else {
                this.selectedStates.push({
                    code: state.code,
                    name: state.name,
                    country_code: group.country_code,
                    country_name: group.country_name,
                });
            }
            await this.onStatesChange();
        },
        clearAllStates() {
            this.selectedStates = [];
            this.onStatesChange();
        },
        async onStatesChange() {
            const keys = new Set(this.selectedStates.map((s) => stateKey(s.country_code, s.code)));
            this.selectedCities = this.selectedCities.filter((c) => keys.has(stateKey(c.country_code, c.state_code)));
            this.showCity = this.selectedStates.length > 0;
            if (!this.showCity) {
                this.cityGroups = [];
                this.selectedCities = [];
                return;
            }
            await this.fetchAllCityGroups();
        },
        async fetchAllCityGroups() {
            this.cityLoading = true;
            const groups = [];
            try {
                const results = await Promise.all(
                    this.selectedStates.map(async (state) => {
                        const params = new URLSearchParams({
                            country: state.country_code,
                            state: state.code,
                        });
                        if (this.cityQuery.trim()) {
                            params.set('q', this.cityQuery.trim());
                        }
                        const res = await fetch(this.endpoints.cities + '?' + params.toString(), {
                            headers: GEO_HEADERS,
                            credentials: 'same-origin',
                        });
                        const cities = res.ok ? await res.json() : [];
                        return {
                            country_code: state.country_code,
                            country_name: state.country_name,
                            state_code: state.code,
                            state_name: state.name,
                            cities: Array.isArray(cities) ? cities : [],
                        };
                    }),
                );
                for (const group of results) {
                    if (group.cities.length > 0) {
                        groups.push(group);
                    }
                }
            } catch (e) {
                // keep empty
            } finally {
                this.cityGroups = groups;
                this.cityLoading = false;
            }
        },
        async rebuildCityGroups() {
            if (this.selectedStates.length > 0) {
                await this.fetchAllCityGroups();
            }
        },
        async toggleCity() {
            if (this.cityLoading || !this.showCity) {
                return;
            }
            this.cityOpen = !this.cityOpen;
            if (this.cityOpen) {
                this.countryOpen = false;
                this.stateOpen = false;
                if (this.cityGroups.length === 0) {
                    await this.fetchAllCityGroups();
                }
                await this.$nextTick();
                this.$refs.citySearch?.focus();
            }
        },
        closeCity() {
            this.cityOpen = false;
        },
        toggleCityItem(cityName, group) {
            const key = cityKey(group.country_code, group.state_code, cityName);
            const idx = this.selectedCities.findIndex((c) => c.key === key);
            if (idx >= 0) {
                this.selectedCities.splice(idx, 1);
            } else {
                this.selectedCities.push({
                    key,
                    name: cityName,
                    country_code: group.country_code,
                    country_name: group.country_name,
                    state_code: group.state_code,
                    state_name: group.state_name,
                });
            }
        },
        clearAllCities() {
            this.selectedCities = [];
        },
        addRule() {
            const toAdd = [];

            if (this.selectedCities.length > 0) {
                for (const city of this.selectedCities) {
                    toAdd.push({
                        country: city.country_code,
                        country_name: city.country_name,
                        state: city.state_code,
                        state_name: city.state_name,
                        city: city.name,
                    });
                }
            } else if (this.selectedStates.length > 0) {
                for (const state of this.selectedStates) {
                    toAdd.push({
                        country: state.country_code,
                        country_name: state.country_name,
                        state: state.code,
                        state_name: state.name,
                        city: null,
                    });
                }
            } else if (this.selectedCountries.length > 0) {
                for (const country of this.selectedCountries) {
                    toAdd.push({
                        country: country.code,
                        country_name: country.name,
                        state: null,
                        state_name: null,
                        city: null,
                    });
                }
            } else {
                return;
            }

            for (const rule of toAdd) {
                const exists = this.rules.some((r) =>
                    r.country === rule.country
                    && (r.state || null) === rule.state
                    && (r.city || null) === rule.city
                );
                if (!exists) {
                    this.rules.push(rule);
                }
            }

            this.selectedCountries = [];
            this.selectedStates = [];
            this.selectedCities = [];
            this.stateGroups = [];
            this.cityGroups = [];
            this.showState = false;
            this.showCity = false;
            this.countryQuery = '';
            this.stateQuery = '';
            this.cityQuery = '';
        },
        removeRule(idx) {
            this.rules.splice(idx, 1);
        },
        ruleLabel(rule) {
            let label = rule.country_name || rule.country;
            if (rule.state_name || rule.state) {
                label += ' · ' + (rule.state_name || rule.state);
            }
            if (rule.city) {
                label += ' · ' + rule.city;
            }
            if (!rule.state && !rule.city) {
                label += ' · All regions';
            } else if (!rule.city) {
                label += ' · All cities';
            }
            return label;
        },
    };
}

export function registerGeoAudiencePicker(AlpineInstance) {
    AlpineInstance.data('geoAudiencePicker', geoAudiencePicker);
}
