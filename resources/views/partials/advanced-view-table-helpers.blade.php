<script>
window.promotixAdvTableHelpers = {
    isRichCol(key) {
        return ['country', 'intel_risk_score', 'intel_risk_level', 'action_taken', 'status', 'session_recording', 'cta_clicks', 'tel_clicks', 'form_starts', 'form_submits', 'add_to_cart', 'checkout', 'purchase', 'session_id'].includes(key);
    },
    countryCode(rowOrCode) {
        const raw = (rowOrCode && typeof rowOrCode === 'object')
            ? String(rowOrCode.country || rowOrCode.code || '').trim()
            : String(rowOrCode || '').trim();
        if (/^[a-z]{2}$/i.test(raw)) return raw.toUpperCase();
        const names = {
            'united states': 'US', usa: 'US', 'united kingdom': 'GB', uk: 'GB',
            pakistan: 'PK', germany: 'DE', france: 'FR', india: 'IN', canada: 'CA',
            'united arab emirates': 'AE', uae: 'AE', mexico: 'MX', 'dominican republic': 'DO',
            china: 'CN', australia: 'AU', brazil: 'BR', japan: 'JP', netherlands: 'NL',
            singapore: 'SG', russia: 'RU', spain: 'ES', italy: 'IT', turkey: 'TR',
        };
        return names[raw.toLowerCase()] || '';
    },
    countryFlagUrl(code) {
        const iso = this.countryCode(code).toLowerCase();
        if (!/^[a-z]{2}$/.test(iso)) return '';
        return `/media/flags/${iso}`;
    },
    score100(row) {
        const n = Number(row?.intel_risk_score ?? row?.threat_score ?? 0);
        if (!Number.isFinite(n) || n <= 0) return 0;
        return n <= 1 ? Math.round(n * 100) : Math.min(100, Math.round(n));
    },
    riskRingStyle(row) {
        const score = this.score100(row);
        const color = score >= 70 ? '#F43F5E' : (score >= 40 ? '#F59E0B' : '#22C55E');
        return `--p:${score};--c:${color}`;
    },
    riskLevelLabel(row) {
        const raw = String(row?.intel_risk_level || '').toLowerCase();
        if (raw.includes('high')) return 'High';
        if (raw.includes('medium') || raw.includes('med')) return 'Medium';
        if (raw.includes('low')) return 'Low';
        const score = this.score100(row);
        if (score >= 70) return 'High';
        if (score >= 40) return 'Medium';
        return 'Low';
    },
    riskLevelClass(row) {
        const label = this.riskLevelLabel(row).toLowerCase();
        return `is-${label}`;
    },
    actionTone(row) {
        const raw = `${row?.action_taken || ''} ${row?.block_status || ''} ${row?.status || ''} ${row?.traffic_status || ''}`.toLowerCase();
        if (/(block)/.test(raw) || row?.ip_is_blocked) return 'block';
        if (/(monitor|flag|invalid)/.test(raw) || row?.is_invalid_traffic) return 'monitor';
        return 'allow';
    },
    actionBadgeClass(row) {
        return `is-${this.actionTone(row)}`;
    },
    actionBadgeLabel(row) {
        const tone = this.actionTone(row);
        return tone === 'block' ? 'Blocked' : (tone === 'monitor' ? 'Monitored' : 'Allowed');
    },
    statusDotClass(row) {
        return `is-${this.actionTone(row)}`;
    },
    pagerPages(page, totalPages) {
        const last = Math.max(1, Number(totalPages) || 1);
        const current = Math.min(last, Math.max(1, Number(page) || 1));
        const items = [];
        for (let i = 1; i <= last; i++) {
            if (i === 1 || i === last || Math.abs(i - current) <= 1) items.push(i);
            else if (items[items.length - 1] !== '…') items.push('…');
        }
        return items;
    },
};
</script>
