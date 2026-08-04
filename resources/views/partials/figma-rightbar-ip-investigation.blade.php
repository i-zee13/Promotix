{{-- High-risk IP investigation panel (replaces Add Account). Updated via promotix-ip-investigation. --}}
<div
    class="pm-ip-invest"
    x-data="promotixIpInvestigation()"
    @promotix-ip-investigation.window="setVisit($event.detail)"
>
    <div class="pm-ip-invest__head">
        <h2 class="pm-ip-invest__title">IP Investigation</h2>
        <button type="button" class="pm-ip-invest__close" x-show="visit" @click="clear()" aria-label="Clear">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <template x-if="!visit">
        <p class="pm-ip-invest__empty">Select a high-risk IP to inspect details here.</p>
    </template>

    <template x-if="visit">
        <div class="pm-ip-invest__body promotix-slim-scroll">
            <div class="pm-ip-invest__ip-row">
                <p class="pm-ip-invest__ip" x-text="visit.ip || '—'"></p>
                <span class="pm-ip-invest__badge" :class="'is-' + riskTone" x-text="riskLabel"></span>
            </div>

            <div class="pm-ip-invest__score-row">
                <div>
                    <p class="pm-ip-invest__score" :class="'is-' + riskTone">
                        <span x-text="riskScore"></span><span class="pm-ip-invest__score-max">/100</span>
                    </p>
                    <p class="pm-ip-invest__muted">Risk Score</p>
                </div>
                <div class="pm-ip-invest__gauge" :style="`--pm-gauge:${gaugePct}%`" aria-hidden="true">
                    <span class="pm-ip-invest__gauge-fill"></span>
                </div>
            </div>

            <section class="pm-ip-invest__section">
                <h3 class="pm-ip-invest__section-title">Detection Reasons</h3>
                <ul class="pm-ip-invest__reasons">
                    <template x-for="reason in detectionReasons" :key="reason">
                        <li>
                            <span class="pm-ip-invest__check" aria-hidden="true">✓</span>
                            <span x-text="reason"></span>
                        </li>
                    </template>
                    <li x-show="detectionReasons.length === 0" class="pm-ip-invest__muted">No detection reasons listed.</li>
                </ul>
            </section>

            <section class="pm-ip-invest__section">
                <h3 class="pm-ip-invest__section-title">Network Information</h3>
                <div class="pm-ip-invest__grid">
                    <div><p class="pm-ip-invest__label">ISP</p><p class="pm-ip-invest__value" x-text="visit.intel_isp || visit.intel_asn_org || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">ASN</p><p class="pm-ip-invest__value" x-text="visit.intel_asn || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">Organization</p><p class="pm-ip-invest__value" x-text="visit.intel_asn_org || visit.intel_isp || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">Connection</p><p class="pm-ip-invest__value" x-text="visit.intel_connection_type || visit.risk_summary?.connection || '—'"></p></div>
                </div>
            </section>

            <section class="pm-ip-invest__section">
                <h3 class="pm-ip-invest__section-title">Device Fingerprint</h3>
                <div class="pm-ip-invest__grid">
                    <div><p class="pm-ip-invest__label">Device ID</p><p class="pm-ip-invest__value pm-ip-invest__mono" x-text="shortId(visit.device_fingerprint)"></p></div>
                    <div><p class="pm-ip-invest__label">Browser</p><p class="pm-ip-invest__value" x-text="browserLabel"></p></div>
                    <div><p class="pm-ip-invest__label">OS</p><p class="pm-ip-invest__value" x-text="visit.os || visit.device || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">Screen</p><p class="pm-ip-invest__value" x-text="visit.screen_resolution || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">Language</p><p class="pm-ip-invest__value" x-text="visit.language || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">Timezone</p><p class="pm-ip-invest__value" x-text="visit.visitor_timezone || '—'"></p></div>
                </div>
            </section>

            <section class="pm-ip-invest__section">
                <h3 class="pm-ip-invest__section-title">Protection Action</h3>
                <div class="pm-ip-invest__grid">
                    <div><p class="pm-ip-invest__label">Detection Result</p><p class="pm-ip-invest__value" :class="riskTone === 'high' ? 'is-danger' : ''" x-text="visit.risk_summary?.status || visit.status || riskLabel"></p></div>
                    <div><p class="pm-ip-invest__label">Action Taken</p><p class="pm-ip-invest__value" :class="visit.ip_is_blocked ? 'is-danger' : ''" x-text="actionTaken"></p></div>
                    <div><p class="pm-ip-invest__label">Threat Group</p><p class="pm-ip-invest__value" x-text="visit.threat_group || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">VPN</p><p class="pm-ip-invest__value" x-text="flagYes(visit.intel_vpn, visit.vpn_hits)"></p></div>
                    <div><p class="pm-ip-invest__label">Data Center</p><p class="pm-ip-invest__value" x-text="flagYes(visit.intel_datacenter, visit.data_center_hits)"></p></div>
                    <div><p class="pm-ip-invest__label">Invalid Clicks</p><p class="pm-ip-invest__value" x-text="visit.invalid_clicks ?? 0"></p></div>
                    <div><p class="pm-ip-invest__label">Country</p><p class="pm-ip-invest__value" x-text="visit.country || '—'"></p></div>
                    <div><p class="pm-ip-invest__label">Last Click</p><p class="pm-ip-invest__value" x-text="visit.last_click_label || '—'"></p></div>
                </div>
            </section>

            <section class="pm-ip-invest__section" x-show="(visit.clicks || []).length">
                <h3 class="pm-ip-invest__section-title">Timeline</h3>
                <ul class="pm-ip-invest__timeline">
                    <template x-for="(click, idx) in (visit.clicks || []).slice(0, 6)" :key="click.id || idx">
                        <li>
                            <span class="pm-ip-invest__dot"></span>
                            <div>
                                <p class="pm-ip-invest__value" x-text="click.threat_group || click.action || 'Click'"></p>
                                <p class="pm-ip-invest__muted" x-text="(click.path || click.campaign || 'Event') + (click.clicked_at ? ' · ' + formatTs(click.clicked_at) : '')"></p>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>

            <div class="pm-ip-invest__actions">
                <button type="button" class="pm-ip-invest__btn pm-ip-invest__btn--primary" @click="viewFull()">View Full Report</button>
                <a :href="exclusionHref" class="pm-ip-invest__btn pm-ip-invest__btn--ghost">Add to Exclusion</a>
            </div>
        </div>
    </template>
