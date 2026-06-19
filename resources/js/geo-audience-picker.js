import Alpine from 'alpinejs';

export function geoAudiencePicker(initial = {}) {
    return {
        countries: Array.isArray(initial.countries) ? initial.countries : [],
        states: [],
        cities: [],
        rules: Array.isArray(initial.rules) ? initial.rules : [],
        draft: { country: '', state: '', city: '' },
        loadingStates: false,
        loadingCities: false,
        get jsonValue() {
            return JSON.stringify({ rules: this.rules });
        },
        async init() {
            if (this.countries.length === 0) {
                try {
                    const res = await fetch('/paid-marketing/geo/countries', {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (res.ok) {
                        this.countries = await res.json();
                    }
                } catch (e) {
                    this.countries = [];
                }
            }
        },
        async loadStates() {
            this.draft.state = '';
            this.draft.city = '';
            this.states = [];
            this.cities = [];
            if (!this.draft.country) {
                return;
            }
            this.loadingStates = true;
            try {
                const res = await fetch('/paid-marketing/geo/states?country=' + encodeURIComponent(this.draft.country), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.states = res.ok ? await res.json() : [];
            } catch (e) {
                this.states = [];
            } finally {
                this.loadingStates = false;
            }
        },
        async loadCities() {
            this.draft.city = '';
            this.cities = [];
            if (!this.draft.country) {
                return;
            }
            this.loadingCities = true;
            try {
                const params = new URLSearchParams({ country: this.draft.country });
                if (this.draft.state) {
                    params.set('state', this.draft.state);
                }
                const res = await fetch('/paid-marketing/geo/cities?' + params.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.cities = res.ok ? await res.json() : [];
            } catch (e) {
                this.cities = [];
            } finally {
                this.loadingCities = false;
            }
        },
        addRule() {
            if (!this.draft.country) {
                return;
            }
            const countryObj = this.countries.find((c) => c.code === this.draft.country);
            const stateObj = this.states.find((s) => s.code === this.draft.state);
            const rule = {
                country: this.draft.country,
                country_name: countryObj ? countryObj.name : this.draft.country,
                state: this.draft.state || null,
                state_name: stateObj ? stateObj.name : (this.draft.state || null),
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
