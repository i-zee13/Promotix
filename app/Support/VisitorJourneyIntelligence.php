<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics → Visitor Journey aggregates (Traffic Control–style payload).
 */
class VisitorJourneyIntelligence
{
    /**
     * @param  list<int>  $domainIds
     * @param  array{campaign?:string,device?:string,path?:string,q?:string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $domainIds, Carbon $from, Carbon $to, Request $request, array $filters = []): array
    {
        if ($domainIds === [] || ! Schema::hasTable('visits')) {
            return $this->emptyPayload();
        }

        $cacheKey = 'vj:intel:v2:'.md5(json_encode([
            'domains' => array_values($domainIds),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'campaign' => trim((string) ($filters['campaign'] ?? '')),
            'device' => strtolower(trim((string) ($filters['device'] ?? ''))),
            'path' => trim((string) ($filters['path'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
            'domain_id' => (int) $request->query('domain_id', 0),
            'google_ads_account_id' => (string) $request->query('google_ads_account_id', ''),
        ]));

        return Cache::remember($cacheKey, 90, function () use ($domainIds, $from, $to, $request, $filters) {
            return $this->buildUncached($domainIds, $from, $to, $request, $filters);
        });
    }

    /**
     * @param  list<int>  $domainIds
     * @param  array{campaign?:string,device?:string,path?:string,q?:string}  $filters
     * @return array<string, mixed>
     */
    private function buildUncached(array $domainIds, Carbon $from, Carbon $to, Request $request, array $filters = []): array
    {
        $analyticsFilters = [
            'traffic_source' => '',
            'campaign' => trim((string) ($filters['campaign'] ?? '')),
            'path' => trim((string) ($filters['path'] ?? '')),
            'device' => strtolower(trim((string) ($filters['device'] ?? ''))),
            'q' => trim((string) ($filters['q'] ?? '')),
            'granularity' => '',
        ];

        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        // One lite aggregator pass (skip decoding up to 2k recording JSON blobs).
        $current = app(PageAnalyticsAggregator::class)->build(
            $domainIds,
            $from,
            $to,
            null,
            $analyticsFilters,
            'USD',
            null,
            true,
        );

        // Cheap previous-period counts for KPI deltas (avoid a second full aggregator build).
        $prevTracked = $this->cheapSessionCount($domainIds, $prevFrom, $prevTo);

        $sessionPage = app(TrafficControlSessionQuery::class)->paginate(
            $domainIds,
            $from,
            $to,
            $request,
            1,
            12,
            'paid',
            false,
        );

        $sessions = $sessionPage['data'] ?? [];
        $sessionTotal = max(
            (int) ($current['journey_summary']['sessions'] ?? 0),
            count($sessions),
        );

        $tracked = max(
            (int) ($current['journey_summary']['sessions'] ?? 0),
            $sessionTotal,
            1,
        );

        $prevTracked = max(1, $prevTracked);

        $avgDurationLabel = (string) ($current['journey_summary']['avg_session_duration'] ?? '00:00:00');
        $pagesPerSession = (float) ($current['pages_per_session'] ?? 0);

        $leadRate = (float) ($current['conversion_summary']['rate_raw']
            ?? $current['kpis']['conversion_rate']
            ?? 0);

        $engagement = collect($current['engagement'] ?? []);
        $bounced = (int) ($engagement->firstWhere('key', 'bounced')['value'] ?? 0);
        $engaged = (int) ($engagement->firstWhere('key', 'engaged')['value'] ?? 0);
        $high = (int) ($engagement->firstWhere('key', 'highly_engaged')['value'] ?? 0);
        $engagedSessions = $engaged + $high;
        $singlePagePct = round(($bounced / max(1, $bounced + $engaged + $high)) * 100, 1);

        // Previous engagement/conversion deltas skipped (lite path) — keep charts fast.
        $prevLead = $leadRate;
        $prevPages = $pagesPerSession;
        $prevSingle = $singlePagePct;
        $prevEngagedSessions = $engagedSessions;

        $vsLabel = 'vs previous '.$days.' days';

        $kpis = [
            [
                'key' => 'tracked_sessions',
                'label' => 'Tracked Sessions',
                'value' => $tracked,
                'display' => number_format($tracked),
                'delta' => $this->pctDelta($tracked, $prevTracked),
                'vs_label' => $vsLabel,
                'tone' => 'orange',
                'spark' => $this->spark($tracked),
            ],
            [
                'key' => 'avg_duration',
                'label' => 'Avg. Session Duration',
                'value' => $this->durationToSeconds($avgDurationLabel),
                'display' => $avgDurationLabel,
                'delta' => 0.0,
                'vs_label' => $vsLabel,
                'tone' => 'orange',
                'spark' => $this->spark($this->durationToSeconds($avgDurationLabel)),
            ],
            [
                'key' => 'pages_per_session',
                'label' => 'Pages per Session',
                'value' => $pagesPerSession,
                'display' => number_format($pagesPerSession, 2),
                'delta' => $this->pctDelta($pagesPerSession, $prevPages),
                'vs_label' => $vsLabel,
                'tone' => 'orange',
                'spark' => $this->spark((int) round($pagesPerSession * 10)),
            ],
            [
                'key' => 'lead_conversion',
                'label' => 'Lead Conversion Rate',
                'value' => $leadRate,
                'display' => number_format($leadRate, 1).'%',
                'delta' => $this->pctDelta($leadRate, $prevLead),
                'vs_label' => $vsLabel,
                'tone' => 'orange',
                'spark' => $this->spark((int) round($leadRate * 10)),
            ],
            [
                'key' => 'single_page',
                'label' => 'Single-Page Sessions',
                'value' => $singlePagePct,
                'display' => number_format($singlePagePct, 1).'%',
                'delta' => $this->pctDelta($singlePagePct, $prevSingle),
                'vs_label' => $vsLabel,
                'tone' => 'orange',
                'spark' => $this->spark((int) round($singlePagePct)),
                'delta_bad_when_up' => true,
            ],
            [
                'key' => 'engaged_sessions',
                'label' => 'Engaged Sessions',
                'value' => $engagedSessions,
                'display' => number_format($engagedSessions),
                'delta' => $this->pctDelta($engagedSessions, max(1, $prevEngagedSessions)),
                'vs_label' => $vsLabel,
                'tone' => 'orange',
                'spark' => $this->spark($engagedSessions),
            ],
        ];

        $shaped = array_map(fn (array $row) => $this->shapeSession($row), $sessions);
        $recent = $shaped;
        $flow = $this->buildFlow($current, $shaped, $tracked);
        $outcomes = $this->buildOutcomes($shaped, $tracked, $leadRate, $bounced);

        $commonPaths = collect($current['journey_paths'] ?? [])
            ->take(6)
            ->values()
            ->map(function ($row, $i) use ($tracked) {
                $value = (int) ($row['value'] ?? 0);

                return [
                    'rank' => $i + 1,
                    'path' => (string) ($row['path'] ?? $row['label'] ?? ''),
                    'steps' => $this->splitPath((string) ($row['path'] ?? $row['label'] ?? '')),
                    'value' => $value,
                    'pct' => round(($value / max(1, $tracked)) * 100, 1),
                ];
            })
            ->all();

        $landing = collect($current['top_landing_pages'] ?? [])->take(6)->values()->map(fn ($r) => [
            'label' => (string) ($r['path'] ?? $r['label'] ?? ''),
            'value' => (int) ($r['value'] ?? 0),
            'pct' => (float) ($r['pct'] ?? 0),
        ])->all();

        $exits = collect($current['top_exit_pages'] ?? [])->take(6)->values()->map(fn ($r) => [
            'label' => (string) ($r['path'] ?? $r['label'] ?? ''),
            'value' => (int) ($r['value'] ?? 0),
            'pct' => (float) ($r['pct'] ?? 0),
        ])->all();

        $selected = $recent[0] ?? null;

        $campaigns = collect($sessions)
            ->pluck('campaign')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->take(60)
            ->all();

        return [
            'kpis' => $kpis,
            'flow' => $flow,
            'common_paths' => $commonPaths,
            'landing_pages' => $landing,
            'exit_pages' => $exits,
            'outcomes' => $outcomes,
            'sessions' => $recent,
            'selected' => $selected,
            'timeline' => $selected['timeline'] ?? [],
            'meta' => [
                'session_total' => $sessionTotal,
                'tracked' => $tracked,
                'campaigns' => $campaigns,
                'days' => $days,
            ],
        ];
    }

