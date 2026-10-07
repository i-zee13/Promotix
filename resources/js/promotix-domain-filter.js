/**
 * Persist selected domain across refresh (and analytics page navigation).
 * Same pattern as promotix-date-range: localStorage + optional ?domain_id= URL.
 */
const STORAGE_KEY = 'promotix-domain-id';

function readFromUrl() {
    try {
        const v = new URLSearchParams(window.location.search).get('domain_id');
        return v != null && String(v) !== '' ? String(v) : '';
    } catch (e) {
        return '';
    }
}

function syncUrl(id) {
    try {
        const url = new URL(window.location.href);
        if (id) url.searchParams.set('domain_id', id);
        else url.searchParams.delete('domain_id');
        window.history.replaceState({}, '', url);
    } catch (e) {}
}

export const PromotixDomainFilter = {
    read() {
        const fromUrl = readFromUrl();
        if (fromUrl) return fromUrl;
        try {
            return String(localStorage.getItem(STORAGE_KEY) || '');
        } catch (e) {
            return '';
        }
    },

    write(id) {
        const v = String(id || '');
        try {
            if (v) localStorage.setItem(STORAGE_KEY, v);
            else localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
        syncUrl(v);
    },

    /**
     * Apply saved domain onto a filters object (`domain_id` or `domainId`).
     */
    applyTo(filters, domainOptions = [], field = 'domain_id') {
        if (!filters || typeof filters !== 'object') return;
        const id = this.read();
        if (!id) {
            if (!filters[field]) filters[field] = '';
            return;
        }
        const opts = Array.isArray(domainOptions) ? domainOptions : [];
        if (opts.length && !opts.some((d) => String(d?.id) === String(id))) {
            filters[field] = '';
            this.write('');
            return;
        }
        filters[field] = String(id);
        syncUrl(String(id));
    },
};

window.PromotixDomainFilter = PromotixDomainFilter;
