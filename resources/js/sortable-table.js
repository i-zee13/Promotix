/**
 * Shared client-side sorting for Alpine / vanilla admin tables.
 */
export function sortRows(rows, key, direction = 'asc', numericKeys = []) {
    const list = Array.isArray(rows) ? [...rows] : [];
    const sortKey = String(key || '').trim();
    if (!sortKey) return list;

    const dir = String(direction).toLowerCase() === 'desc' ? -1 : 1;
    const numeric = new Set(numericKeys);

    return list.sort((a, b) => {
        const left = a?.[sortKey];
        const right = b?.[sortKey];

        if (left == null && right == null) return 0;
        if (left == null || left === '') return 1;
        if (right == null || right === '') return -1;

        const bothNumeric = numeric.has(sortKey)
            || (typeof left === 'number' && typeof right === 'number')
            || (!Number.isNaN(Number(left)) && !Number.isNaN(Number(right)) && String(left).trim() !== '' && String(right).trim() !== '');

        let cmp;
        if (bothNumeric) {
            cmp = Number(left) - Number(right);
        } else {
            cmp = String(left).localeCompare(String(right), undefined, { numeric: true, sensitivity: 'base' });
        }

        return cmp * dir;
    });
}

export function toggleSort(currentKey, nextKey, currentDir = 'asc') {
    if (currentKey === nextKey) {
        return { key: nextKey, dir: String(currentDir).toLowerCase() === 'asc' ? 'desc' : 'asc' };
    }

    return { key: nextKey, dir: 'asc' };
}

export function sortStateClass(activeKey, columnKey, direction) {
    if (activeKey !== columnKey) return 'is-sortable';
    return String(direction).toLowerCase() === 'desc' ? 'is-sortable is-desc' : 'is-sortable is-asc';
}

/**
 * Alpine mixin helpers — spread into page components:
 *   Object.assign(component, window.promotixSortable.alpineHelpers())
 */
export function alpineHelpers(defaults = {}) {
    const numericKeys = defaults.numericKeys || [];

    return {
        sortKey: defaults.sortKey || '',
        sortDir: defaults.sortDir || 'asc',
        sortNumericKeys: numericKeys,
        setSort(key) {
            if (!key || key === 'session_recording') return;
            const next = toggleSort(this.sortKey, key, this.sortDir);
            this.sortKey = next.key;
            this.sortDir = next.dir;
        },
        sortClass(key) {
            return sortStateClass(this.sortKey, key, this.sortDir);
        },
        sorted(rows) {
            return sortRows(rows || [], this.sortKey, this.sortDir, this.sortNumericKeys || numericKeys);
        },
    };
}

export function registerSortableTable(AlpineInstance) {
    AlpineInstance.data('promotixSortableDemo', () => alpineHelpers());
}

window.promotixSortable = {
    sortRows,
    toggleSort,
    sortStateClass,
    alpineHelpers,
};