    /**
     * Fast distinct-session estimate for previous-period KPI delta.
     *
     * @param  list<int>  $domainIds
     */
    private function cheapSessionCount(array $domainIds, Carbon $from, Carbon $to): int
    {
        if ($domainIds === [] || ! Schema::hasTable('visits')) {
            return 0;
        }

        $sessionExpr = Schema::hasColumn('visits', 'session_id')
            ? "COALESCE(NULLIF(session_id, ''), CONCAT('ip:', ip))"
            : "CONCAT('ip:', ip)";

        return (int) DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->selectRaw("COUNT(DISTINCT CONCAT(domain_id, '|', {$sessionExpr})) as c")
            ->value('c');
    }

    /** @param  array<string, mixed>  $row */
    private function shapeSession(array $row): array
    {
        $pages = array_values(array_filter(array_map(
            fn ($p) => $this->shortPath((string) $p),
            $row['pages'] ?? [],
        )));
        if ($pages === [] && ! empty($row['landing_page'])) {
            $pages[] = $this->shortPath((string) $row['landing_page']);
        }

        $actions = [];
        foreach ($row['event_actions'] ?? [] as $ev) {
            $key = (string) ($ev['key'] ?? '');
            if ($key === 'cta_click') {
                $actions[] = ['label' => 'cta clicked', 'tone' => 'action'];
            } elseif (in_array($key, ['tel_click', 'call_click'], true)) {
                $actions[] = ['label' => 'call button click', 'tone' => 'action'];
            } elseif (in_array($key, ['form_start', 'form_submit', 'form_fills'], true)) {
                $actions[] = ['label' => (str_contains($key, 'submit') ? 'form submit' : 'form started'), 'tone' => 'form'];
            } elseif ($key === 'purchase') {
                $actions[] = ['label' => 'purchase', 'tone' => 'form'];
            }
        }

        $purchases = ($row['purchase'] ?? 'No') === 'Yes' || (int) ($row['form_submits'] ?? 0) > 0;
        $hasAction = $actions !== [];
        if ($purchases) {
            $outcome = ['label' => 'Lead confirmed', 'tone' => 'lead'];
        } elseif ($hasAction) {
            $outcome = ['label' => 'Pending', 'tone' => 'pending'];
        } else {
            $outcome = ['label' => 'No conversion', 'tone' => 'none'];
            $actions[] = ['label' => 'exit', 'tone' => 'exit'];
        }

        $pathChips = [];
        foreach (array_slice($pages, 0, 4) as $p) {
            $pathChips[] = ['label' => $p, 'tone' => 'page'];
        }
        foreach (array_slice($actions, 0, 2) as $a) {
            $pathChips[] = $a;
        }

        $timeline = $this->buildTimeline($row, $pages);

        $durationSec = $this->sessionDurationSeconds($row);
        $timelineMax = 0;
        foreach ($timeline as $ev) {
            $timelineMax = max($timelineMax, (int) ($ev['elapsed_sec'] ?? 0));
        }
        // Bounce / same-second visits often report 00:00:00 while the timeline has a real span.
        if ($durationSec <= 0 && $timelineMax > 0) {
            $durationSec = $timelineMax;
        }
        // Duration label can be correct while exit is still stuck at 0:00 — pin exit to duration.
        $timeline = $this->syncExitToDuration($timeline, $durationSec, $row);
        $durationLabel = $this->friendlyDuration(sprintf(
            '%02d:%02d:%02d',
            intdiv($durationSec, 3600),
            intdiv($durationSec % 3600, 60),
            $durationSec % 60
        ));
        $sessionId = (string) ($row['session_id'] ?? $row['session_key'] ?? '');
        $deviceRaw = trim((string) ($row['device_id_raw'] ?? ''));
        $fpRaw = trim((string) ($row['fingerprint_id'] ?? ''));
        // Ignore legacy TC rows that copied Device ID label into fingerprint_id.
        if ($fpRaw !== '' && str_starts_with($fpRaw, 'DEV_')) {
            $fpRaw = '';
        }
        $ip = trim((string) ($row['ip'] ?? ''));
        $displayDevice = trim((string) ($row['device_id'] ?? ''));
        $deviceId = $displayDevice !== '' && str_starts_with($displayDevice, 'DEV_')
            ? $displayDevice
            : DeviceIdLabel::format(
                $deviceRaw !== '' ? $deviceRaw : null,
                $fpRaw !== '' ? $fpRaw : null,
                $ip !== '' ? $ip : null,
            );
        if ($sessionId !== '' && str_starts_with($sessionId, 'unknown_')) {
            $sessionId = 'ses_'.substr(sha1($sessionId), 0, 6);
        } elseif ($sessionId !== '' && ! str_starts_with($sessionId, 'ses_') && strlen($sessionId) > 12) {
            $sessionId = 'ses_'.substr(sha1($sessionId), 0, 6);
        } elseif ($sessionId === '') {
            $sessionId = 'ses_'.substr(sha1($ip !== '' ? $ip : 'x'), 0, 6);
        }

        return [
            'session_id' => $sessionId,
            'session_key' => (string) ($row['session_key'] ?? $sessionId),
            'ip' => $ip !== '' ? $ip : '—',
            'fingerprint_id' => $fpRaw !== '' ? $fpRaw : '—',
            'fingerprint_short' => $fpRaw !== '' ? $this->shortId($fpRaw) : '—',
            'device_id' => $deviceId,
            'device_id_raw' => $deviceRaw,
            'device' => (string) ($row['device'] ?? '—'),
            'browser' => (string) ($row['browser'] ?? '—'),
            'os' => (string) ($row['os'] ?? '—'),
            'status' => 'Ended',
            'campaign' => (string) ($row['campaign'] ?? $row['source_platform'] ?? '—'),
            'source' => (string) ($row['source_platform'] ?? 'Google Ads'),
            'duration' => $durationLabel,
            'duration_raw' => sprintf('%02d:%02d:%02d', intdiv($durationSec, 3600), intdiv($durationSec % 3600, 60), $durationSec % 60),
            'duration_sec' => $durationSec,
            'path_chips' => $pathChips,
            'path_footer' => array_values(array_merge(
                array_map(fn ($p) => ['label' => $p, 'tone' => 'page'], array_slice($pages, 0, 3)),
                [['label' => 'Exit', 'tone' => 'exit']],
            )),
            'outcome' => $outcome,
            'landing_page' => $this->shortPath((string) ($row['landing_page'] ?? '/')),
            'exit_page' => $this->shortPath((string) ($row['exit_page'] ?? '—')),
            'page_views' => (int) ($row['page_views'] ?? max(1, count($pages))),
            // Keep raw action keys for Page Paths Action column (buildFlow).
            'event_actions' => array_values(array_filter(
                $row['event_actions'] ?? [],
                fn ($ev) => is_array($ev) && filled($ev['key'] ?? null),
            )),
            'cta_clicks' => (int) ($row['cta_clicks'] ?? 0) + (int) ($row['tel_clicks'] ?? 0),
            'tel_clicks' => (int) ($row['tel_clicks'] ?? 0),
            'form_submits' => (int) ($row['form_submits'] ?? 0) + (int) ($row['form_fills'] ?? 0),
            'gclid_captured' => filled($row['gclid'] ?? null)
                || str_contains(strtolower((string) ($row['source_platform'] ?? '')), 'google')
                || (bool) ($row['is_paid'] ?? false),
            'start_time' => (string) ($row['entry_clock'] ?? $this->extractClock((string) ($row['first_seen'] ?? ''))),
            'start_label' => $this->ampmFromClock((string) ($row['entry_clock'] ?? $this->extractClock((string) ($row['first_seen'] ?? '')))),
            'last_event_time' => (string) ($row['exit_clock'] ?? $this->extractClock((string) ($row['last_seen'] ?? ''))),
            'alert' => $this->sessionAlert($timeline),
            'timeline' => $timeline,
            'first_seen' => (string) ($row['first_seen'] ?? ''),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
        ];
    }

