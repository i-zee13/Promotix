/**
 * Shared date-range picker for filter-bar calendar buttons and header trigger.
 */
export function figmaDateRangePicker() {
    const pad = (n) => String(n).padStart(2, '0');
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const stored = (() => {
        try {
            return JSON.parse(localStorage.getItem('promotix-date-range') || '{}');
        } catch (e) {
            return {};
        }
    })();
    const today = new Date();
    const todayStr = fmt(today);

    const dayCell = (dt, inMonth, selFrom, selTo, todayIso) => {
        const iso = fmt(dt);
        return {
            iso,
            day: dt.getDate(),
            inMonth,
            inRange: iso > selFrom && iso < selTo,
            isStart: iso === selFrom,
            isEnd: iso === selTo,
            isToday: iso === todayIso,
        };
    };

    return {
        calendarOpen: false,
        pickStart: null,
        viewMonth: new Date(today.getFullYear(), today.getMonth(), 1),
        from: stored.from || todayStr,
        to: stored.to || todayStr,
        rangeLabel() {
            const display = (iso) => {
                if (!iso) return '—';
                const [y, m, d] = iso.split('-');
                return `${m}/${d}/${y}`;
            };
            if (this.from === this.to) return display(this.from);
            return `${display(this.from)} – ${display(this.to)}`;
        },
        monthLabel() {
            return this.viewMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },
        calendarDays() {
            const year = this.viewMonth.getFullYear();
            const month = this.viewMonth.getMonth();
            const first = new Date(year, month, 1);
            const startDay = first.getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const rangeFrom = this.pickStart || this.from;
            const rangeTo = this.pickStart ? this.pickStart : this.to;
            const selFrom = rangeFrom <= rangeTo ? rangeFrom : rangeTo;
            const selTo = rangeFrom <= rangeTo ? rangeTo : rangeFrom;
            const cells = [];
            const prevMonthDays = new Date(year, month, 0).getDate();

            for (let i = startDay - 1; i >= 0; i--) {
                cells.push(dayCell(new Date(year, month - 1, prevMonthDays - i), false, selFrom, selTo, todayStr));
            }
            for (let d = 1; d <= daysInMonth; d++) {
                cells.push(dayCell(new Date(year, month, d), true, selFrom, selTo, todayStr));
            }
            let nextDay = 1;
            while (cells.length % 7 !== 0) {
                cells.push(dayCell(new Date(year, month + 1, nextDay++), false, selFrom, selTo, todayStr));
            }
            return cells;
        },
        toggleCalendar(forceOpen = false) {
            this.calendarOpen = forceOpen ? true : !this.calendarOpen;
            if (this.calendarOpen) {
                this.pickStart = null;
                this.viewMonth = new Date((this.from || todayStr) + 'T12:00:00');
                this.viewMonth = new Date(this.viewMonth.getFullYear(), this.viewMonth.getMonth(), 1);
            }
        },
        prevMonth() {
            this.viewMonth = new Date(this.viewMonth.getFullYear(), this.viewMonth.getMonth() - 1, 1);
        },
        nextMonth() {
            this.viewMonth = new Date(this.viewMonth.getFullYear(), this.viewMonth.getMonth() + 1, 1);
        },
        selectDay(iso) {
            if (!this.pickStart) {
                this.pickStart = iso;
                this.from = iso;
                this.to = iso;
                this.applyRange(true);
                return;
            }
            let from = this.pickStart;
            let to = iso;
            if (to < from) [from, to] = [to, from];
            this.from = from;
            this.to = to;
            this.pickStart = null;
            this.calendarOpen = false;
            this.applyRange(true);
        },
        applyRange(showLoader = false) {
            if (showLoader) {
                window.promotixPageLoader?.show('Loading data…');
            }
            localStorage.setItem('promotix-date-range', JSON.stringify({ from: this.from, to: this.to }));
            window.dispatchEvent(new CustomEvent('promotix:date-range', { detail: { from: this.from, to: this.to } }));
        },
        init() {
            const migrateKey = 'promotix-date-default-today-v1';
            if (!localStorage.getItem(migrateKey)) {
                const t = fmt(new Date());
                this.from = t;
                this.to = t;
                localStorage.setItem(migrateKey, '1');
            }
            this.applyRange(false);
            window.addEventListener('promotix:open-date-calendar', () => this.toggleCalendar(true));
        },
    };
}
