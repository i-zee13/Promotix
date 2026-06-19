import Alpine from 'alpinejs';
import $ from 'jquery';
import 'select2/dist/css/select2.min.css';

window.$ = window.jQuery = $;

const GEO_HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function select2BaseOptions(placeholder) {
    return {
        placeholder,
        allowClear: true,
        width: '100%',
        dropdownParent: document.body,
        minimumInputLength: 0,
    };
}

function destroySelect2(el) {
    if (!el) {
        return;
    }
    const $el = $(el);
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.off('change.geo');
        $el.select2('destroy');
    }
}

export function geoAudiencePicker(initial = {}) {
    return {
        rules: Array.isArray(initial.rules) ? initial.rules : [],
        draft: {
            country: '',
            country_name: '',
            state: '',
            state_name: '',
            city: '',
        },
        showState: false,
        showCity: false,
        loadingStates: false,
        loadingCities: false,
        get jsonValue() {
            return JSON.stringify({ rules: this.rules });
        },
        async init() {
            await this.$nextTick();
            this.initCountrySelect();
        },
        initCountrySelect() {
            const el = this.$refs.countrySelect;
            if (!el) {
                return;
            }

            destroySelect2(el);

            $(el).select2({
                ...select2BaseOptions('Search country…'),
                ajax: {
                    url: '/paid-marketing/geo/countries',
                    delay: 250,
                    cache: true,
                    data: (params) => ({ q: params.term || '' }),
                    processResults: (data) => ({
                        results: (Array.isArray(data) ? data : []).map((c) => ({
                            id: c.code,
                            text: c.name,
                        })),
                    }),
                },
            }).on('change.geo', () => {
                const selected = $(el).select2('data')[0];
                this.draft.country = selected?.id || '';
                this.draft.country_name = selected?.text || '';
                this.onCountryChange();
            });
        },
        async onCountryChange() {
            this.resetStateSelect();
            this.resetCitySelect();
            this.showState = false;
            this.showCity = false;

            if (!this.draft.country) {
                return;
            }

            this.loadingStates = true;
            try {
                const res = await fetch(
                    '/paid-marketing/geo/states?country=' + encodeURIComponent(this.draft.country),
                    { headers: GEO_HEADERS },
                );
                const states = res.ok ? await res.json() : [];
                if (!Array.isArray(states) || states.length === 0) {
                    return;
                }
                this.showState = true;
                await this.$nextTick();
                this.initStateSelect(states);
            } catch (e) {
                this.showState = false;
            } finally {
                this.loadingStates = false;
            }
        },
        initStateSelect(states) {
            const el = this.$refs.stateSelect;
            if (!el) {
                return;
            }

            destroySelect2(el);
            el.innerHTML = '';

            $(el).select2({
                ...select2BaseOptions('All regions'),
                data: [{ id: '', text: 'All regions' }].concat(
                    states.map((s) => ({ id: s.code, text: s.name })),
                ),
            }).on('change.geo', () => {
                const selected = $(el).select2('data')[0];
                this.draft.state = selected?.id || '';
                this.draft.state_name = selected?.id ? (selected.text || '') : '';
                this.onStateChange();
            });
        },
        async onStateChange() {
            this.resetCitySelect();
            this.showCity = false;

            if (!this.draft.country || !this.draft.state) {
                return;
            }

            this.loadingCities = true;
            try {
                const params = new URLSearchParams({
                    country: this.draft.country,
                    state: this.draft.state,
                });
                const res = await fetch('/paid-marketing/geo/cities?' + params.toString(), {
                    headers: GEO_HEADERS,
                });
                const cities = res.ok ? await res.json() : [];
                if (!Array.isArray(cities) || cities.length === 0) {
                    return;
                }
                this.showCity = true;
                await this.$nextTick();
                this.initCitySelect();
            } catch (e) {
                this.showCity = false;
            } finally {
                this.loadingCities = false;
            }
        },
        initCitySelect() {
            const el = this.$refs.citySelect;
            if (!el) {
                return;
            }

            destroySelect2(el);

            $(el).select2({
                ...select2BaseOptions('All cities'),
                ajax: {
                    url: '/paid-marketing/geo/cities',
                    delay: 250,
                    cache: true,
                    data: (params) => ({
                        country: this.draft.country,
                        state: this.draft.state,
                        q: params.term || '',
                    }),
                    processResults: (data) => ({
                        results: (Array.isArray(data) ? data : []).map((name) => ({
                            id: name,
                            text: name,
                        })),
                    }),
                },
            }).on('change.geo', () => {
                const selected = $(el).select2('data')[0];
                this.draft.city = selected?.id || '';
            });
        },
        resetStateSelect() {
            destroySelect2(this.$refs.stateSelect);
            if (this.$refs.stateSelect) {
                this.$refs.stateSelect.innerHTML = '<option value=""></option>';
            }
            this.draft.state = '';
            this.draft.state_name = '';
        },
        resetCitySelect() {
            destroySelect2(this.$refs.citySelect);
            if (this.$refs.citySelect) {
                this.$refs.citySelect.innerHTML = '<option value=""></option>';
            }
            this.draft.city = '';
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
