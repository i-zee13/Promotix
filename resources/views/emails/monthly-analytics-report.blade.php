<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Clickronix Analytics Report</title>
</head>
<body style="margin:0;padding:24px;background:#0f0f10;font-family:Arial,Helvetica,sans-serif;color:#f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#141414;border:1px solid rgba(255,102,0,.35);border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:20px 24px;background:linear-gradient(180deg,#ff6600 0%,#cc5200 100%);color:#fff;">
                <h1 style="margin:0;font-size:22px;">Clickronix Analytics</h1>
                <p style="margin:8px 0 0;font-size:13px;opacity:.9;">Monthly report for {{ $from->format('F j') }} – {{ $to->format('F j, Y') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px;font-size:14px;color:#ddd;">Hi {{ $user->name ?? 'there' }}, here is your Analytics snapshot.</p>

                @php($k = $payload['kpis'] ?? [])
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                    <tr>
                        @foreach ([
                            ['Total Visitors', number_format($k['total_visitors'] ?? 0)],
                            ['Organic', number_format($k['organic_traffic'] ?? 0)],
                            ['Direct', number_format($k['direct_traffic'] ?? 0)],
                            ['Conversion', number_format($k['conversion_rate'] ?? 0, 2).'%'],
                        ] as [$label, $value])
                            <td width="25%" style="padding:10px;background:#1a1a1a;border:1px solid rgba(255,255,255,.08);border-radius:8px;text-align:center;">
                                <div style="font-size:11px;color:#aaa;">{{ $label }}</div>
                                <div style="font-size:18px;font-weight:700;color:#ff6600;margin-top:4px;">{{ $value }}</div>
                            </td>
                        @endforeach
                    </tr>
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Traffic Sources</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    @foreach (($payload['traffic_sources'] ?? []) as $row)
                        <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                            <td>{{ $row['label'] ?? '—' }}</td>
                            <td align="right">{{ number_format($row['value'] ?? 0) }}</td>
                            <td align="right" style="color:#aaa;">{{ $row['pct'] ?? 0 }}%</td>
                        </tr>
                    @endforeach
                </table>

                <h2 style="font-size:15px;color:#ff8533;margin:0 0 10px;">Top Pages</h2>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:12px;border-collapse:collapse;margin-bottom:18px;">
                    @foreach (array_slice($payload['top_pages'] ?? [], 0, 5) as $row)
                        <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
                            <td>{{ $row['path'] ?? '—' }}</td>
                            <td align="right">{{ number_format($row['views'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </table>

                @php($conv = $payload['conversion_summary'] ?? [])
                <p style="margin:0;font-size:13px;color:#ccc;">
                    Revenue: <strong style="color:#fff;">{{ $conv['revenue'] ?? '$0.00' }}</strong> ·
                    Transactions: <strong style="color:#fff;">{{ $conv['transactions'] ?? '0' }}</strong>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