    /** @param  list<array<string, mixed>>  $timeline */
    private function sessionAlert(array $timeline): string
    {
        foreach ($timeline as $ev) {
            if (($ev['type'] ?? '') === 'cta' && filled($ev['note'] ?? null)) {
                return 'Call click recorded · '.trim((string) $ev['note']);
            }
        }

        return '';
    }

    private function extractClock(string $label): string
    {
        if (preg_match('/(\d{1,2}:\d{2}(?::\d{2})?)/', $label, $m)) {
            $clock = $m[1];
            if (substr_count($clock, ':') === 1) {
                return $clock.':00';
            }

            return $clock;
        }

        return '';
    }

    private function ampmFromClock(string $clock): string
    {
        $sec = $this->parseClock($clock);
        if ($sec <= 0 && $clock === '') {
            return '—';
        }
        $h24 = intdiv($sec, 3600) % 24;
        $m = intdiv($sec % 3600, 60);
        $ampm = $h24 >= 12 ? 'PM' : 'AM';
        $h12 = $h24 % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }

        return sprintf('%d:%02d %s', $h12, $m, $ampm);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $pages
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(array $row, array $pages): array
    {
        $detail = $row['event_detail'] ?? [];
        $flat = [];
        if (is_array($detail) && $detail !== []) {
            // Nested shape from TrafficControlSessionQuery recordings.
            if (isset($detail['timeline']) && is_array($detail['timeline'])) {
                $flat = $detail['timeline'];
            } elseif (array_is_list($detail)) {
                $flat = $detail;
            } else {
                foreach (['pages', 'scroll', 'cta', 'tel', 'phone', 'form', 'commerce'] as $bucket) {
                    if (! empty($detail[$bucket]) && is_array($detail[$bucket])) {
                        foreach ($detail[$bucket] as $ev) {
                            if (is_array($ev)) {
                                $flat[] = $ev;
                            }
                        }
                    }
                }
            }
        }

        $sessionDur = $this->sessionDurationSeconds($row);
        $sessionStartTs = $this->sessionStartUnix($row);
        $entryClock = (string) ($row['entry_clock'] ?? $this->extractClock((string) ($row['first_seen'] ?? '')) ?: '00:00:00');
        $base = $this->parseClock($entryClock);

        if ($flat !== []) {
            usort($flat, function ($a, $b) use ($sessionStartTs): int {
                $ta = $this->rawEventSortKey(is_array($a) ? $a : [], $sessionStartTs);
                $tb = $this->rawEventSortKey(is_array($b) ? $b : [], $sessionStartTs);
                if ($ta === $tb) {
                    return strcmp((string) (($a['at'] ?? '')), (string) (($b['at'] ?? '')));
                }

                return $ta <=> $tb;
            });

            $out = [];
            $prevElapsed = 0;
            foreach (array_slice($flat, 0, 40) as $i => $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $type = $this->normalizeEventType((string) ($ev['type'] ?? $ev['kind'] ?? $ev['label'] ?? ''));
                $elapsed = $this->resolveEventElapsedSec($ev, $sessionStartTs, $i === 0 ? 0 : $prevElapsed);
                $prevElapsed = $elapsed;
                $label = (string) ($ev['label'] ?? $ev['name'] ?? $ev['path'] ?? $ev['detail'] ?? 'event');
                $page = $this->shortPath((string) ($ev['page'] ?? $ev['path'] ?? $ev['page_url'] ?? ($pages[min($i, max(0, count($pages) - 1))] ?? '/')));
                $clock = (string) ($ev['time'] ?? '');
                if ($clock === '' && ! empty($ev['at'])) {
                    $clock = $this->extractClock((string) $ev['at']) ?: $this->formatClock($base + $elapsed);
                }
                if ($clock === '') {
                    $clock = $this->formatClock($base + $elapsed);
                }
                $out[] = $this->timelineEvent([
                    'type' => $type,
                    'label' => $this->shortEventLabel($label, $type),
                    'event' => $label,
                    'kind' => (string) ($ev['kind'] ?? $this->kindForType($type)),
                    'time' => $clock,
                    'elapsed_sec' => $elapsed,
                    'page' => $page,
                    'note' => (string) ($ev['note'] ?? ''),
                    'status' => (string) ($ev['status'] ?? $this->statusForType($type)),
                ]);
            }
            if ($out !== []) {
                return $this->finalizeTimelineExits($out, $row, $sessionDur, $base);
            }
        }

        // No recorder events — build from page path + real session duration (not demo gaps).
        $pageSlice = array_values(array_slice($pages, 0, 6));
        if ($pageSlice === []) {
            $pageSlice = ['/'];
        }
        $n = count($pageSlice);
        $out = [];
        foreach ($pageSlice as $i => $page) {
            $elapsed = ($n <= 1 || $sessionDur <= 0)
                ? 0
                : (int) round(($i / max(1, $n - 1)) * $sessionDur);
            $out[] = $this->timelineEvent([
                'type' => 'page',
                'label' => $page,
                'event' => $page,
                'kind' => 'Page view',
                'time' => $this->formatClock($base + $elapsed),
                'elapsed_sec' => $elapsed,
                'page' => $page,
                'note' => '',
                'status' => 'Page viewed',
            ]);
        }
        if ((int) ($row['scroll_events'] ?? 0) > 0 && $sessionDur > 0) {
            $scrollAt = min($sessionDur, max(1, (int) round($sessionDur * 0.25)));
            array_splice($out, min(1, count($out)), 0, [$this->timelineEvent([
                'type' => 'scroll',
                'label' => 'Scroll',
                'event' => 'scroll',
                'kind' => 'Scroll',
                'time' => $this->formatClock($base + $scrollAt),
                'elapsed_sec' => $scrollAt,
                'page' => $pageSlice[0],
                'note' => '',
                'status' => 'Scroll recorded',
            ])]);
        }
        if ((int) ($row['form_starts'] ?? 0) > 0 || (int) ($row['form_submits'] ?? 0) > 0) {
            $formAt = $sessionDur > 0 ? min($sessionDur, max(1, (int) round($sessionDur * 0.55))) : 0;
            $out[] = $this->timelineEvent([
                'type' => 'form',
                'label' => 'availability check',
                'event' => 'availability_check',
                'kind' => 'Form submit',
                'time' => $this->formatClock($base + $formAt),
                'elapsed_sec' => $formAt,
                'page' => $pageSlice[min(1, $n - 1)],
                'note' => '',
                'status' => 'Submitted',
            ]);
        }
        if ((int) ($row['cta_clicks'] ?? 0) > 0 || (int) ($row['tel_clicks'] ?? 0) > 0) {
            $ctaAt = $sessionDur > 0 ? min($sessionDur, max(1, (int) round($sessionDur * 0.7))) : 0;
            $out[] = $this->timelineEvent([
                'type' => 'cta',
                'label' => 'Call click',
                'event' => 'call_button_click',
                'kind' => 'CTA click',
                'time' => $this->formatClock($base + $ctaAt),
                'elapsed_sec' => $ctaAt,
                'page' => $pageSlice[min(1, $n - 1)],
                'note' => 'Call outcome unavailable.',
                'status' => 'Click recorded',
            ]);
        }

        usort($out, static fn ($a, $b) => ((int) ($a['elapsed_sec'] ?? 0)) <=> ((int) ($b['elapsed_sec'] ?? 0)));

        return $this->finalizeTimelineExits($out, $row, $sessionDur, $base);
    }

