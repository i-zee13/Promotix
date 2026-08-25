<script>
window.promotixAdvTableHelpers = {
    isRichCol(key) {
        return [
            'country', 'intel_risk_score', 'intel_risk_level', 'action_taken', 'status', 'session_recording',
            'cta_clicks', 'tel_clicks', 'form_starts', 'form_submits', 'form_fills', 'add_to_cart', 'checkout', 'purchase',
            'session_id', 'source_platform', 'page_flow', 'event_actions', 'entry_time', 'exit_time',
        ].includes(key);
    },
    sourcePlatformKind(label) {
        const s = String(label || '').toLowerCase();
        if (s.includes('google')) return 'google';
        if (s.includes('facebook') || s.includes('meta')) return 'facebook';
        if (s.includes('instagram')) return 'instagram';
        if (s.includes('yahoo')) return 'yahoo';
        if (s.includes('bing') || s.includes('microsoft')) return 'bing';
        if (s.includes('linkedin')) return 'linkedin';
        if (s.includes('twitter') || s.includes('x.com')) return 'twitter';
        if (s.includes('direct')) return 'direct';
        if (s.includes('paid')) return 'paid';
        if (s.includes('backlink') || s.includes('referral')) return 'link';
        if (s.includes('social')) return 'social';
        if (s.includes('organic')) return 'organic';
        return 'link';
    },
    pageFlowParts(row) {
        if (Array.isArray(row?.pages) && row.pages.length) {
            return row.pages.slice(0, 8).map((p) => String(p || '/'));
        }
        const flow = String(row?.page_flow || '').trim();
        if (!flow || flow === '—') {
            const landing = String(row?.landing_page || '').trim();
            return landing && landing !== '—' ? [landing] : [];
        }
        return flow.split(/\s*(?:->|→)\s*/).map((s) => s.trim()).filter(Boolean);
    },
    eventActionRows(row) {
        if (Array.isArray(row?.event_actions) && row.event_actions.length) {
            return row.event_actions.map((ev) => ({
                key: String(ev.key || ev.label || 'event'),
                count: Number(ev.count || 0),
            })).filter((ev) => ev.count > 0);
        }
        const fallback = [];
        const push = (key, count) => {
            const n = Number(count || 0);
            if (n > 0) fallback.push({ key, count: n });
        };
        push('page_view', row?.page_views);
        push('cta_click', row?.cta_clicks);
        push('add_to_cart', row?.add_to_cart);
        push('checkout', row?.checkout);
        if (String(row?.purchase || '').toLowerCase() === 'yes') push('purchase', 1);
        push('scroll', row?.scroll_events);
        push('tel_click', row?.tel_clicks);
        push('form_submit', row?.form_fills ?? row?.form_submits);
        return fallback;
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
