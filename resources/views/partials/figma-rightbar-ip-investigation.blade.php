{{-- High-risk IP investigation — auto-filled; polished card UI (inline CSS for Vite staleness). --}}
<style>
    .pm-ip-invest { display:flex; flex-direction:column; min-width:0; color:#fff; }
    .pm-ip-invest__head {
        display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px;
    }
    .pm-ip-invest__title { margin:0; font-size:14px; font-weight:700; color:#a9a9a9; letter-spacing:.01em; }
    .pm-ip-invest__live {
        display:inline-flex; align-items:center; gap:5px; font-size:8px; font-weight:700;
        letter-spacing:.06em; text-transform:uppercase; color:#f87171;
    }
    .pm-ip-invest__live-dot {
        width:6px; height:6px; border-radius:999px; background:#f43f5e;
        box-shadow:0 0 0 3px rgba(244,63,94,.22); animation:pm-ip-pulse 1.6s ease infinite;
    }
    @keyframes pm-ip-pulse {
        0%,100% { opacity:1; transform:scale(1); }
        50% { opacity:.55; transform:scale(.85); }
    }
    .pm-ip-invest__empty {
        margin:0; padding:12px 8px; border-radius:8px; border:1px dashed rgba(255,255,255,.12);
        background:#141414; font-size:9px; line-height:1.35; color:rgba(255,255,255,.38); text-align:center;
    }
    .pm-ip-invest__body {
        max-height:min(64vh, 680px); overflow-x:hidden; overflow-y:auto; padding-right:2px;
        display:flex; flex-direction:column; gap:10px;
    }
    .pm-ip-invest__hero {
        border-radius:10px; border:1px solid rgba(100,0,178,.45);
        background:linear-gradient(165deg, #1a1024 0%, #111111 55%, #0d0d0d 100%);
        padding:12px 11px 11px;
    }
    .pm-ip-invest__ip-row {
        display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:10px;
    }
    .pm-ip-invest__ip {
        margin:0; font-size:13px; font-weight:800; letter-spacing:.01em;
        font-variant-numeric:tabular-nums; word-break:break-all; line-height:1.2; color:#fff;
    }
    .pm-ip-invest__badge {
        flex-shrink:0; border-radius:999px; padding:3px 6px; font-size:7px; font-weight:800;
        letter-spacing:.04em; text-transform:uppercase; white-space:nowrap;
        background:rgba(34,197,94,.18); color:#86efac; border:1px solid rgba(134,239,172,.25);
    }
    .pm-ip-invest__badge.is-high {
        background:rgba(244,63,94,.22); color:#fda4af; border-color:rgba(253,164,175,.35);
    }
    .pm-ip-invest__badge.is-medium {
        background:rgba(245,158,11,.2); color:#fcd34d; border-color:rgba(252,211,77,.3);
    }
    .pm-ip-invest__score-row {
        display:flex; align-items:center; justify-content:flex-start; gap:10px;
    }
    .pm-ip-invest__score {
        margin:0; font-size:22px; font-weight:800; line-height:1; color:#22c55e;
        font-variant-numeric:tabular-nums;
    }
    .pm-ip-invest__score.is-high { color:#f43f5e; }
    .pm-ip-invest__score.is-medium { color:#f59e0b; }
    .pm-ip-invest__score-max { font-size:11px; font-weight:600; opacity:.65; margin-left:1px; }
    .pm-ip-invest__score-label { margin:4px 0 0; font-size:8px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:rgba(255,255,255,.4); }

    .pm-ip-invest__card {
        border-radius:9px; border:1px solid rgba(255,255,255,.1);
        background:#141414; padding:8px;
    }
    .pm-ip-invest__section-title {
        position:relative; margin:0 0 8px; padding-left:8px;
        font-size:10px; font-weight:700; color:rgba(255,255,255,.9);
    }
    .pm-ip-invest__section-title::before {
        content:''; position:absolute; left:0; top:1px; bottom:1px; width:2px; border-radius:2px; background:#6400B2;
    }

    .pm-ip-invest__reasons { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:5px; }
    .pm-ip-invest__reasons li {
        display:flex; align-items:center; gap:7px; font-size:10px; font-weight:500; color:rgba(255,255,255,.88);
    }
    .pm-ip-invest__reasons li.is-off { color:rgba(255,255,255,.28); }
    .pm-ip-invest__check {
        display:grid; place-items:center; width:13px; height:13px; flex-shrink:0;
        border-radius:999px; background:rgba(244,63,94,.28); color:#fda4af; font-size:8px; font-weight:800;
    }
    .pm-ip-invest__reasons li.is-off .pm-ip-invest__check {
        background:rgba(255,255,255,.06); color:rgba(255,255,255,.25);
    }

    .pm-ip-invest__grid {
        display:grid; grid-template-columns:1fr 1fr; gap:6px;
    }
    .pm-ip-invest__field {
        min-width:0; padding:5px 6px; border-radius:6px; background:rgba(255,255,255,.03);
        border:1px solid rgba(255,255,255,.06);
    }
    .pm-ip-invest__label {
        margin:0; font-size:7px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
        color:rgba(255,255,255,.38);
    }
    .pm-ip-invest__value {
        margin:3px 0 0; font-size:10px; font-weight:600; color:rgba(255,255,255,.92);
        word-break:break-word; line-height:1.25;
    }
    .pm-ip-invest__value.is-danger { color:#f87171; }
    .pm-ip-invest__value.is-ok { color:#86efac; }
    .pm-ip-invest__mono { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:9px; }

    .pm-ip-invest__timeline { list-style:none; margin:0; padding:0 0 0 2px; display:flex; flex-direction:column; gap:8px; border-left:1px solid rgba(100,0,178,.4); }
    .pm-ip-invest__timeline li { position:relative; padding-left:12px; }
    .pm-ip-invest__dot {
        position:absolute; left:-4px; top:3px; width:7px; height:7px; border-radius:999px;
        background:#6400B2; box-shadow:0 0 0 3px rgba(100,0,178,.22);
    }
    .pm-ip-invest__tl-title { margin:0; font-size:10px; font-weight:600; color:#fff; }
    .pm-ip-invest__tl-meta { margin:2px 0 0; font-size:8px; color:rgba(255,255,255,.4); line-height:1.3; }

    .pm-ip-invest__actions { display:grid; grid-template-columns:1fr; gap:6px; }
    .pm-ip-invest__btn {
        display:inline-flex; align-items:center; justify-content:center; min-height:30px;
        padding:6px 10px; border-radius:7px; font-size:10px; font-weight:700;
        text-align:center; text-decoration:none; cursor:pointer; border:1px solid transparent;
    }
    .pm-ip-invest__btn--primary { background:#6400B2; color:#fff; }
    .pm-ip-invest__btn--primary:hover { background:#7B13C8; }
    .pm-ip-invest__btn--ghost {
        background:#0d0d0d; border-color:rgba(255,255,255,.16); color:rgba(255,255,255,.82);
    }
    .pm-ip-invest__btn--ghost:hover { border-color:rgba(100,0,178,.55); color:#fff; }

    html.light-mode .pm-ip-invest__title { color:#5c5470; }
    html.light-mode .pm-ip-invest__empty { background:#f4f2f7; border-color:#d4c4e8; color:#6b6280; }
    html.light-mode .pm-ip-invest__hero {
        background:linear-gradient(165deg, #f3eef9 0%, #fff 60%);
        border-color:#d4c4e8;
    }
    html.light-mode .pm-ip-invest__ip,
    html.light-mode .pm-ip-invest__section-title,
    html.light-mode .pm-ip-invest__value,
    html.light-mode .pm-ip-invest__reasons li,
    html.light-mode .pm-ip-invest__tl-title { color:#1a1a1a; }
    html.light-mode .pm-ip-invest__card { background:#fff; border-color:#e5ddf0; }
    html.light-mode .pm-ip-invest__field { background:#f8f6fb; border-color:#ebe4f4; }
    html.light-mode .pm-ip-invest__label,
    html.light-mode .pm-ip-invest__score-label,
    html.light-mode .pm-ip-invest__tl-meta { color:#6b6280; }
    html.light-mode .pm-ip-invest__btn--ghost { background:#fff; border-color:#d4c4e8; color:#3d3848; }
    html.light-mode .pm-ip-invest__reasons li.is-off { color:#b0a8bc; }
</style>

<div
    class="pm-ip-invest"
    x-data="promotixIpInvestigation()"
    x-init="boot()"
    @promotix-ip-investigation.window="setVisit($event.detail)"
>
    <div class="pm-ip-invest__head">
        <h2 class="pm-ip-invest__title">IP Investigation</h2>
        <span class="pm-ip-invest__live" x-show="visit" x-cloak>
            <span class="pm-ip-invest__live-dot"></span>
            Live
        </span>
    </div>

    <p class="pm-ip-invest__empty" x-show="!visit" x-cloak>Waiting for high-risk traffic…</p>

    <div class="pm-ip-invest__body promotix-slim-scroll" x-show="visit" x-cloak>
        <div class="pm-ip-invest__hero">
            <div class="pm-ip-invest__ip-row">
                <p class="pm-ip-invest__ip" x-text="visit.ip || '—'"></p>
                <span class="pm-ip-invest__badge" :class="'is-' + riskTone" x-text="riskLabel"></span>
            </div>
            <div class="pm-ip-invest__score-row">
                <div>
                    <p class="pm-ip-invest__score" :class="'is-' + riskTone">
                        <span x-text="riskScore"></span><span class="pm-ip-invest__score-max">/100</span>
                    </p>
                    <p class="pm-ip-invest__score-label">Risk Score</p>
                </div>
            </div>
        </div>

        <section class="pm-ip-invest__card">
            <h3 class="pm-ip-invest__section-title">Detection Reasons</h3>
            <ul class="pm-ip-invest__reasons">
                <template x-for="row in detectionRows" :key="row.label">
                    <li :class="row.on ? '' : 'is-off'">
                        <span class="pm-ip-invest__check" x-text="row.on ? '✓' : '·'"></span>
                        <span x-text="row.label"></span>
                    </li>
                </template>
            </ul>
        </section>

        <section class="pm-ip-invest__card">
            <h3 class="pm-ip-invest__section-title">Network Information</h3>
            <div class="pm-ip-invest__grid">
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">ISP</p>
                    <p class="pm-ip-invest__value" x-text="visit.intel_isp || visit.intel_asn_org || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">ASN</p>
                    <p class="pm-ip-invest__value" x-text="visit.intel_asn || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Organization</p>
                    <p class="pm-ip-invest__value" x-text="visit.intel_asn_org || visit.intel_isp || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Connection</p>
                    <p class="pm-ip-invest__value" x-text="visit.intel_connection_type || visit.risk_summary?.connection || '—'"></p>
                </div>
            </div>
        </section>

        <section class="pm-ip-invest__card">
            <h3 class="pm-ip-invest__section-title">Device Fingerprint</h3>
            <div class="pm-ip-invest__grid">
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Device ID</p>
                    <p class="pm-ip-invest__value pm-ip-invest__mono" x-text="shortId(visit.device_fingerprint)"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Browser</p>
                    <p class="pm-ip-invest__value" x-text="browserLabel"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">OS</p>
                    <p class="pm-ip-invest__value" x-text="visit.os || visit.device || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Screen</p>
                    <p class="pm-ip-invest__value" x-text="visit.screen_resolution || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Language</p>
                    <p class="pm-ip-invest__value" x-text="visit.language || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Timezone</p>
                    <p class="pm-ip-invest__value" x-text="visit.visitor_timezone || '—'"></p>
                </div>
            </div>
        </section>

        <section class="pm-ip-invest__card">
            <h3 class="pm-ip-invest__section-title">Protection Action</h3>
            <div class="pm-ip-invest__grid">
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Detection Result</p>
                    <p class="pm-ip-invest__value" :class="riskTone === 'high' ? 'is-danger' : ''" x-text="visit.risk_summary?.status || visit.status || riskLabel"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Action Taken</p>
                    <p class="pm-ip-invest__value" :class="visit.ip_is_blocked ? 'is-danger' : ''" x-text="actionTaken"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">VPN</p>
                    <p class="pm-ip-invest__value" :class="flagOn(visit.intel_vpn, visit.vpn_hits) ? 'is-danger' : 'is-ok'" x-text="flagYes(visit.intel_vpn, visit.vpn_hits)"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Data Center</p>
                    <p class="pm-ip-invest__value" :class="flagOn(visit.intel_datacenter, visit.data_center_hits) ? 'is-danger' : 'is-ok'" x-text="flagYes(visit.intel_datacenter, visit.data_center_hits)"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Geo Mismatch</p>
                    <p class="pm-ip-invest__value" :class="hasGeo ? 'is-danger' : 'is-ok'" x-text="hasGeo ? 'Yes' : 'No'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Invalid Device</p>
                    <p class="pm-ip-invest__value" :class="hasInvalidDevice ? 'is-danger' : 'is-ok'" x-text="hasInvalidDevice ? 'Yes' : 'No'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Threat Group</p>
                    <p class="pm-ip-invest__value" x-text="visit.threat_group || '—'"></p>
                </div>
                <div class="pm-ip-invest__field">
                    <p class="pm-ip-invest__label">Invalid Clicks</p>
                    <p class="pm-ip-invest__value" x-text="visit.invalid_clicks ?? 0"></p>
                </div>
            </div>
        </section>

        <section class="pm-ip-invest__card" x-show="(visit.clicks || []).length">
            <h3 class="pm-ip-invest__section-title">Timeline</h3>
            <ul class="pm-ip-invest__timeline">
                <template x-for="(click, idx) in (visit.clicks || []).slice(0, 5)" :key="click.id || idx">
                    <li>
                        <span class="pm-ip-invest__dot"></span>
                        <p class="pm-ip-invest__tl-title" x-text="click.threat_group || click.action || 'Click event'"></p>
                        <p class="pm-ip-invest__tl-meta" x-text="(click.path || click.campaign || 'Event') + (click.clicked_at ? ' · ' + formatTs(click.clicked_at) : '')"></p>
                    </li>
                </template>
            </ul>
        </section>

        <div class="pm-ip-invest__actions">
            <button type="button" class="pm-ip-invest__btn pm-ip-invest__btn--primary" @click="viewFull()">View Full Report</button>
            <a :href="exclusionHref" class="pm-ip-invest__btn pm-ip-invest__btn--ghost">Add to Exclusion</a>
        </div>
    </div>
</div>

<script>
window.promotixIpInvestigation = function promotixIpInvestigation() {
    const threatBlob = (v) => String(v?.threat_group || '') + ' ' + String(v?.threat_type || '') + ' ' + String(v?.intel_device_action || '') + ' ' + ((v?.risk_summary?.reasons || []).join(' '));

    return {
        visit: null,
        boot() {
            // Re-apply last published visit if Advanced View already fetched.
            try {
                const cached = window.__promotixIpInvestigationVisit;
                if (cached) this.setVisit(cached);
            } catch (_) {}
        },
        normalizeScore(raw) {
            let n = Number(raw);
            if (!isFinite(n) || raw === null || raw === undefined || raw === '') return null;
            if (n > 0 && n < 1) n = n * 100;
            if (n > 100) n = 100;
            if (n < 0) n = 0;
            return Math.round(n);
        },
        derivedScore() {
            const v = this.visit || {};
            let score = this.normalizeScore(v.intel_risk_score ?? v.risk_summary?.score);
            if (score === null) score = this.normalizeScore(v.intel_confidence);
            if (score === null) score = 0;
            // Elevate when strong threat signals exist but intel score is near-zero.
            const signals = [
                v.ip_is_blocked,
                v.intel_vpn === 'Yes' || Number(v.vpn_hits) > 0,
                v.intel_datacenter === 'Yes' || Number(v.data_center_hits) > 0,
                /bot|blocked|malicious|invalid/i.test(threatBlob(v)),
                Number(v.invalid_clicks) > 0,
            ].filter(Boolean).length;
            if (score < 40 && signals >= 2) score = Math.max(score, 55 + signals * 8);
            if (score < 70 && v.ip_is_blocked) score = Math.max(score, 78);
            return Math.min(100, score);
        },
        get riskScore() {
            return this.visit ? this.derivedScore() : '—';
        },
        get riskLabel() {
            const level = String(this.visit?.intel_risk_level || this.visit?.risk_summary?.level || '').toLowerCase();
            if (level.includes('high')) return 'High Risk';
            if (level.includes('medium')) return 'Medium Risk';
            if (level.includes('low') && this.derivedScore() < 40 && !this.visit?.ip_is_blocked) return 'Low Risk';
            const score = this.derivedScore();
            if (this.visit?.ip_is_blocked || score >= 70) return 'High Risk';
            if (score >= 40) return 'Medium Risk';
            return 'Low Risk';
        },
        get riskTone() {
            const label = this.riskLabel.toLowerCase();
            if (label.includes('high')) return 'high';
            if (label.includes('medium')) return 'medium';
            return 'low';
        },
        get hasGeo() {
            return /geo|mismatch|location|timezone/i.test(threatBlob(this.visit));
        },
        get hasInvalidDevice() {
            return /device|fingerprint|invalid_device/i.test(threatBlob(this.visit));
        },
        get detectionRows() {
            const v = this.visit || {};
            return [
                { label: 'VPN detected', on: v.intel_vpn === 'Yes' || Number(v.vpn_hits) > 0 },
                { label: 'Datacenter IP', on: v.intel_datacenter === 'Yes' || Number(v.data_center_hits) > 0 },
                { label: 'Geo mismatch', on: this.hasGeo },
                { label: 'Invalid device', on: this.hasInvalidDevice },
                { label: 'Bot behavior', on: /bot|automation/i.test(threatBlob(v)) },
                { label: 'Repeated clicks', on: Number(v.invalid_clicks) > 1 || Number(v.visits) > 1 },
                { label: 'Blocked', on: !!v.ip_is_blocked || /blocked/i.test(String(v.status || '') + ' ' + String(v.risk_summary?.status || '')) },
            ];
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
            try { window.__promotixIpInvestigationVisit = this.visit; } catch (_) {}
        },
        shortId(id) {
            if (!id) return '—';
            const s = String(id);
            return s.length > 16 ? s.slice(0, 16) + '…' : s;
        },
        flagOn(intel, hits) {
            return intel === 'Yes' || Number(hits) > 0;
        },
        flagYes(intel, hits) {
            if (this.flagOn(intel, hits)) return 'Yes';
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
