<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audience DB check · {{ $hostname }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #0a0a0a;
            color: #e8e8e8;
            font: 14px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            padding: 28px 20px 48px;
        }
        .wrap { max-width: 920px; margin: 0 auto; }
        h1 { font-size: 18px; font-weight: 700; margin: 0 0 6px; color: #fff; }
        .sub { color: #9ca3af; margin: 0 0 22px; font-size: 13px; }
        .card {
            border: 1px solid #2a2a2a;
            background: #111;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 14px;
        }
        .card h2 {
            margin: 0 0 10px;
            font-size: 12px;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #f97316;
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
        .stat { background: #0a0a0a; border: 1px solid #222; border-radius: 8px; padding: 10px 12px; }
        .stat b { display: block; font-size: 20px; color: #fff; margin-top: 2px; }
        .stat span { color: #9ca3af; font-size: 11px; }
        .note {
            background: #1a1208;
            border: 1px solid #7c2d12;
            color: #fdba74;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.55;
            margin-bottom: 14px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #222; }
        th { color: #9ca3af; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        tr.on-sheet td { color: #86efac; }
        tr.off-sheet td { color: #9ca3af; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-yes { background: #14532d; color: #86efac; }
        .badge-no { background: #292524; color: #a8a29e; }
        form {
            display: grid;
            grid-template-columns: 1.6fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 16px;
            align-items: end;
        }
        @media (max-width: 720px) {
            form { grid-template-columns: 1fr; }
        }
        label { display: flex; flex-direction: column; gap: 4px; font-size: 11px; color: #9ca3af; }
        select, button {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #fff;
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
            width: 100%;
        }
        button { background: #ea580c; border-color: #ea580c; cursor: pointer; font-weight: 600; }
        .url { word-break: break-all; color: #93c5fd; font-size: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Audience exclusion · live DB check</h1>
    <p class="sub">What is in the database vs what the export sheet includes (paid ads clicks only).</p>

    <form method="get" action="{{ route('audience-db-check') }}">
        <label>Domain
            <select name="domain_id" required>
                @forelse ($domains as $d)
                    <option value="{{ $d->id }}" @selected((int) $d->id === (int) $domainId)>{{ $d->hostname }}</option>
                @empty
                    <option value="">No domains</option>
                @endforelse
            </select>
        </label>
        <label>Min repeat clicks
            <select name="threshold">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 50] as $t)
                    <option value="{{ $t }}" @selected((int) $threshold === $t)>≥ {{ $t }}</option>
                @endforeach
            </select>
        </label>
        <label>Days
            <select name="days">
                @foreach ([7, 14, 30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" @selected((int) $days === $d)>{{ $d }} days</option>
                @endforeach
            </select>
        </label>
        <button type="submit">Run check</button>
    </form>

    <div class="card">
        <h2>Summary</h2>
        <div class="grid">
            <div class="stat"><span>Domain</span><b style="font-size:14px">{{ $hostname }}</b></div>
            <div class="stat"><span>Paid visits ({{ $days }}d)</span><b>{{ $paidVisits }}</b></div>
            <div class="stat"><span>Unique IPs</span><b>{{ $allIps }}</b></div>
            <div class="stat"><span>On sheet (≥ {{ $threshold }})</span><b>{{ $matchingIps }}</b></div>
        </div>
    </div>

    <div class="note">
        <strong>Why the sheet has fewer rows:</strong>
        Export is <strong>1 row per IP</strong>, not 1 row per click.
        Only IPs with paid click count <strong>≥ {{ $threshold }}</strong> appear on the sheet.
        Green = on sheet · Gray = in DB but filtered out by the rule.
    </div>

    <div class="card">
        <h2>All paid IPs in DB (last {{ $days }} days)</h2>
        @if ($rows->isEmpty())
            <p style="color:#9ca3af;margin:0">No paid ads visits found for this domain in the selected window.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>IP</th>
                    <th>Repeat clicks</th>
                    <th>First click</th>
                    <th>Last click</th>
                    <th>On export sheet?</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $i => $row)
                    @php $onSheet = (int) $row->cnt >= $threshold; @endphp
                    <tr class="{{ $onSheet ? 'on-sheet' : 'off-sheet' }}">
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row->ip }}</td>
                        <td>{{ $row->cnt }}</td>
                        <td>{{ $row->first_click }}</td>
                        <td>{{ $row->last_click }}</td>
                        <td>
                            @if ($onSheet)
                                <span class="badge badge-yes">Yes (≥ {{ $threshold }})</span>
                            @else
                                <span class="badge badge-no">No (needs ≥ {{ $threshold }})</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Share this URL</h2>
        <p class="url">{{ url()->full() }}</p>
    </div>
</div>
</body>
</html>