</div>

<script>
window.promotixIpInvestigation = function promotixIpInvestigation() {
    return {
        visit: null,
        get riskScore() {
            const s = this.visit?.intel_risk_score ?? this.visit?.risk_summary?.score;
            return (s === null || s === undefined || s === '') ? '—' : s;
        },
        get riskLabel() {
            const level = String(this.visit?.intel_risk_level || this.visit?.risk_summary?.level || '').toLowerCase();
            if (level) return level.charAt(0).toUpperCase() + level.slice(1) + (level.includes('risk') ? '' : ' Risk');
            const score = Number(this.visit?.intel_risk_score ?? this.visit?.risk_summary?.score ?? 0);
            if (score >= 70) return 'High Risk';
            if (score >= 40) return 'Medium Risk';
            return 'Low Risk';
        },
        get riskTone() {
            const label = this.riskLabel.toLowerCase();
            if (label.includes('high')) return 'high';
            if (label.includes('medium')) return 'medium';
            return 'low';
        },
        get gaugePct() {
            const s = Number(this.visit?.intel_risk_score ?? this.visit?.risk_summary?.score ?? 0);
            return Math.max(0, Math.min(100, isFinite(s) ? s : 0));
        },
        get detectionReasons() {
            const reasons = [];
            const v = this.visit || {};
            const push = (label, on) => { if (on && !reasons.includes(label)) reasons.push(label); };
            push('VPN detected', v.intel_vpn === 'Yes' || Number(v.vpn_hits) > 0);
            push('Datacenter IP', v.intel_datacenter === 'Yes' || Number(v.data_center_hits) > 0);
            push('Proxy detected', v.intel_proxy === 'Yes');
            push('Geo mismatch', /geo|mismatch|location/i.test(String(v.threat_group || '') + ' ' + String(v.threat_type || '')));
            push('Invalid device', /device|fingerprint/i.test(String(v.threat_group || '') + ' ' + String(v.threat_type || '') + ' ' + String(v.intel_device_action || '')));
            push('Bot behavior', /bot/i.test(String(v.threat_group || '') + ' ' + String(v.threat_type || '')));
            push('Repeated clicks', Number(v.invalid_clicks) > 1 || Number(v.visits) > 1);
            (v.risk_summary?.reasons || []).forEach((r) => push(String(r), true));
            return reasons.slice(0, 8);
        },
        get browserLabel() {
            const b = this.visit?.browser || this.visit?.clicks?.[0]?.browser_name;
            const ver = this.visit?.clicks?.[0]?.browser_version;
            if (!b) return '—';
            return ver ? `${b} ${ver}` : b;
        },
        get actionTaken() {
            if (this.visit?.ip_is_blocked) return 'Blocked';
            if (this.visit?.rule_explanation?.action) return this.visit.rule_explanation.action;
            if (this.visit?.threat_type) return this.visit.threat_type;
            return this.visit?.status || '—';
        },
        get exclusionHref() {
            return @json(route('domains.index'));
        },
        setVisit(detail) {
            this.visit = detail || null;
        },
        clear() {
            this.visit = null;
        },
        shortId(id) {
            if (!id) return '—';
            const s = String(id);
            return s.length > 16 ? s.slice(0, 16) + '…' : s;
        },
        flagYes(intel, hits) {
            if (intel === 'Yes' || Number(hits) > 0) return 'Yes';
            if (intel === 'No') return 'No';
            return '—';
        },
        formatTs(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return String(iso);
                return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
            } catch (_) {
                return String(iso);
            }
        },
        viewFull() {
            if (!this.visit) return;
            window.dispatchEvent(new CustomEvent('promotix-open-ip-report', { detail: this.visit }));
        },
    };
};
</script>
