<script>
function trafficLogs(initial) {
    return {
        urls: initial.urls,
        csrf: initial.csrf,
        domains: initial.domains || [],
        stats: { ...initial.initialStats },
        toast: { message: '', type: 'success' },
        loading: { traffic: false, blocklist: false },
        showBlocklist: false,
        perPage: 10,
        filters: {
            search: '',
            domain_id: '',
            action_taken: '',
            country: '',
            source: '',
            date: '',
            blocked_only: false,
        },
        manual: { ip: '', reason: '' },
        traffic: [],
        meta: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
        blocklist: [],
        get trackerLabel() {
            if (!this.filters.domain_id) return 'All Trackers';
            const match = this.domains.find((d) => String(d.id) === String(this.filters.domain_id));
            return match?.hostname || 'All Trackers';
        },
        get statusLabel() {
            return {
                '': 'All Statuses',
                allow: 'Allowed',
                flag: 'Flagged',
                block: 'Blocked',
            }[this.filters.action_taken] || 'All Statuses';
        },
        get countryLabel() {
            if (!this.filters.country) return 'Country / Geo Filter';
            const names = { US: 'United States', PK: 'Pakistan', IN: 'India', TR: 'Turkey' };
            return names[this.filters.country] || this.filters.country;
        },
        formatNumber(value) {
            return new Intl.NumberFormat().format(Number(value || 0));
        },
        notify(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => (this.toast.message = ''), 4000);
        },
        async request(url, method = 'GET', body = null) {
            const opts = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            };
            if (body) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
            const res = await fetch(url, opts);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },
        resetFilters() {
            this.filters = {
                search: '',
                domain_id: '',
                action_taken: '',
                country: '',
                source: '',
                date: '',
                blocked_only: false,
            };
        },
        setTracker(id) {
            this.filters.domain_id = id ? String(id) : '';
            this.loadTraffic(1);
        },
        setStatus(status) {
            this.filters.action_taken = status;
            this.loadTraffic(1);
        },
        setCountry(country) {
            this.filters.country = country;
            this.loadTraffic(1);
        },
        toggleBlockedOnly() {
            this.filters.blocked_only = !this.filters.blocked_only;
            this.loadTraffic(1);
        },
        filterTimer: null,
        debounceMs: window.PROMOTIX_FILTER_DEBOUNCE_MS || 600,
        scheduleLoadTraffic() {
            clearTimeout(this.filterTimer);
            this.filterTimer = setTimeout(() => this.loadTraffic(1), this.debounceMs);
        },
        async loadStats() {
            try {
                const data = await this.request(this.urls.stats);
                this.stats = {
                    total_requests: data.total_requests ?? 0,
                    threat_groups: data.threat_groups ?? 0,
                    blocked_traffic: data.blocked_traffic ?? data.blocked_requests ?? 0,
                    allow_lists: data.allow_lists ?? 0,
                };
            } catch (e) { /* silent */ }
        },
        async loadTraffic(page = 1) {
            this.loading.traffic = true;
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v === '' || v === false || v === null || v === undefined) return;
                    params.append(k, v === true ? '1' : v);
                });
                params.append('page', page);
                params.append('per_page', this.perPage);
                const data = await this.request(`${this.urls.traffic}?${params.toString()}`);
                this.traffic = data.data || [];
                this.meta = {
                    current_page: data.current_page || 1,
                    last_page: data.last_page || 1,
                    total: data.total || 0,
                    from: data.from || 0,
                    to: data.to || 0,
                };
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.traffic = false;
            }
        },
        goToPage(page) {
            if (page < 1 || page > (this.meta.last_page || 1)) return;
            this.loadTraffic(page);
        },
        async loadBlocklist() {
            this.loading.blocklist = true;
            try {
                const data = await this.request(this.urls.blocklist);
                this.blocklist = data.data || [];
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.blocklist = false;
            }
        },
        async blockIp(ip, blocked) {
            try {
                const data = await this.request(this.urls.block, 'POST', { ip, blocked, reason: blocked ? 'manual_block_from_logs' : 'manual_unblock' });
                this.notify(data.message || (blocked ? 'IP blocked.' : 'IP unblocked.'));
                this.loadStats();
                this.loadTraffic(this.meta.current_page);
                if (this.showBlocklist) this.loadBlocklist();
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
        async manualBlock() {
            if (!this.manual.ip) return;
            try {
                await this.request(this.urls.block, 'POST', { ip: this.manual.ip, blocked: true, reason: this.manual.reason || 'manual_block' });
                this.manual = { ip: '', reason: '' };
                this.notify('IP added to blocklist.');
                this.loadStats();
                this.loadBlocklist();
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
    };
}
</script>
