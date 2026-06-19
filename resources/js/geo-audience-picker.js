import Alpine from 'alpinejs';

const GEO_HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function debounce(fn, ms = 300) {
    let timer = null;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
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
        draft: {
            country: '',
            country_name: '',
            state: '',
            state_name: '',
            city: '',
            city_name: '',
        },
        showState: false,
        showCity: false,
        countryOpen: false,
        stateOpen: false,
        cityOpen: false,
        countryQuery: '',
        stateQuery: '',
        cityQuery: '',
        countryItems: [],
        stateItems: [],
        cityItems: [],
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
            this.searchCities = debounce(() => this.fetchCities(), 280);
        },
        get filteredStates() {
            const q = this.stateQuery.trim().toLowerCase();
            if (!q) {
                return this.stateItems;
            }
            return this.stateItems.filter((s) =>
                s.name.toLowerCase().includes(q) || s.code.toLowerCase().includes(q)
            );
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
        pickCountry(item) {
            this.draft.country = item.code;
            this.draft.country_name = item.name;
            this.countryOpen = false;
            this.countryQuery = '';
            this.onCountryChange();
        },
        clearCountry() {
            this.draft.country = '';
            this.draft.country_name = '';
            this.countryQuery = '';
            this.countryItems = [];
            this.resetState();
            this.resetCity();
            this.showState = false;
            this.showCity = false;
        },
        async onCountryChange() {
            this.resetState();
            this.resetCity();
            this.showState = false;
            this.showCity = false;
            if (!this.draft.country) {
                return;
            }
            this.stateLoading = true;
            try {
                const res = await fetch(
                    this.endpoints.states + '?country=' + encodeURIComponent(this.draft.country),
                    { headers: GEO_HEADERS, credentials: 'same-origin' },
                );
                const states = res.ok ? await res.json() : [];
                if (!Array.isArray(states) || states.length === 0) {
                    return;
                }
                this.stateItems = states;
                this.showState = true;
            } catch (e) {
                this.showState = false;
            } finally {
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
        pickState(item) {
            if (!item) {
                this.draft.state = '';
                this.draft.state_name = '';
            } else {
                this.draft.state = item.code;
                this.draft.state_name = item.name;
            }
            this.stateOpen = false;
            this.stateQuery = '';
            this.onStateChange();
        },
        resetState() {
            this.stateOpen = false;
            this.stateQuery = '';
            this.stateItems = [];
            this.draft.state = '';
            this.draft.state_name = '';
        },
        async onStateChange() {
            this.resetCity();
            this.showCity = false;
            if (!this.draft.country || !this.draft.state) {
                return;
            }
            await this.fetchCities();
            if (this.cityItems.length > 0) {
                this.showCity = true;
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
                if (this.cityItems.length === 0) {
                    await this.fetchCities();
                }
                await this.$nextTick();
                this.$refs.citySearch?.focus();
            }
        },
        closeCity() {
            this.cityOpen = false;
        },
        async fetchCities() {
            if (!this.draft.country || !this.draft.state) {
                this.cityItems = [];
                return;
            }
            this.cityLoading = true;
            try {
                const params = new URLSearchParams({
                    country: this.draft.country,
                    state: this.draft.state,
                });
                if (this.cityQuery.trim()) {
                    params.set('q', this.cityQuery.trim());
                }
                const res = await fetch(this.endpoints.cities + '?' + params.toString(), {
                    headers: GEO_HEADERS,
                    credentials: 'same-origin',
                });
                const cities = res.ok ? await res.json() : [];
                this.cityItems = Array.isArray(cities) ? cities : [];
            } catch (e) {
                this.cityItems = [];
            } finally {
                this.cityLoading = false;
            }
        },
        pickCity(name) {
            if (!name) {
                this.draft.city = '';
                this.draft.city_name = '';
            } else {
                this.draft.city = name;
                this.draft.city_name = name;
            }
            this.cityOpen = false;
            this.cityQuery = '';
        },
        resetCity() {
            this.cityOpen = false;
            this.cityQuery = '';
            this.cityItems = [];
            this.draft.city = '';
            this.draft.city_name = '';
        },
        addRule() {
            if (!this.draft.country) {
                return;
            }
            const rule = {
                country: this.draft.country,
                country_name: this.draft.country_name || this.draft.country,
                state: this.draft.state || null,
                state_name: this.draft.state ? (this.draft.state_name || this.draft.state) : null,
                city: this.draft.city || null,
            };
            const exists = this.rules.some((r) =>
                r.country === rule.country
                && (r.state || null) === rule.state
                && (r.city || null) === rule.city
            );
            if (!exists) {
                this.rules.push(rule);
            }
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
