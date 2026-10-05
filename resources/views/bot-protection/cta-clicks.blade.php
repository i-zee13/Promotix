@extends('layouts.admin')

@section('title', 'CTA Clicks')

@section('rightbar')
<div class="figma-rightbar-default paid-rightbar">
    @include('partials.figma-rightbar-header-actions')
    @include('partials.figma-rightbar-analytics')
</div>
@endsection

@section('content')
@php
    $tz = \App\Support\UserTimezone::forUser(auth()->user());
@endphp
<div class="brand-page-bg analytics-skin min-h-[calc(100vh-49px)]">
    <section class="mx-auto w-full min-w-0 px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <style>
            .cta-page { color: rgba(255,255,255,.88); }
            .cta-head { display:flex; flex-direction:column; gap:14px; margin-bottom:18px; }
            @media (min-width:1100px){
                .cta-head { flex-direction:row; align-items:flex-start; justify-content:space-between; }
            }
            .cta-title { font-size:28px; font-weight:650; color:#fff; letter-spacing:-.02em; line-height:1.1; }
            @media (min-width:640px){ .cta-title { font-size:32px; } }
            .cta-title__muted { color:#a9a9a9; }
            .cta-title__pipe { color:rgba(255,255,255,.35); margin:0 4px; }
            .cta-sub { margin-top:8px; font-size:13px; color:rgba(255,255,255,.45); max-width:560px; }
            .cta-filters {
                display:flex; flex-wrap:wrap; gap:8px; align-items:end;
                padding:10px 12px; border-radius:10px; background:#d9d9d9; color:#111;
            }
            .cta-filters label { display:flex; flex-direction:column; gap:3px; min-width:120px; }
            .cta-filters span { font-size:8px; font-weight:700; text-transform:uppercase; color:rgba(0,0,0,.55); }
            .cta-filters select, .cta-filters input {
                height:28px; border:0; border-radius:4px; background:#101010; color:#c8c4d0;
                padding:0 8px; font-size:11px; min-width:140px;
            }
            .cta-filters button {
                height:28px; border:0; border-radius:6px; background:var(--brand-primary,#FF6600);
                color:#fff; font-size:11px; font-weight:700; padding:0 12px; cursor:pointer;
            }
            .cta-kpis { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
            @media (min-width:900px){ .cta-kpis { grid-template-columns:repeat(4,minmax(0,1fr)); } }
            .cta-kpi {
                border-radius:10px; background:#121212; border:1px solid rgba(255,102,0,.3);
                padding:14px 12px; min-height:96px;
            }
            .cta-kpi__label { font-size:11px; color:rgba(255,255,255,.5); font-weight:600; margin-bottom:8px; }
            .cta-kpi__value { font-size:26px; font-weight:700; color:#fff; letter-spacing:-.02em; }
            .cta-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:14px; }
            @media (min-width:1100px){ .cta-grid { grid-template-columns:minmax(0,1fr) 260px; } }
            .cta-card {
                border-radius:12px; border:1px solid rgba(255,102,0,.22); background:#121212; overflow:hidden;
            }
            .cta-card__pad { padding:14px; }
            .cta-card__title { font-size:15px; font-weight:650; color:#fff; margin-bottom:12px; }
            .cta-table-wrap { overflow:auto; max-height:min(70vh,720px); }
            .cta-table { width:100%; border-collapse:collapse; font-size:11px; min-width:980px; }
            .cta-table th {
                text-align:left; padding:8px 10px; color:rgba(255,255,255,.45);
                border-bottom:1px solid rgba(255,255,255,.08); position:sticky; top:0; background:#121212;
                font-weight:600; white-space:nowrap;
            }
            .cta-table td {
                padding:9px 10px; border-bottom:1px solid rgba(255,255,255,.06);
                color:rgba(255,255,255,.82); vertical-align:top; max-width:220px;
            }
            .cta-table tr:hover td { background:rgba(255,102,0,.06); }
            .cta-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:10px; color:rgba(255,255,255,.55); word-break:break-all; }
            .cta-chip {
                display:inline-block; border-radius:999px; padding:2px 8px; font-size:10px; font-weight:700;
                background:rgba(255,102,0,.18); color:#FFB380;
            }
            .cta-empty { padding:28px; text-align:center; color:rgba(255,255,255,.4); font-size:13px; }
            .cta-top-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
            .cta-top-list li {
                display:flex; justify-content:space-between; gap:10px; align-items:center;
                padding:8px 10px; border-radius:8px; background:rgba(255,255,255,.04);
            }
            .cta-top-list .label { font-size:12px; color:#fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .cta-top-list .count { font-size:12px; font-weight:700; color:var(--brand-primary,#FF6600); flex-shrink:0; }
        </style>

        <div class="cta-page">
            <div class="cta-head">
                <div>
                    <h1 class="cta-title">
                        <span class="cta-title__muted">Page Analytics</span>
                        <span class="cta-title__pipe">|</span>
                        CTA Clicks
                    </h1>
                    <p class="cta-sub">All captured CTA click events with page, label, href, session and timing details.</p>
                </div>

                <form method="GET" action="{{ route('analytics.cta-clicks') }}" class="cta-filters">
                    <label>
                        <span>Domain</span>
                        <select name="domain_id">
                            <option value="">All Domains</option>
                            @foreach ($domains as $d)
                                <option value="{{ $d->id }}" @selected((string)($filters['domain_id'] ?? '') === (string)$d->id)>{{ $d->hostname }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>From</span>
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                    </label>
                    <label>
                        <span>To</span>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                    </label>
                    <label>
                        <span>Path</span>
                        <input type="text" name="path" value="{{ $filters['path'] ?? '' }}" placeholder="/pricing">
                    </label>
                    <label>
                        <span>Search</span>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="label, href, session…">
                    </label>
                    <button type="submit">Apply</button>
                </form>
            </div>

            <div class="cta-kpis">
                <div class="cta-kpi">
                    <div class="cta-kpi__label">Total CTA Clicks</div>
                    <div class="cta-kpi__value">{{ number_format($total) }}</div>
                </div>
                <div class="cta-kpi">
                    <div class="cta-kpi__label">Sessions with CTA</div>
                    <div class="cta-kpi__value">{{ number_format($uniqueSessions) }}</div>
                </div>
                <div class="cta-kpi">
                    <div class="cta-kpi__label">Pages with CTA</div>
                    <div class="cta-kpi__value">{{ number_format($uniquePages) }}</div>
                </div>
                <div class="cta-kpi">
                    <div class="cta-kpi__label">Showing</div>
                    <div class="cta-kpi__value">{{ number_format($events->count()) }}</div>
                </div>
            </div>

            <div class="cta-grid">
                <div class="cta-card">
                    <div class="cta-card__pad">
                        <div class="cta-card__title">CTA Click Events</div>
                        @if ($events->isEmpty())
                            <div class="cta-empty">No CTA clicks found for this range. Make sure Session Recording is on and visitors click CTAs.</div>
                        @else
                            <div class="cta-table-wrap promotix-slim-scroll">
                                <table class="cta-table">
                                    <thead>
                                        <tr>
                                            <th>Time ({{ $tz }})</th>
                                            <th>Domain</th>
                                            <th>CTA Label</th>
                                            <th>Href</th>
                                            <th>Page</th>
                                            <th>Element</th>
                                            <th>Session</th>
                                            <th>@+ms</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($events as $e)
                                            @php
                                                $when = $e['occurred_at'] !== ''
                                                    ? \Carbon\Carbon::parse($e['occurred_at'])->timezone($tz)->format('Y-m-d H:i:s')
                                                    : '—';
                                            @endphp
                                            <tr>
                                                <td class="cta-mono">{{ $when }}</td>
                                                <td>{{ $e['domain'] !== '' ? $e['domain'] : '—' }}</td>
                                                <td>
                                                    @if ($e['element_text'] !== '')
                                                        <span class="cta-chip">{{ \Illuminate\Support\Str::limit($e['element_text'], 48) }}</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="cta-mono">{{ $e['href'] !== '' ? \Illuminate\Support\Str::limit($e['href'], 60) : '—' }}</td>
                                                <td>
                                                    <div>{{ $e['page_path'] !== '' ? $e['page_path'] : '—' }}</div>
                                                    @if ($e['title'] !== '')
                                                        <div class="cta-mono">{{ \Illuminate\Support\Str::limit($e['title'], 40) }}</div>
                                                    @endif
                                                </td>
                                                <td class="cta-mono">
                                                    @if ($e['element_id'] !== '') #{{ $e['element_id'] }} @endif
                                                    @if ($e['element_class'] !== '') .{{ \Illuminate\Support\Str::limit(str_replace(' ', '.', $e['element_class']), 40) }} @endif
                                                    @if ($e['link_type'] !== '') <div>{{ $e['link_type'] }}</div> @endif
                                                    @if ($e['element_id'] === '' && $e['element_class'] === '' && $e['link_type'] === '') — @endif
                                                </td>
                                                <td class="cta-mono">{{ $e['session_id'] !== '' ? \Illuminate\Support\Str::limit($e['session_id'], 18) : '—' }}</td>
                                                <td>{{ $e['relative_ms'] > 0 ? number_format($e['relative_ms']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="cta-card">
                    <div class="cta-card__pad">
                        <div class="cta-card__title">Top CTA Labels</div>
                        @if ($topLabels->isEmpty())
                            <div class="cta-empty" style="padding:18px;">No labels yet.</div>
                        @else
                            <ul class="cta-top-list">
                                @foreach ($topLabels as $row)
                                    <li>
                                        <span class="label" title="{{ $row->element_text }}">{{ \Illuminate\Support\Str::limit($row->element_text, 28) }}</span>
                                        <span class="count">{{ number_format((int) $row->clicks) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