    /**
     * Ensure Exit sits at real session end (never stuck at 0:00 beside the first page view).
     *
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function finalizeTimelineExits(array $out, array $row, int $sessionDur, int $baseClock): array
    {
        $maxOther = 0;
        foreach ($out as $ev) {
            if (($ev['type'] ?? '') === 'exit') {
                continue;
            }
            $maxOther = max($maxOther, (int) ($ev['elapsed_sec'] ?? 0));
        }
        $maxRaw = $this->maxRawTimelineElapsed($row);
        $exitAt = max($sessionDur, $maxOther, $maxRaw);

        // Clock delta (entry → exit) when duration_sec was rounded to 0 — but never
        // treat multi-hour idle gaps between sparse hits as engaged time.
        $entry = $this->parseClock((string) ($row['entry_clock'] ?? ''));
        $exitClockSec = $this->parseClock((string) ($row['exit_clock'] ?? ''));
        if ($exitClockSec > $entry) {
            $clockSpan = $exitClockSec - $entry;
            $idleCap = 30 * 60;
            if ($exitAt > 0) {
                // Keep engaged duration; ignore huge idle wall-clock.
            } else {
                $exitAt = min($clockSpan, $idleCap);
            }
        }

        $exitClock = (string) ($row['exit_clock'] ?? $this->extractClock((string) ($row['last_seen'] ?? '')));
        if ($exitClock === '') {
            $exitClock = $this->formatClock($baseClock + $exitAt);
        }

        $hasExit = false;
        foreach ($out as $i => $ev) {
            if (($ev['type'] ?? '') !== 'exit') {
                continue;
            }
            $hasExit = true;
            $cur = (int) ($ev['elapsed_sec'] ?? 0);
            $target = max($cur, $exitAt);
            // Exit must not share 0:00 with the landing page when we know the session lasted longer.
            if ($target <= 0 && $maxOther <= 0 && $maxRaw <= 0 && $sessionDur <= 0) {
                continue;
            }
            if ($target !== $cur || $cur === 0 && $target > 0) {
                $out[$i] = $this->timelineEvent([
                    'type' => 'exit',
                    'label' => (string) ($ev['label'] ?? 'Exit'),
                    'event' => (string) ($ev['event'] ?? 'session_end'),
                    'kind' => (string) ($ev['kind'] ?? 'Exit'),
                    'time' => $exitClock !== '' ? $exitClock : (string) ($ev['time'] ?? ''),
                    'elapsed_sec' => $target,
                    'page' => (string) ($ev['page'] ?? ($row['exit_page'] ?? '/')),
                    'note' => (string) ($ev['note'] ?? ''),
                    'status' => (string) ($ev['status'] ?? 'Session ended'),
                ]);
            }
        }

        if (! $hasExit) {
            $out[] = $this->timelineEvent([
                'type' => 'exit',
                'label' => 'Exit',
                'event' => 'session_end',
                'kind' => 'Exit',
                'time' => $exitClock,
                'elapsed_sec' => $exitAt,
                'page' => $this->shortPath((string) ($row['exit_page'] ?? ($out[count($out) - 1]['page'] ?? '/'))),
                'note' => '',
                'status' => 'Session ended',
            ]);
        }

        usort($out, static fn ($a, $b) => ((int) ($a['elapsed_sec'] ?? 0)) <=> ((int) ($b['elapsed_sec'] ?? 0)));

        return array_values($this->dedupeTimelineEvents($out));
    }

    /**
     * Pin Exit marker to engaged session duration (e.g. 14s left label → Exit at 0:14).
     *
     * @param  list<array<string, mixed>>  $timeline
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function syncExitToDuration(array $timeline, int $durationSec, array $row): array
    {
        if ($durationSec <= 0) {
            return $this->dedupeTimelineEvents($timeline);
        }

        $exitClock = (string) ($row['exit_clock'] ?? $this->extractClock((string) ($row['last_seen'] ?? '')));
        $hasExit = false;
        foreach ($timeline as $i => $ev) {
            if (($ev['type'] ?? '') !== 'exit') {
                continue;
            }
            $hasExit = true;
            $cur = (int) ($ev['elapsed_sec'] ?? 0);
            if ($cur >= $durationSec) {
                continue;
            }
            $timeline[$i] = $this->timelineEvent([
                'type' => 'exit',
                'label' => (string) ($ev['label'] ?? 'Exit'),
                'event' => (string) ($ev['event'] ?? 'session_end'),
                'kind' => (string) ($ev['kind'] ?? 'Exit'),
                'time' => $exitClock !== '' ? $exitClock : (string) ($ev['time'] ?? ''),
                'elapsed_sec' => $durationSec,
                'page' => (string) ($ev['page'] ?? ($row['exit_page'] ?? '/')),
                'note' => (string) ($ev['note'] ?? ''),
                'status' => (string) ($ev['status'] ?? 'Session ended'),
            ]);
        }

        if (! $hasExit) {
            $timeline[] = $this->timelineEvent([
                'type' => 'exit',
                'label' => 'Exit',
                'event' => 'session_end',
                'kind' => 'Exit',
                'time' => $exitClock,
                'elapsed_sec' => $durationSec,
                'page' => $this->shortPath((string) ($row['exit_page'] ?? '/')),
                'note' => '',
                'status' => 'Session ended',
            ]);
        }

        usort($timeline, static fn ($a, $b) => ((int) ($a['elapsed_sec'] ?? 0)) <=> ((int) ($b['elapsed_sec'] ?? 0)));

        return array_values($this->dedupeTimelineEvents($timeline));
    }

    /**
     * Drop duplicate markers (same type + second + label) that cause double-rendered UI.
     *
     * @param  list<array<string, mixed>>  $timeline
     * @return list<array<string, mixed>>
     */
    private function dedupeTimelineEvents(array $timeline): array
    {
        $seen = [];
        $out = [];
        foreach ($timeline as $ev) {
            if (! is_array($ev)) {
                continue;
            }
            $key = strtolower((string) ($ev['type'] ?? '')).'|'
                .(int) ($ev['elapsed_sec'] ?? 0).'|'
                .strtolower(trim((string) ($ev['label'] ?? $ev['event'] ?? '')));
            if (isset($seen[$key])) {
                continue;
            }
            // Only one Exit marker per session.
            if (($ev['type'] ?? '') === 'exit' && isset($seen['__exit__'])) {
                continue;
            }
            if (($ev['type'] ?? '') === 'exit') {
                $seen['__exit__'] = true;
            }
            $seen[$key] = true;
            $out[] = $ev;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $row */
    private function maxRawTimelineElapsed(array $row): int
    {
        $detail = $row['event_detail'] ?? [];
        $flat = [];
        if (isset($detail['timeline']) && is_array($detail['timeline'])) {
            $flat = $detail['timeline'];
        } elseif (is_array($detail)) {
            foreach (['pages', 'scroll', 'cta', 'tel', 'phone', 'form', 'commerce'] as $bucket) {
                if (! empty($detail[$bucket]) && is_array($detail[$bucket])) {
                    foreach ($detail[$bucket] as $ev) {
                        if (is_array($ev)) {
                            $flat[] = $ev;
                        }
                    }
                }
            }
        }

        $max = 0;
        foreach ($flat as $ev) {
            if (! is_array($ev)) {
                continue;
            }
            $elapsed = (int) ($ev['elapsed_sec'] ?? 0);
            if ($elapsed > 0) {
                $max = max($max, $elapsed);
            }
            $rawT = (int) ($ev['t'] ?? 0);
            if ($rawT > 0) {
                $max = max($max, $rawT >= 1000 ? (int) floor($rawT / 1000) : $rawT);
            }
        }

        // Recording duration_ms (when visits first/last collapse to the same second).
        if (isset($row['duration_ms']) && (int) $row['duration_ms'] > 0) {
            $max = max($max, (int) floor(((int) $row['duration_ms']) / 1000));
        }

        return $max;
    }

    /** @param  array<string, mixed>  $row */
    private function sessionDurationSeconds(array $row): int
    {
        $idleCap = 30 * 60;
        $fromEvents = $this->maxRawTimelineElapsed($row);

        if (isset($row['duration_ms']) && (int) $row['duration_ms'] > 0) {
            $fromMs = (int) floor(((int) $row['duration_ms']) / 1000);
            if ($fromMs > 0) {
                return max($fromMs, $fromEvents);
            }
        }

        if (isset($row['duration_sec']) && (int) $row['duration_sec'] > 0) {
            // Trust TC engaged duration when already capped; still prefer event max if higher.
            return max((int) $row['duration_sec'], $fromEvents);
        }

        $dur = $this->durationToSeconds((string) ($row['time_on_site'] ?? '00:00:00'));
        if ($dur > 0) {
            return min(max($dur, $fromEvents), max($fromEvents, $idleCap));
        }
        $first = $this->parseFlexibleUnix((string) ($row['first_seen_at'] ?? $row['first_seen'] ?? ''));
        $last = $this->parseFlexibleUnix((string) ($row['last_seen_at'] ?? $row['last_seen'] ?? ''));
        if ($first !== null && $last !== null && $last >= $first) {
            $wall = (int) ($last - $first);
            if ($fromEvents > 0) {
                return $fromEvents;
            }

            return min($wall, $idleCap);
        }
        $entry = $this->parseClock((string) ($row['entry_clock'] ?? ''));
        $exit = $this->parseClock((string) ($row['exit_clock'] ?? ''));
        if ($exit > $entry) {
            $wall = $exit - $entry;
            if ($fromEvents > 0) {
                return $fromEvents;
            }

            return min($wall, $idleCap);
        }
        if ($fromEvents > 0) {
            return $fromEvents;
        }

        return 0;
    }

    /** @param  array<string, mixed>  $row */
    private function sessionStartUnix(array $row): ?int
    {
        return $this->parseFlexibleUnix((string) ($row['first_seen_at'] ?? ''))
            ?? $this->parseFlexibleUnix((string) ($row['first_seen'] ?? ''))
            ?? $this->parseFlexibleUnix((string) ($row['entry_at'] ?? ''));
    }

    private function parseFlexibleUnix(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '—' || $raw === '-') {
            return null;
        }
        if (ctype_digit($raw)) {
            $n = (int) $raw;

            return $n > 1_000_000_000_000 ? (int) floor($n / 1000) : $n;
        }
        $ts = strtotime($raw);

        return $ts !== false ? $ts : null;
    }

    /** @param  array<string, mixed>  $ev */
    private function rawEventSortKey(array $ev, ?int $sessionStartTs): int
    {
        $elapsed = $this->resolveEventElapsedSec($ev, $sessionStartTs, 0);

        return $elapsed;
    }

    /** @param  array<string, mixed>  $ev */
    private function resolveEventElapsedSec(array $ev, ?int $sessionStartTs, int $fallbackPrev): int
    {
        $elapsed = (int) ($ev['elapsed_sec'] ?? 0);
        if ($elapsed > 0) {
            return $elapsed;
        }
        $rawT = (int) ($ev['t'] ?? 0);
        if ($rawT > 0) {
            // Recorder may send ms (>= 1000 for multi-second) or seconds.
            return $rawT >= 1000 ? (int) floor($rawT / 1000) : $rawT;
        }
        if ($sessionStartTs !== null && ! empty($ev['at'])) {
            $at = $this->parseFlexibleUnix((string) $ev['at']);
            if ($at !== null && $at >= $sessionStartTs) {
                return (int) ($at - $sessionStartTs);
            }
        }
        // Keep honest stacking at previous time — never invent demo gaps (+18s).
        return max(0, $fallbackPrev);
    }

    /** @param  array<string, mixed>  $data */
    private function timelineEvent(array $data): array
    {
        $elapsed = max(0, (int) ($data['elapsed_sec'] ?? 0));
        $type = (string) ($data['type'] ?? 'page');

        return [
            'id' => md5(($data['event'] ?? '').'|'.$elapsed.'|'.$type),
            'type' => $type,
            'label' => (string) ($data['label'] ?? ''),
            'title' => (string) ($data['title'] ?? $this->titleForType($type, (string) ($data['label'] ?? ''))),
            'event' => (string) ($data['event'] ?? ''),
            'tag' => (string) ($data['tag'] ?? $this->tagForEvent((string) ($data['event'] ?? ''), $type)),
            'kind' => (string) ($data['kind'] ?? $this->kindForType($type)),
            'time' => (string) ($data['time'] ?? ''),
            'elapsed_sec' => $elapsed,
            'elapsed' => sprintf('%02d:%02d', intdiv($elapsed, 60), $elapsed % 60),
            'elapsed_short' => sprintf('%d:%02d', intdiv($elapsed, 60), $elapsed % 60),
            'page' => (string) ($data['page'] ?? '/'),
            'note' => (string) ($data['note'] ?? ''),
            'status' => (string) ($data['status'] ?? $this->statusForType($type)),
            'gap_before' => (string) ($data['gap_before'] ?? ''),
        ];
    }

    private function titleForType(string $type, string $label = ''): string
    {
        return match ($type) {
            'scroll' => 'Scroll',
            'cta' => 'Call button clicked',
            'form' => (str_contains(strtolower($label), 'availability') ? 'Availability checked' : 'Form submitted'),
            'exit' => 'Session ended',
            default => (str_starts_with($label, '/') ? 'Page viewed' : ($label !== '' ? $label : 'Page viewed')),
        };
    }

    private function tagForEvent(string $event, string $type): string
    {
        $event = trim($event);
        if ($event !== '' && ! str_starts_with($event, '/')) {
            return $event;
        }

        return match ($type) {
            'scroll' => 'scroll',
            'cta' => 'call_button_click',
            'form' => 'form_submit',
            'exit' => 'session_end',
            default => 'page_view',
        };
    }

    private function normalizeEventType(string $raw): string
    {
        $s = strtolower($raw);
        if (str_contains($s, 'scroll')) {
            return 'scroll';
        }
        if (str_contains($s, 'cta') || str_contains($s, 'call') || str_contains($s, 'click')) {
            return 'cta';
        }
        if (str_contains($s, 'form') || str_contains($s, 'submit') || str_contains($s, 'availability')) {
            return 'form';
        }
        if (str_contains($s, 'exit') || str_contains($s, 'end') || str_contains($s, 'session_end')) {
            return 'exit';
        }

        return 'page';
    }

    private function kindForType(string $type): string
    {
        return match ($type) {
            'scroll' => 'Scroll',
            'cta' => 'CTA click',
            'form' => 'Form submit',
            'exit' => 'Exit',
            default => 'Page view',
        };
    }

    private function statusForType(string $type): string
    {
        return match ($type) {
            'scroll' => 'Scroll recorded',
            'cta' => 'Click recorded',
            'form' => 'Submitted',
            'exit' => 'Session ended',
            default => 'Page viewed',
        };
    }

    private function shortEventLabel(string $label, string $type): string
    {
        $label = trim($label);
        if ($type === 'cta') {
            return 'Call click';
        }
        if ($type === 'form') {
            return strlen($label) > 18 ? 'Form submit' : $label;
        }
        if ($type === 'exit') {
            return 'Exit';
        }
        if ($type === 'scroll') {
            return 'Scroll';
        }

        return $this->shortPath($label);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<array<string, mixed>>  $recent
     * @return array{columns: list<array<string, mixed>>, links: list<array<string, mixed>>}
     */
    private function buildFlow(array $current, array $recent, int $tracked): array
    {
        $landing = collect($current['top_landing_pages'] ?? [])->take(12)->values();
        if ($landing->isEmpty()) {
            $landing = collect($recent)
                ->groupBy('landing_page')
                ->map(fn ($rows, $path) => ['label' => $path, 'value' => $rows->count()])
                ->sortByDesc('value')
                ->take(12)
                ->values();
        }

        $colLanding = $landing->map(function ($r) use ($tracked) {
            $value = (int) ($r['value'] ?? 0);

            return [
                'id' => 'l:'.($r['path'] ?? $r['label'] ?? ''),
                'label' => (string) ($r['path'] ?? $r['label'] ?? '/'),
                'value' => $value,
                'pct' => round(($value / max(1, $tracked)) * 100, 1),
                'tone' => 'default',
            ];
        })->all();

        $nextCounts = [];
        foreach ($current['journey_paths'] ?? [] as $pathRow) {
            $steps = $this->splitPath((string) ($pathRow['path'] ?? ''));
            if (count($steps) >= 2) {
                $next = $steps[1];
                $nextCounts[$next] = ($nextCounts[$next] ?? 0) + (int) ($pathRow['value'] ?? 0);
            }
        }
        arsort($nextCounts);
        $colNext = [];
        $i = 0;
        foreach ($nextCounts as $label => $value) {
            if ($i >= 11) {
                break;
            }
            $colNext[] = [
                'id' => 'n:'.$label,
                'label' => $label,
                'value' => $value,
                'pct' => round(($value / max(1, $tracked)) * 100, 1),
                'tone' => 'default',
            ];
            $i++;
        }
        $exitNext = max(0, $tracked - array_sum(array_column($colNext, 'value')));
        if ($exitNext > 0 || $colNext === []) {
            $colNext[] = [
                'id' => 'n:Exit',
                'label' => 'Exit',
                'value' => max($exitNext, (int) round($tracked * 0.08)),
                'pct' => round((max($exitNext, (int) round($tracked * 0.08)) / max(1, $tracked)) * 100, 1),
                'tone' => 'exit',
            ];
        }

        $actionBuckets = [
            'Page viewed' => 0,
            'Pricing viewed' => 0,
            'Provider selected' => 0,
            'ZIP checked' => 0,
            'CTA clicked' => 0,
            'Call button clicked' => 0,
            'Call started' => 0,
            'Form viewed' => 0,
            'Form started' => 0,
            'Form submitted' => 0,
            'Chat started' => 0,
            'Product viewed' => 0,
            'Add to cart' => 0,
            'Checkout started' => 0,
            'Payment started' => 0,
            'Appointment requested' => 0,
            'No action' => 0,
            'Exit' => 0,
        ];
        $outcomeBuckets = [
            'Lead confirmed' => 0,
            'Qualified lead' => 0,
            'Call connected' => 0,
            'Form completed' => 0,
            'Appointment booked' => 0,
            'Purchase completed' => 0,
            'Sale completed' => 0,
            'Follow-up required' => 0,
            'Awaiting outcome' => 0,
            'No answer' => 0,
            'Wrong number' => 0,
            'Unqualified lead' => 0,
            'Provider unavailable' => 0,
            'ZIP unserviceable' => 0,
            'Duplicate lead' => 0,
            'Spam' => 0,
            'Suspected fraud' => 0,
            'Blocked' => 0,
            'Exited' => 0,
        ];

        foreach ($recent as $s) {
            $hadMeaningful = false;
            $countedCta = false;
            $countedTel = false;
            foreach ($s['event_actions'] ?? [] as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $key = strtolower((string) ($ev['key'] ?? ''));
                // Session-level presence (Traffic Control emits one row per key).
                if (in_array($key, ['cta_click', 'tel_click', 'call_click'], true)) {
                    if (in_array($key, ['tel_click', 'call_click'], true)) {
                        $actionBuckets['Call button clicked']++;
                        $countedTel = true;
                    } else {
                        $actionBuckets['CTA clicked']++;
                        $countedCta = true;
                    }
                    $hadMeaningful = true;
                } elseif (in_array($key, ['form_start', 'form_fills', 'form_view'], true)) {
                    $actionBuckets[$key === 'form_view' ? 'Form viewed' : 'Form started']++;
                    $hadMeaningful = true;
                } elseif ($key === 'form_submit') {
                    $actionBuckets['Form submitted']++;
                    $hadMeaningful = true;
                } elseif (str_contains($key, 'chat')) {
                    $actionBuckets['Chat started']++;
                    $hadMeaningful = true;
                } elseif (str_contains($key, 'appointment')) {
                    $actionBuckets['Appointment requested']++;
                    $hadMeaningful = true;
                } elseif (in_array($key, ['add_to_cart', 'cart'], true)) {
                    $actionBuckets['Add to cart']++;
                    $hadMeaningful = true;
                } elseif (in_array($key, ['checkout', 'begin_checkout'], true)) {
                    $actionBuckets['Checkout started']++;
                    $hadMeaningful = true;
                } elseif (in_array($key, ['purchase', 'sale', 'payment'], true)) {
                    $actionBuckets['Payment started']++;
                    $hadMeaningful = true;
                } elseif (str_contains($key, 'pricing')) {
                    $actionBuckets['Pricing viewed']++;
                    $hadMeaningful = true;
                } elseif (str_contains($key, 'product')) {
                    $actionBuckets['Product viewed']++;
                    $hadMeaningful = true;
                } elseif (str_contains($key, 'zip')) {
                    $actionBuckets['ZIP checked']++;
                    $hadMeaningful = true;
                } elseif (str_contains($key, 'provider')) {
                    $actionBuckets['Provider selected']++;
                    $hadMeaningful = true;
                }
            }
            // Supplement CTA/tel from counters even when forms already marked the session meaningful
            // (event_actions used to omit cta_click when the recording column stayed at 0).
            $telOnly = (int) ($s['tel_clicks'] ?? 0);
            $ctaCombined = (int) ($s['cta_clicks'] ?? 0);
            // shapeSession stores cta_clicks as cta+tel; isolate pure CTA when possible.
            $ctaOnly = max(0, $ctaCombined - $telOnly);
            if (! $countedCta && $ctaOnly > 0) {
                $actionBuckets['CTA clicked']++;
                $hadMeaningful = true;
                $countedCta = true;
            }
            if (! $countedTel && $telOnly > 0) {
                $actionBuckets['Call button clicked']++;
                $hadMeaningful = true;
                $countedTel = true;
            }
            if (! $countedCta && ! $countedTel) {
                foreach ($s['timeline'] ?? [] as $tev) {
                    $tt = strtolower((string) ($tev['type'] ?? ''));
                    if (in_array($tt, ['cta', 'cta_click'], true)) {
                        $actionBuckets['CTA clicked']++;
                        $hadMeaningful = true;
                        break;
                    }
                    if (in_array($tt, ['tel', 'tel_click', 'phone', 'phone_click'], true)) {
                        $actionBuckets['Call button clicked']++;
                        $hadMeaningful = true;
                        break;
                    }
                }
            }
            if (! $hadMeaningful && (int) ($s['form_submits'] ?? 0) > 0) {
                $actionBuckets['Form submitted']++;
                $hadMeaningful = true;
            }
            if ((int) ($s['page_views'] ?? 0) > 0 || ! empty($s['path_chips'])) {
                $actionBuckets['Page viewed']++;
            }
            $tones = collect($s['path_chips'] ?? [])->pluck('tone')->all();
            if (! $hadMeaningful) {
                if (in_array('exit', $tones, true) && count($s['path_chips'] ?? []) <= 2) {
                    $actionBuckets['Exit']++;
                } else {
                    $actionBuckets['No action']++;
                }
            }

            $ot = $s['outcome']['tone'] ?? 'none';
            $olabel = strtolower((string) ($s['outcome']['label'] ?? ''));
            if ($ot === 'lead' || str_contains($olabel, 'lead confirmed')) {
                $outcomeBuckets['Lead confirmed']++;
            } elseif (str_contains($olabel, 'qualified')) {
                $outcomeBuckets['Qualified lead']++;
            } elseif (str_contains($olabel, 'call connected')) {
                $outcomeBuckets['Call connected']++;
            } elseif (str_contains($olabel, 'form completed') || str_contains($olabel, 'form submit')) {
                $outcomeBuckets['Form completed']++;
            } elseif (str_contains($olabel, 'appointment')) {
                $outcomeBuckets['Appointment booked']++;
            } elseif (str_contains($olabel, 'purchase')) {
                $outcomeBuckets['Purchase completed']++;
            } elseif (str_contains($olabel, 'sale')) {
                $outcomeBuckets['Sale completed']++;
            } elseif (str_contains($olabel, 'follow')) {
                $outcomeBuckets['Follow-up required']++;
            } elseif (str_contains($olabel, 'no answer')) {
                $outcomeBuckets['No answer']++;
            } elseif (str_contains($olabel, 'wrong number')) {
                $outcomeBuckets['Wrong number']++;
            } elseif (str_contains($olabel, 'unqualified')) {
                $outcomeBuckets['Unqualified lead']++;
            } elseif (str_contains($olabel, 'unavailable')) {
                $outcomeBuckets['Provider unavailable']++;
            } elseif (str_contains($olabel, 'unserviceable') || str_contains($olabel, 'zip')) {
                $outcomeBuckets['ZIP unserviceable']++;
            } elseif (str_contains($olabel, 'duplicate')) {
                $outcomeBuckets['Duplicate lead']++;
            } elseif (str_contains($olabel, 'spam')) {
                $outcomeBuckets['Spam']++;
            } elseif (str_contains($olabel, 'fraud')) {
                $outcomeBuckets['Suspected fraud']++;
            } elseif (str_contains($olabel, 'block')) {
                $outcomeBuckets['Blocked']++;
            } elseif ($ot === 'pending') {
                $outcomeBuckets['Awaiting outcome']++;
            } else {
                $outcomeBuckets['Exited']++;
            }
        }

        $scale = $tracked / max(1, count($recent));
        $actionTones = [
            'Page viewed' => 'default',
            'Pricing viewed' => 'action',
            'Provider selected' => 'action',
            'ZIP checked' => 'action',
            'CTA clicked' => 'action',
            'Call button clicked' => 'action',
            'Call started' => 'action',
            'Form viewed' => 'form',
            'Form started' => 'form',
            'Form submitted' => 'form',
            'Chat started' => 'action',
            'Product viewed' => 'action',
            'Add to cart' => 'action',
            'Checkout started' => 'action',
            'Payment started' => 'action',
            'Appointment requested' => 'action',
            'No action' => 'default',
            'Exit' => 'exit',
        ];
        $colAction = [];
        foreach ($actionBuckets as $label => $count) {
            $value = (int) round($count * $scale);
            $colAction[] = [
                'id' => 'a:'.md5($label),
                'label' => $label,
                'value' => $value,
                'pct' => round(($value / max(1, $tracked)) * 100, 1),
                'tone' => $actionTones[$label] ?? 'default',
            ];
        }
        // Sample can miss CTAs that exist in recordings / behavior events — lift from Page Analytics KPIs.
        $colAction = $this->liftActionNodesFromKpis($colAction, $current, $tracked);

        $outcomeTones = [
            'Lead confirmed' => 'lead',
            'Qualified lead' => 'lead',
            'Call connected' => 'lead',
            'Form completed' => 'lead',
            'Appointment booked' => 'lead',
            'Purchase completed' => 'lead',
            'Sale completed' => 'lead',
            'Follow-up required' => 'pending',
            'Awaiting outcome' => 'pending',
            'No answer' => 'exit',
            'Wrong number' => 'exit',
            'Unqualified lead' => 'exit',
            'Provider unavailable' => 'exit',
            'ZIP unserviceable' => 'exit',
            'Duplicate lead' => 'exit',
            'Spam' => 'exit',
            'Suspected fraud' => 'exit',
            'Blocked' => 'exit',
            'Exited' => 'exit',
        ];
        $colOutcome = [];
        foreach ($outcomeBuckets as $label => $count) {
            $value = (int) round($count * $scale);
            $colOutcome[] = [
                'id' => 'o:'.md5($label),
                'label' => $label,
                'value' => $value,
                'pct' => round(($value / max(1, $tracked)) * 100, 1),
                'tone' => $outcomeTones[$label] ?? 'default',
            ];
        }
        if (array_sum(array_column($colOutcome, 'value')) === 0) {
            $colOutcome[count($colOutcome) - 1]['value'] = $tracked;
            $colOutcome[count($colOutcome) - 1]['pct'] = 100.0;
        }

        $columns = [
            ['key' => 'landing', 'label' => 'Landing Page', 'nodes' => $colLanding],
            ['key' => 'next', 'label' => 'Next Page', 'nodes' => $colNext],
            ['key' => 'action', 'label' => 'Action', 'nodes' => $colAction],
            ['key' => 'outcome', 'label' => 'Outcome', 'nodes' => $colOutcome],
        ];

        $links = $this->syntheticLinks($columns);

        return ['columns' => $columns, 'links' => $links];
    }

    /**
     * Prefer real Page Analytics KPI totals when the session sample under-counts conversions.
     *
     * @param  list<array{id:string,label:string,value:int,pct:float,tone:string}>  $colAction
     * @param  array<string, mixed>  $current
     * @return list<array{id:string,label:string,value:int,pct:float,tone:string}>
     */
    private function liftActionNodesFromKpis(array $colAction, array $current, int $tracked): array
    {
        $kpis = is_array($current['kpis'] ?? null) ? $current['kpis'] : [];
        $floors = [
            'CTA clicked' => min($tracked, (int) ($kpis['cta_clicks'] ?? 0)),
            'Call button clicked' => min($tracked, (int) ($kpis['tel_clicks'] ?? 0)),
            'Form submitted' => min($tracked, (int) ($kpis['form_submits'] ?? 0)),
            'Payment started' => min($tracked, (int) ($kpis['purchases'] ?? 0)),
        ];

        // Pricing / product from known pages when event keys are not recorded yet.
        $pricingHits = 0;
        foreach (array_merge(
            $current['top_landing_pages'] ?? [],
            $current['top_exit_pages'] ?? [],
            $current['journey_paths'] ?? [],
        ) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $path = strtolower((string) ($row['path'] ?? $row['label'] ?? ''));
            if ($path !== '' && str_contains($path, 'pricing')) {
                $pricingHits += (int) ($row['value'] ?? 0);
            }
        }
        if ($pricingHits > 0) {
            $floors['Pricing viewed'] = min($tracked, max($floors['Pricing viewed'] ?? 0, $pricingHits));
        }

        $lifted = 0;
        foreach ($colAction as &$node) {
            $label = (string) ($node['label'] ?? '');
            if (! isset($floors[$label])) {
                continue;
            }
            $floor = (int) $floors[$label];
            if ($floor <= (int) ($node['value'] ?? 0)) {
                continue;
            }
            $lifted += $floor - (int) $node['value'];
            $node['value'] = $floor;
            $node['pct'] = round(($floor / max(1, $tracked)) * 100, 1);
        }
        unset($node);

        if ($lifted <= 0) {
            return $colAction;
        }

        // Pull excess away from Exit / No action so the column stays ~session-normalized.
        foreach (['Exit', 'No action', 'Page viewed'] as $drain) {
            if ($lifted <= 0) {
                break;
            }
            foreach ($colAction as &$node) {
                if ($lifted <= 0) {
                    break;
                }
                if ((string) ($node['label'] ?? '') !== $drain) {
                    continue;
                }
                $take = min($lifted, (int) ($node['value'] ?? 0));
                if ($take <= 0) {
                    continue;
                }
                $node['value'] = (int) $node['value'] - $take;
                $node['pct'] = round(((int) $node['value'] / max(1, $tracked)) * 100, 1);
                $lifted -= $take;
            }
            unset($node);
        }

        return $colAction;
    }

    /**
     * @param  list<array{nodes:list<array{id:string,value:int}>}>  $columns
     * @return list<array{source:string,target:string,value:int}>
     */
    private function syntheticLinks(array $columns): array
    {
        $links = [];
        for ($c = 0; $c < count($columns) - 1; $c++) {
            $left = $columns[$c]['nodes'] ?? [];
            $right = $columns[$c + 1]['nodes'] ?? [];
            if ($left === [] || $right === []) {
                continue;
            }
            foreach ($left as $li => $src) {
                $remaining = (int) ($src['value'] ?? 0);
                foreach ($right as $ri => $tgt) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $share = (int) max(1, round(($src['value'] ?? 0) * (($tgt['value'] ?? 1) / max(1, array_sum(array_column($right, 'value'))))));
                    $share = min($share, $remaining);
                    $links[] = [
                        'source' => $src['id'],
                        'target' => $tgt['id'],
                        'value' => $share,
                    ];
                    $remaining -= $share;
                }
            }
        }

        return $links;
    }

    /**
     * @param  list<array<string, mixed>>  $recent
     * @return array{total:int,slices:list<array<string,mixed>>}
     */
    private function buildOutcomes(array $recent, int $tracked, float $leadRate, int $bounced): array
    {
        $lead = 0;
        $pending = 0;
        $none = 0;
        foreach ($recent as $s) {
            $tone = $s['outcome']['tone'] ?? 'none';
            if ($tone === 'lead') {
                $lead++;
            } elseif ($tone === 'pending') {
                $pending++;
            } else {
                $none++;
            }
        }
        $n = max(1, count($recent));
        $leadN = max(0, (int) round($tracked * ($leadRate / 100)));
        if ($leadN === 0 && $lead > 0) {
            $leadN = (int) round(($lead / $n) * $tracked);
        }
        $pendingN = (int) round(($pending / $n) * $tracked);
        $noneN = max(0, $tracked - $leadN - $pendingN);
        if ($noneN === 0 && $bounced > 0) {
            $noneN = $bounced;
        }

        return [
            'total' => $tracked,
            'slices' => [
                ['key' => 'lead', 'label' => 'Confirmed leads', 'value' => $leadN, 'color' => '#22C55E'],
                ['key' => 'pending', 'label' => 'Pending', 'value' => $pendingN, 'color' => '#EAB308'],
                ['key' => 'none', 'label' => 'No conversion', 'value' => $noneN, 'color' => '#EF4444'],
            ],
        ];
    }

    /** @return list<string> */
    private function splitPath(string $path): array
    {
        $parts = preg_split('/\s*→\s*|\s*->\s*/u', $path) ?: [];

        return array_values(array_filter(array_map(fn ($p) => $this->shortPath(trim((string) $p)), $parts)));
    }

    private function shortPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '—') {
            return '/';
        }
        if (str_starts_with($path, 'http')) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) && $parsed !== '' ? $parsed : '/';
        }
        if ($path === '') {
            return '/';
        }

        return strlen($path) > 28 ? substr($path, 0, 26).'…' : $path;
    }

    private function shortId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '—';
        }
        if (strlen($id) <= 16) {
            return $id;
        }

        return substr($id, 0, 8).'…'.substr($id, -4);
    }

    private function pctDelta(int|float $cur, int|float $prev): float
    {
        $cur = (float) $cur;
        $prev = (float) $prev;
        if ($prev == 0.0) {
            return $cur > 0 ? 100.0 : 0.0;
        }

        return round((($cur - $prev) / $prev) * 100, 1);
    }

    private function durationToSeconds(string $label): int
    {
        $parts = array_map('intval', explode(':', $label));
        if (count($parts) === 3) {
            return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        }
        if (count($parts) === 2) {
            return $parts[0] * 60 + $parts[1];
        }

        return 0;
    }

    private function friendlyDuration(string $hms): string
    {
        $sec = $this->durationToSeconds($hms);
        $m = intdiv($sec, 60);
        $s = $sec % 60;
        if ($m >= 60) {
            $h = intdiv($m, 60);
            $m = $m % 60;

            return sprintf('%dh %dm', $h, $m);
        }

        return sprintf('%dm %02ds', $m, $s);
    }

    private function parseClock(string $clock): int
    {
        $parts = array_map('intval', explode(':', $clock));
        $h = $parts[0] ?? 0;
        $m = $parts[1] ?? 0;
        $s = $parts[2] ?? 0;

        return $h * 3600 + $m * 60 + $s;
    }

    private function formatClock(int $sec): string
    {
        $sec = max(0, $sec);
        $h = intdiv($sec, 3600) % 24;
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /** @return list<int> */
    private function spark(int $seed): array
    {
        $base = max(2, $seed % 17);
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $out[] = max(1, $base + (int) round(sin(($seed + $i) * 0.7) * 4) + $i);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function emptyPayload(): array
    {
        return [
            'kpis' => [
                ['key' => 'tracked_sessions', 'label' => 'Tracked Sessions', 'value' => 0, 'display' => '0', 'delta' => 0, 'vs_label' => 'vs previous period', 'tone' => 'orange', 'spark' => [2, 3, 2, 4, 3, 5, 4]],
                ['key' => 'avg_duration', 'label' => 'Avg. Session Duration', 'value' => 0, 'display' => '00:00:00', 'delta' => 0, 'vs_label' => 'vs previous period', 'tone' => 'orange', 'spark' => [1, 2, 2, 3, 2, 3, 4]],
                ['key' => 'pages_per_session', 'label' => 'Pages per Session', 'value' => 0, 'display' => '0.00', 'delta' => 0, 'vs_label' => 'vs previous period', 'tone' => 'orange', 'spark' => [1, 1, 2, 2, 3, 3, 2]],
                ['key' => 'lead_conversion', 'label' => 'Lead Conversion Rate', 'value' => 0, 'display' => '0.0%', 'delta' => 0, 'vs_label' => 'vs previous period', 'tone' => 'orange', 'spark' => [1, 2, 1, 2, 3, 2, 3]],
                ['key' => 'single_page', 'label' => 'Single-Page Sessions', 'value' => 0, 'display' => '0.0%', 'delta' => 0, 'vs_label' => 'vs previous period', 'tone' => 'orange', 'spark' => [3, 2, 3, 2, 1, 2, 1], 'delta_bad_when_up' => true],
                ['key' => 'engaged_sessions', 'label' => 'Engaged Sessions', 'value' => 0, 'display' => '0', 'delta' => 0, 'vs_label' => 'vs previous period', 'tone' => 'orange', 'spark' => [2, 3, 4, 3, 5, 4, 6]],
            ],
            'flow' => ['columns' => [], 'links' => []],
            'common_paths' => [],
            'landing_pages' => [],
            'exit_pages' => [],
            'outcomes' => [
                'total' => 0,
                'slices' => [
                    ['key' => 'lead', 'label' => 'Confirmed leads', 'value' => 0, 'color' => '#22C55E'],
                    ['key' => 'pending', 'label' => 'Pending', 'value' => 0, 'color' => '#EAB308'],
                    ['key' => 'none', 'label' => 'No conversion', 'value' => 0, 'color' => '#EF4444'],
                ],
            ],
            'sessions' => [],
            'selected' => null,
            'timeline' => [],
            'meta' => ['session_total' => 0, 'tracked' => 0, 'campaigns' => [], 'days' => 0],
        ];
    }
}
