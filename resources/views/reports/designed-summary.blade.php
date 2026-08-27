<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Clickronix Report — {{ $from->format('M j') }} – {{ $to->format('M j, Y') }}</title>
    <style>
        @page { margin: 18mm; }
        body { margin: 0; padding: 24px; background: #0f0f10; font-family: Arial, Helvetica, sans-serif; color: #f5f5f5; }
        .wrap { max-width: 760px; margin: 0 auto; background: #141414; border: 1px solid rgba(255,102,0,.35); border-radius: 12px; overflow: hidden; }
        .hero { padding: 20px 24px; background: linear-gradient(180deg,#ff6600 0%,#cc5200 100%); color: #fff; }
        .hero h1 { margin: 0; font-size: 22px; }
        .hero p { margin: 8px 0 0; font-size: 13px; opacity: .9; }
        .body { padding: 24px; }
        h2 { font-size: 15px; color: #ff8533; margin: 18px 0 10px; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 8px; }
        .kpi { padding: 12px; background: #1a1a1a; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; text-align: center; }
        .kpi .l { font-size: 11px; color: #aaa; }
        .kpi .v { font-size: 18px; font-weight: 700; color: #ff6600; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px; }
        table.data td, table.data th { padding: 8px; border-bottom: 1px solid rgba(255,255,255,.06); text-align: left; }
        table.data td.num, table.data th.num { text-align: right; }
        .muted { color: #aaa; font-size: 12px; }
        .print-tip { margin-top: 18px; font-size: 12px; color: #888; }
        @media print {
            body { background: #fff; color: #111; }
            .wrap { border: none; }
            .hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-tip { display: none; }
        }
    </style>
</head>
<body>
@php
    $k = $payload['kpis'] ?? [];
    $q = $payload['quality'] ?? [];
    $conv = $payload['conversion_summary'] ?? [];
    $funnel = $payload['funnel'] ?? [];
    $prevK = $previous['kpis'] ?? [];
    $mom = function ($cur, $prev) {
        $cur = (float) $cur; $prev = (float) $prev;
        if ($prev == 0.0) return $cur > 0 ? '+100%' : '0%';
        $d = round((($cur - $prev) / $prev) * 100, 1);
        return ($d > 0 ? '+' : '').$d.'%';
    };
@endphp
<div class="wrap">
    <div class="hero">
        <h1>Clickronix — {{ $reportType }}</h1>
        <p>
            {{ $from->format('F j, Y') }} – {{ $to->format('F j, Y') }}
            · Timezone: {{ $timezoneLabel }}
            · Designed PDF summary
        </p>
    </div>
    <div class="body">
        <p class="muted" style="margin-top:0">Hi {{ $user->name ?? 'there' }}, here is your Clickronix report snapshot.</p>

        <h2>Clicks &amp; budget</h2>
        <table class="grid"><tr>
            <td class="kpi"><div class="l">Total clicks</div><div class="v">{{ number_format($paid['total_clicks'] ?? 0) }}</div></td>
            <td class="kpi"><div class="l">Invalid clicks</div><div class="v">{{ number_format($paid['invalid_clicks'] ?? 0) }}</div></td>
            <td class="kpi"><div class="l">Valid clicks</div><div class="v">{{ number_format($paid['valid_clicks'] ?? 0) }}</div></td>
            <td class="kpi"><div class="l">Est. budget saved</div><div class="v">${{ number_format((float) ($paid['cost_saved'] ?? 0), 2) }}</div></td>
        </tr></table>

        <h2>Fraud &amp; quality</h2>
        <table class="grid"><tr>
            <td class="kpi"><div class="l">Repeat IPs</div><div class="v">{{ number_format($paid['repeat_ips'] ?? 0) }}</div></td>
            <td class="kpi"><div class="l">Repeat devices</div><div class="v">{{ number_format($paid['repeat_devices'] ?? 0) }}</div></td>
            <td class="kpi"><div class="l">High-risk IPs</div><div class="v">{{ number_format($paid['high_risk_ips'] ?? 0) }}</div></td>
            <td class="kpi"><div class="l">Crawler / Auto / Malicious</div><div class="v" style="font-size:14px">{{ (int) ($q['crawler_score'] ?? 0) }}% / {{ (int) ($q['automation_score'] ?? 0) }}% / {{ (int) ($q['malicious_score'] ?? 0) }}%</div></td>
        </tr></table>

        <h2>Month-over-month</h2>
        <table class="data">
            <tr><th>Metric</th><th class="num">This period</th><th class="num">Prior period</th><th class="num">MoM</th></tr>
            <tr>
                <td>Visitors</td>
                <td class="num">{{ number_format($k['total_visitors'] ?? 0) }}</td>
                <td class="num">{{ number_format($prevK['total_visitors'] ?? 0) }}</td>
                <td class="num">{{ $mom($k['total_visitors'] ?? 0, $prevK['total_visitors'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Conversion rate</td>
                <td class="num">{{ number_format((float) ($k['conversion_rate'] ?? 0), 2) }}%</td>
                <td class="num">{{ number_format((float) ($prevK['conversion_rate'] ?? 0), 2) }}%</td>
                <td class="num">{{ $mom($k['conversion_rate'] ?? 0, $prevK['conversion_rate'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Revenue</td>
                <td class="num">{{ $conv['revenue'] ?? '$0.00' }}</td>
                <td class="num">{{ $previous['conversion_summary']['revenue'] ?? '$0.00' }}</td>
                <td class="num">{{ $mom($conv['revenue_raw'] ?? 0, $previous['conversion_summary']['revenue_raw'] ?? 0) }}</td>
            </tr>
        </table>

        <h2>Top sources</h2>
        <table class="data">
            @foreach (($payload['traffic_sources'] ?? []) as $row)
                <tr>
                    <td>{{ $row['label'] ?? '—' }}</td>
                    <td class="num">{{ number_format($row['value'] ?? 0) }}</td>
                    <td class="num muted">{{ $row['pct'] ?? 0 }}%</td>
                </tr>
            @endforeach
        </table>

        <h2>Top pages</h2>
        <table class="data">
            @foreach (array_slice($payload['top_pages'] ?? [], 0, 8) as $row)
                <tr>
                    <td>{{ $row['path'] ?? '—' }}</td>
                    <td class="num">{{ number_format($row['views'] ?? 0) }}</td>
                </tr>
            @endforeach
        </table>

        <h2>Top keywords</h2>
        <table class="data">
            @forelse (array_slice($payload['keywords'] ?? [], 0, 8) as $row)
                <tr>
                    <td>{{ $row['keyword'] ?? $row['label'] ?? '—' }}</td>
                    <td class="num">{{ number_format($row['value'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">No keyword data in this range.</td></tr>
            @endforelse
        </table>

        <h2>Conversion funnel</h2>
        <table class="data">
            @forelse ($funnel as $row)
                <tr>
                    <td>{{ $row['label'] ?? $row['key'] ?? 'Step' }}</td>
                    <td class="num">{{ number_format($row['value'] ?? $row['count'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">Funnel unavailable.</td></tr>
            @endforelse
        </table>

        <h2>Sales &amp; revenue</h2>
        <p style="margin:0;font-size:13px;color:#ccc;">
            Revenue: <strong style="color:#fff;">{{ $conv['revenue'] ?? '$0.00' }}</strong> ·
            Transactions: <strong style="color:#fff;">{{ $conv['transactions'] ?? '0' }}</strong> ·
            AOV: <strong style="color:#fff;">{{ $conv['aov'] ?? '$0.00' }}</strong>
        </p>

        @if (!empty($crossDomainEnabled))
            <h2>Cross-domain patterns</h2>
            <p class="muted" style="margin:0">Cross-domain / multi-page journey tracking is enabled on your package. Top journey paths:</p>
            <table class="data" style="margin-top:8px">
                @forelse (array_slice($payload['journey_paths'] ?? [], 0, 5) as $row)
                    <tr>
                        <td>{{ is_array($row) ? ($row['path'] ?? $row['label'] ?? json_encode($row)) : (string) $row }}</td>
                        <td class="num">{{ number_format(is_array($row) ? ($row['value'] ?? $row['count'] ?? 0) : 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">No cross-domain journeys in this range.</td></tr>
                @endforelse
            </table>
        @endif

        <p class="print-tip">Open this file in a browser and use Print → Save as PDF for a designed PDF copy.</p>
    </div>
</div>
</body>
</html>
