<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Clickronix Analytics Report</title>
</head>
<body style="margin:0;padding:24px;background:#0f0f10;font-family:Arial,Helvetica,sans-serif;color:#f5f5f5;">
@php
    $k = $payload['kpis'] ?? [];
    $q = $payload['quality'] ?? [];
    $conv = $payload['conversion_summary'] ?? [];
    $funnel = $payload['funnel'] ?? [];
    $paid = $paid ?? [];
@endphp
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#141414;border:1px solid rgba(255,102,0,.35);border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:20px 24px;background:linear-gradient(180deg,#ff6600 0%,#cc5200 100%);color:#fff;">
                <h1 style="margin:0;font-size:22px;">Clickronix Monthly Report</h1>
                <p style="margin:8px 0 0;font-size:13px;opacity:.9;">{{ $from->format('F j') }} – {{ $to->format('F j, Y') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px;font-size:14px;color:#ddd;">Hi {{ $user->name ?? 'there' }}, here is your previous-month Clickronix summary.</p>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Clicks &amp; budget saved</h2>
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
                    <tr>
                        @foreach ([
                            ['Total clicks', number_format($paid['total_clicks'] ?? $k['total_visitors'] ?? 0)],
                            ['Invalid clicks', number_format($paid['invalid_clicks'] ?? 0)],
                            ['Budget saved', '$'.number_format((float) ($paid['cost_saved'] ?? 0), 2)],
                            ['Conversion', number_format($k['conversion_rate'] ?? 0, 2).'%'],
                        ] as [$label, $value])
                            <td width="25%" style="padding:10px;background:#1a1a1a;border:1px solid rgba(255,255,255,.08);border-radius:8px;text-align:center;">
                                <div style="font-size:11px;color:#aaa;">{{ $label }}</div>
                                <div style="font-size:16px;font-weight:700;color:#ff6600;margin-top:4px;">{{ $value }}</div>
                            </td>
                        @endforeach
                    </tr>
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Fraud &amp; quality signals</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    <tr style="border-bottom:1px solid rgba(255,255,255,.06);"><td>Repeat IPs</td><td align="right">{{ number_format($paid['repeat_ips'] ?? 0) }}</td></tr>
                    <tr style="border-bottom:1px solid rgba(255,255,255,.06);"><td>Repeat devices</td><td align="right">{{ number_format($paid['repeat_devices'] ?? 0) }}</td></tr>
                    <tr style="border-bottom:1px solid rgba(255,255,255,.06);"><td>High-risk IPs</td><td align="right">{{ number_format($paid['high_risk_ips'] ?? 0) }}</td></tr>
                    <tr style="border-bottom:1px solid rgba(255,255,255,.06);"><td>Crawler / Automation / Malicious</td><td align="right">{{ (int) ($q['crawler_score'] ?? 0) }}% / {{ (int) ($q['automation_score'] ?? 0) }}% / {{ (int) ($q['malicious_score'] ?? 0) }}%</td></tr>
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Top sources</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    @foreach (($payload['traffic_sources'] ?? []) as $row)
                        <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                            <td>{{ $row['label'] ?? '—' }}</td>
                            <td align="right">{{ number_format($row['value'] ?? 0) }}</td>
                            <td align="right" style="color:#aaa;">{{ $row['pct'] ?? 0 }}%</td>
                        </tr>
                    @endforeach
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Top pages</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    @foreach (array_slice($payload['top_pages'] ?? [], 0, 5) as $row)
                        <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                            <td>{{ $row['path'] ?? '—' }}</td>
                            <td align="right">{{ number_format($row['views'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Top keywords</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    @forelse (array_slice($payload['keywords'] ?? [], 0, 5) as $row)
                        <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                            <td>{{ $row['keyword'] ?? $row['label'] ?? '—' }}</td>
                            <td align="right">{{ number_format($row['value'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td style="color:#888;">No keyword data</td><td></td></tr>
                    @endforelse
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Conversion funnel</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    @forelse ($funnel as $row)
                        <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                            <td>{{ $row['label'] ?? $row['key'] ?? 'Step' }}</td>
                            <td align="right">{{ number_format($row['value'] ?? $row['count'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td style="color:#888;">Funnel unavailable</td><td></td></tr>
                    @endforelse
                </table>

                <p style="margin:0;font-size:13px;color:#ccc;">
                    Revenue: <strong style="color:#fff;">{{ $conv['revenue'] ?? '$0.00' }}</strong> ·
                    Transactions: <strong style="color:#fff;">{{ $conv['transactions'] ?? '0' }}</strong> ·
                    AOV: <strong style="color:#fff;">{{ $conv['aov'] ?? '$0.00' }}</strong>
                </p>

                @if (!empty($k['deltas']))
                    <h2 style="font-size:15px;color:#ff8533;margin:18px 0 10px;">Month-over-month</h2>
                    <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:8px;">
                        @foreach ([
                            'total_visitors' => 'Visitors',
                            'organic_traffic' => 'Organic',
                            'direct_traffic' => 'Direct',
                            'conversion_rate' => 'Conversion rate',
                        ] as $key => $label)
                            <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                                <td>{{ $label }}</td>
                                <td align="right">{{ isset($k['deltas'][$key]) ? (($k['deltas'][$key] > 0 ? '+' : '').$k['deltas'][$key].'%') : '—' }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if (!empty($crossDomainEnabled))
                    <h2 style="font-size:15px;color:#ff8533;margin:18px 0 10px;">Cross-domain patterns</h2>
                    <p style="margin:0;font-size:12px;color:#aaa;">Enabled on your package. Journey paths are included when session/behavior tracking is active.</p>
                @endif

                @if (!empty($exportMode))
                    <p style="margin:18px 0 0;font-size:12px;color:#888;">Tip: use your browser Print → Save as PDF for a designed PDF copy.</p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
