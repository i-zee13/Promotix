/**
 * Google Ads–style date range picker (global).
 * Persists via localStorage['promotix-date-range'] + CustomEvent('promotix:date-range').
 * Pass { embedded: true } for inline use (e.g. Settings → Data Reports) — no global persistence.
 */
export function figmaDateRangePicker(options = {}) {
    const embedded = !!options.embedded;
    const pad = (n) => String(n).padStart(2, '0');
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parseIso = (iso) => {
        if (!iso) return null;
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(y, m - 1, d, 12, 0, 0);
    };
    const addDays = (d, n) => {
        const x = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        x.setDate(x.getDate() + n);
        return x;
    };
    const startOfWeek = (d) => {
        const x = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        x.setDate(x.getDate() - x.getDay());
        return x;
    };
    const endOfWeek = (d) => addDays(startOfWeek(d), 6);
    const startOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1, 12, 0, 0);
    const endOfMonth = (d) => new Date(d.getFullYear(), d.getMonth() + 1, 0, 12, 0, 0);
    const displayDate = (iso) => {
        if (!iso) return '';
        const dt = parseIso(iso);
        if (!dt) return '';
        return dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    };
    const displayInput = (iso) => {
        if (!iso) return '';
        const [y, m, d] = iso.split('-');
        return `${Number(m)}/${Number(d)}/${y}`;
    };

    const stored = (() => {
        try {
            return JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
        } catch (e) {
            return {};
        }
    })();
    const storedCompare = (() => {
        try {
            return JSON.parse(localStorage.getItem('promotix-date-compare') || '{}');
        } catch (e) {
            return {};
        }
    })();

    const startOfWeekMon = (d) => {
        const x = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        const day = x.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        x.setDate(x.getDate() + diff);
        return x;
    };

    const today = new Date();
    const todayStr = fmt(today);

    const presets = [
        { id: 'today', label: 'Today' },
        { id: 'yesterday', label: 'Yesterday' },
        { id: 'last_7', label: 'Last 7 days' },
        { id: 'last_14', label: 'Last 14 days' },
        { id: 'last_30', label: 'Last 30 days' },
        { id: 'this_week', label: 'This week (Sun – Today)' },
        { id: 'this_week_mon', label: 'This week (Mon – Today)' },
        { id: 'last_week', label: 'Last week (Sun – Sat)' },
        { id: 'last_week_mon', label: 'Last week (Mon – Sun)' },
        { id: 'this_month', label: 'This month' },
        { id: 'last_month', label: 'Last month' },
        { id: 'this_year', label: 'This year' },
        { id: 'all_time', label: 'All time (24 mo)' },
    ];

    const rangeForPreset = (id) => {
        const t = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 12, 0, 0);
        if (id === 'today') return { from: fmt(t), to: fmt(t) };
        if (id === 'yesterday') {
            const y = addDays(t, -1);
            return { from: fmt(y), to: fmt(y) };
        }
        if (id === 'this_week') return { from: fmt(startOfWeek(t)), to: fmt(t) };
        if (id === 'this_week_mon') return { from: fmt(startOfWeekMon(t)), to: fmt(t) };
        if (id === 'last_7') return { from: fmt(addDays(t, -6)), to: fmt(t) };
        if (id === 'last_week') {
            const end = addDays(startOfWeek(t), -1);
            return { from: fmt(startOfWeek(end)), to: fmt(end) };
        }
        if (id === 'last_week_mon') {
            const thisMon = startOfWeekMon(t);
            const end = addDays(thisMon, -1);
            const start = startOfWeekMon(end);
            return { from: fmt(start), to: fmt(end) };
        }
        if (id === 'last_14') return { from: fmt(addDays(t, -13)), to: fmt(t) };
        if (id === 'this_month') return { from: fmt(startOfMonth(t)), to: fmt(t) };
        if (id === 'last_30') return { from: fmt(addDays(t, -29)), to: fmt(t) };
        if (id === 'last_month') {
            const prev = new Date(t.getFullYear(), t.getMonth() - 1, 1, 12, 0, 0);
            return { from: fmt(startOfMonth(prev)), to: fmt(endOfMonth(prev)) };
        }
        if (id === 'this_year') return { from: fmt(new Date(t.getFullYear(), 0, 1, 12, 0, 0)), to: fmt(t) };
        // Cap "All time" to last 24 months — unbounded 2020→now blew up heavy paid queries.
        if (id === 'all_time') return { from: fmt(new Date(t.getFullYear() - 2, t.getMonth(), t.getDate(), 12, 0, 0)), to: fmt(t) };
        return { from: todayStr, to: todayStr };
    };

    const detectPreset = (from, to) => {
        for (const p of presets) {
            const r = rangeForPreset(p.id);
            if (r.from === from && r.to === to) return p.id;
        }
        return 'custom';
    };

    return {
        embedded,
        calendarOpen: embedded,
        mobileView: 'list', // list | custom
        pickStart: null,
        from: stored.from || todayStr,
        to: stored.to || todayStr,
        draftFrom: stored.from || todayStr,
        draftTo: stored.to || todayStr,
        activePreset: detectPreset(stored.from || todayStr, stored.to || todayStr),
        daysUpToToday: 30,
        daysUpToYesterday: 30,
        compareEnabled: !!storedCompare.enabled,
        months: [],
        scrollMonthKey: '',
        presets,
        isMobile() {
            return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches;
        },
        lockBody(lock) {
            if (typeof document === 'undefined') return;
            document.documentElement.classList.toggle('figma-gads-calendar-open', !!lock);
            document.body.style.overflow = lock ? 'hidden' : '';
        },

        rangeLabel() {
            if (this.from === this.to) return displayDate(this.from) || displayInput(this.from);
            return `${displayInput(this.from)} – ${displayInput(this.to)}`;
        },
        shortRangeLabel() {
            const preset = this.presets.find((p) => p.id === this.activePreset);
            if (preset && this.activePreset !== 'custom') return preset.label;
            if (this.from === this.to) return displayDate(this.from) || 'Custom';
            return `${displayInput(this.from)} – ${displayInput(this.to)}`;
        },
        draftFromLabel() {
            return displayDate(this.draftFrom) || displayInput(this.draftFrom);
        },
        draftToLabel() {
            return displayDate(this.draftTo) || displayInput(this.draftTo);
        },

        buildMonths(anchorIso) {
            const anchor = parseIso(anchorIso || this.draftFrom || todayStr) || today;
            const list = [];
            for (let i = -6; i <= 6; i++) {
                const m = new Date(anchor.getFullYear(), anchor.getMonth() + i, 1, 12, 0, 0);
                list.push(this.monthBlock(m));
            }
            this.months = list;
            this.scrollMonthKey = fmt(new Date(anchor.getFullYear(), anchor.getMonth(), 1));
        },

        monthBlock(monthDate) {
            const year = monthDate.getFullYear();
            const month = monthDate.getMonth();
            const first = new Date(year, month, 1, 12, 0, 0);
            const startDay = first.getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const selFrom = this.draftFrom <= this.draftTo ? this.draftFrom : this.draftTo;
            const selTo = this.draftFrom <= this.draftTo ? this.draftTo : this.draftFrom;
            const previewFrom = this.pickStart || selFrom;
            const previewTo = this.pickStart || selTo;
            const pf = previewFrom <= previewTo ? previewFrom : previewTo;
            const pt = previewFrom <= previewTo ? previewTo : previewFrom;
            const cells = [];
            const prevMonthDays = new Date(year, month, 0).getDate();

            for (let i = startDay - 1; i >= 0; i--) {
                const dt = new Date(year, month - 1, prevMonthDays - i, 12, 0, 0);
                cells.push(this.dayCell(dt, false, pf, pt));
            }
            for (let d = 1; d <= daysInMonth; d++) {
                cells.push(this.dayCell(new Date(year, month, d, 12, 0, 0), true, pf, pt));
            }
            let nextDay = 1;
            while (cells.length % 7 !== 0) {
                cells.push(this.dayCell(new Date(year, month + 1, nextDay++, 12, 0, 0), false, pf, pt));
            }

            return {
                key: fmt(first),
                label: first.toLocaleDateString(undefined, { month: 'short', year: 'numeric' }).toUpperCase(),
                cells,
            };
        },

        dayCell(dt, inMonth, selFrom, selTo) {
            const iso = fmt(dt);
            // Outside (padded) days can share the same ISO as an adjacent in-month day.
            // Only paint selection styles on in-month cells so prev-month 31 ≠ this-month 31.
            return {
                iso,
                day: dt.getDate(),
                inMonth,
                inRange: inMonth && iso > selFrom && iso < selTo,
                isStart: inMonth && iso === selFrom,
                isEnd: inMonth && iso === selTo,
                isToday: iso === todayStr,
            };
        },

        refreshMonths() {
            const anchor = this.draftFrom || todayStr;
            this.buildMonths(anchor);
        },

        toggleCalendar(forceOpen = false) {
            this.calendarOpen = forceOpen ? true : !this.calendarOpen;
            if (this.calendarOpen) {
                this.pickStart = null;
                this.draftFrom = this.from;
                this.draftTo = this.to;
                this.activePreset = detectPreset(this.from, this.to);
                this.mobileView = 'list';
                this.refreshMonths();
                this.lockBody(this.isMobile());
                this.$nextTick?.(() => this.scrollToMonth(this.scrollMonthKey));
            } else {
                this.lockBody(false);
            }
        },

        scrollToMonth(key) {
            const root = this.$refs?.monthScroller || this.$refs?.mobileMonthScroller;
            if (!root || !key) return;
            const el = root.querySelector(`[data-month-key="${key}"]`);
            if (!el) return;
            // Scroll only the calendar scroller — never the page (scrollIntoView jumps the window).
            const rootTop = root.getBoundingClientRect().top;
            const elTop = el.getBoundingClientRect().top;
            root.scrollTop += elTop - rootTop;
        },

        jumpMonth(delta) {
            const anchor = parseIso(this.scrollMonthKey || this.draftFrom || todayStr) || today;
            const next = new Date(anchor.getFullYear(), anchor.getMonth() + delta, 1, 12, 0, 0);
            this.buildMonths(fmt(next));
            this.$nextTick?.(() => this.scrollToMonth(this.scrollMonthKey));
        },

        selectPreset(id) {
            const r = rangeForPreset(id);
            this.activePreset = id;
            this.draftFrom = r.from;
            this.draftTo = r.to;
            this.pickStart = null;
            this.refreshMonths();
        },

        /** Mobile Google Ads behavior: picking a preset applies immediately. */
        selectPresetMobile(id) {
            this.selectPreset(id);
            this.applyCalendar();
        },

        openCustomMobile() {
            this.mobileView = 'custom';
            this.activePreset = 'custom';
            this.pickStart = null;
            this.refreshMonths();
            this.$nextTick?.(() => this.scrollToMonth(this.scrollMonthKey));
        },

        backToMobileList() {
            this.mobileView = 'list';
            this.pickStart = null;
            this.draftFrom = this.from;
            this.draftTo = this.to;
            this.activePreset = detectPreset(this.from, this.to);
        },

        applyDaysUpTo(kind) {
            const t = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 12, 0, 0);
            const end = kind === 'yesterday' ? addDays(t, -1) : t;
            const n = Math.max(1, Number(kind === 'yesterday' ? this.daysUpToYesterday : this.daysUpToToday) || 30);
            const start = addDays(end, -(n - 1));
            this.activePreset = 'custom';
            this.draftFrom = fmt(start);
            this.draftTo = fmt(end);
            this.pickStart = null;
            this.refreshMonths();
        },

        selectDay(iso, inMonth) {
            if (!inMonth) return;
            if (!this.pickStart) {
                this.pickStart = iso;
                this.draftFrom = iso;
                this.draftTo = iso;
                this.activePreset = 'custom';
                this.refreshMonths();
                return;
            }
            let from = this.pickStart;
            let to = iso;
            if (to < from) [from, to] = [to, from];
            this.draftFrom = from;
            this.draftTo = to;
            this.pickStart = null;
            this.activePreset = 'custom';
            this.refreshMonths();
        },

        parseDraftInput(which, value) {
            const raw = String(value || '').trim();
            let m = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (!m) m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!m) return;
            let y; let mo; let d;
            if (m[1].length === 4) {
                y = Number(m[1]); mo = Number(m[2]); d = Number(m[3]);
            } else {
                mo = Number(m[1]); d = Number(m[2]); y = Number(m[3]);
            }
            const dt = new Date(y, mo - 1, d, 12, 0, 0);
            if (Number.isNaN(dt.getTime())) return;
            const iso = fmt(dt);
            if (which === 'from') this.draftFrom = iso;
            else this.draftTo = iso;
            this.activePreset = 'custom';
            this.pickStart = null;
            this.refreshMonths();
        },

        cancelCalendar() {
            if (this.embedded) {
                this.pickStart = null;
                this.draftFrom = this.from;
                this.draftTo = this.to;
                this.activePreset = detectPreset(this.from, this.to);
                this.refreshMonths();
                return;
            }
            this.calendarOpen = false;
            this.mobileView = 'list';
            this.pickStart = null;
            this.draftFrom = this.from;
            this.draftTo = this.to;
            this.lockBody(false);
        },

        applyCalendar() {
            let from = this.draftFrom;
            let to = this.draftTo;
            if (!from || !to) return;
            if (to < from) [from, to] = [to, from];
            this.from = from;
            this.to = to;
            this.activePreset = detectPreset(from, to);
            this.pickStart = null;
            if (this.embedded) {
                this.refreshMonths();
                this.$dispatch('figma-embedded-range-applied', { from, to });
                return;
            }
            this.calendarOpen = false;
            this.mobileView = 'list';
            this.lockBody(false);
            this.applyRange(true);
        },

        applyRange(showLoader = false) {
            if (showLoader) {
                window.promotixPageLoader?.show('Loading data…');
            }
            localStorage.setItem('promotix-date-range', JSON.stringify({ from: this.from, to: this.to }));
            localStorage.setItem('promotix-date-compare', JSON.stringify({ enabled: this.compareEnabled }));
            window.dispatchEvent(new CustomEvent('promotix:date-range', {
                detail: { from: this.from, to: this.to, compare: this.compareEnabled },
            }));
        },

        initEmbedded(from, to) {
            const f = from || todayStr;
            const t = to || todayStr;
            this.from = f;
            this.to = t;
            this.draftFrom = f;
            this.draftTo = t;
            this.activePreset = detectPreset(f, t);
            this.calendarOpen = true;
            this.pickStart = null;
            this.refreshMonths();
            this.$nextTick?.(() => this.scrollToMonth(this.scrollMonthKey));
        },

        syncExternalRange(from, to) {
            if (!from || !to) return;
            this.from = from;
            this.to = to;
            this.draftFrom = from;
            this.draftTo = to;
            this.activePreset = detectPreset(from, to);
            this.pickStart = null;
            this.refreshMonths();
        },

        init() {
            if (this.embedded) {
                return;
            }
            const migrateKey = 'promotix-date-default-today-v1';
            if (!localStorage.getItem(migrateKey)) {
                const t = fmt(new Date());
                this.from = t;
                this.to = t;
                localStorage.setItem(migrateKey, '1');
            }
            this.draftFrom = this.from;
            this.draftTo = this.to;
            this.activePreset = detectPreset(this.from, this.to);
            this.applyRange(false);
            window.addEventListener('promotix:open-date-calendar', () => this.toggleCalendar(true));
            window.addEventListener('promotix:date-range', (e) => {
                const detail = e.detail || {};
                if (detail.from && detail.to && (detail.from !== this.from || detail.to !== this.to)) {
                    this.from = detail.from;
                    this.to = detail.to;
                    this.draftFrom = detail.from;
                    this.draftTo = detail.to;
                    this.activePreset = detectPreset(this.from, this.to);
                }
            });
            window.addEventListener('resize', () => {
                if (this.calendarOpen) this.lockBody(this.isMobile());
            });
        },
    };
}
