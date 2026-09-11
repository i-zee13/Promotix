<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics → Visitor Journey aggregates (Traffic Control–style payload).
 */
class VisitorJourneyIntelligence
{
    /**
     * @param  list<int>  $domainIds
     * @param  array{campaign?:string,device?:string,path?:string,q?:string,sample?:bool}  $filters
     * @return array<string, mixed>
     */
    public function build(array $domainIds, Carbon $from, Carbon $to, Request $request, array $filters = []): array
    {
        if (! empty($filters['sample'])) {
            return $this->samplePayload();
        }

        if ($domainIds === [] || ! Schema::hasTable('visits')) {
            return $this->emptyPayload();
        }

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

        $current = app(PageAnalyticsAggregator::class)->build($domainIds, $from, $to, null, $analyticsFilters);
        $previous = app(PageAnalyticsAggregator::class)->build($domainIds, $prevFrom, $prevTo, null, $analyticsFilters);

        $sessionPage = app(TrafficControlSessionQuery::class)->paginate(
            $domainIds,
            $from,
            $to,
            $request,
            1,
            40,
        );

        $sessions = $sessionPage['data'] ?? [];
        $sessionTotal = (int) ($sessionPage['total'] ?? count($sessions));

        $tracked = max(
            (int) ($current['journey_summary']['sessions'] ?? 0),
            $sessionTotal,
            1,
        );

        $prevTracked = max(1, (int) ($previous['journey_summary']['sessions'] ?? 0));

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

        $prevLead = (float) ($previous['conversion_summary']['rate_raw']
            ?? $previous['kpis']['conversion_rate']
            ?? 0);
        $prevPages = (float) ($previous['pages_per_session'] ?? 0);
        $prevEngagement = collect($previous['engagement'] ?? []);
        $prevBounced = (int) ($prevEngagement->firstWhere('key', 'bounced')['value'] ?? 0);
        $prevEngaged = (int) ($prevEngagement->firstWhere('key', 'engaged')['value'] ?? 0);
        $prevHigh = (int) ($prevEngagement->firstWhere('key', 'highly_engaged')['value'] ?? 0);
        $prevEngagedSessions = $prevEngaged + $prevHigh;
        $prevSingle = round(($prevBounced / max(1, $prevBounced + $prevEngaged + $prevHigh)) * 100, 1);

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
                'delta' => $this->pctDelta(
                    $this->durationToSeconds($avgDurationLabel),
                    $this->durationToSeconds((string) ($previous['journey_summary']['avg_session_duration'] ?? '00:00:00')),
                ),
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

        $recent = array_map(fn (array $row) => $this->shapeSession($row), array_slice($sessions, 0, 12));
        $flow = $this->buildFlow($current, $recent, $tracked);
        $outcomes = $this->buildOutcomes($recent, $tracked, $leadRate, $bounced);

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
            if (in_array($key, ['cta_click', 'tel_click'], true)) {
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

        $durationLabel = $this->friendlyDuration((string) ($row['time_on_site'] ?? '00:00:00'));
        $sessionId = (string) ($row['session_id'] ?? $row['session_key'] ?? '');
        $deviceId = (string) ($row['fingerprint_id'] ?? '');
        if ($deviceId === '') {
            $deviceId = 'dev_'.substr(sha1($sessionId !== '' ? $sessionId : (string) ($row['ip'] ?? 'x')), 0, 8);
        } elseif (! str_starts_with($deviceId, 'dev_')) {
            $deviceId = 'dev_'.substr($deviceId, 0, 10);
        }
        if ($sessionId !== '' && ! str_starts_with($sessionId, 'ses_') && strlen($sessionId) > 12) {
            $sessionId = 'ses_'.substr(sha1($sessionId), 0, 6);
        } elseif ($sessionId === '') {
            $sessionId = 'ses_'.substr(sha1((string) ($row['ip'] ?? 'x')), 0, 6);
        }

        return [
            'session_id' => $sessionId,
            'session_key' => (string) ($row['session_key'] ?? $sessionId),
            'device_id' => $deviceId,
            'device' => (string) ($row['device'] ?? '—'),
            'browser' => (string) ($row['browser'] ?? '—'),
            'os' => (string) ($row['os'] ?? '—'),
            'status' => 'Ended',
            'campaign' => (string) ($row['campaign'] ?? $row['source_platform'] ?? '—'),
            'source' => (string) ($row['source_platform'] ?? 'Google Ads'),
            'duration' => $durationLabel,
            'duration_raw' => (string) ($row['time_on_site'] ?? '00:00:00'),
            'path_chips' => $pathChips,
            'path_footer' => array_values(array_merge(
                array_map(fn ($p) => ['label' => $p, 'tone' => 'page'], array_slice($pages, 0, 3)),
                [['label' => 'Exit', 'tone' => 'exit']],
            )),
            'outcome' => $outcome,
            'landing_page' => $this->shortPath((string) ($row['landing_page'] ?? '/')),
            'exit_page' => $this->shortPath((string) ($row['exit_page'] ?? '—')),
            'page_views' => (int) ($row['page_views'] ?? max(1, count($pages))),
            'cta_clicks' => (int) ($row['cta_clicks'] ?? 0) + (int) ($row['tel_clicks'] ?? 0),
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

        if ($flat !== []) {
            usort($flat, static function ($a, $b): int {
                $ta = (int) ($a['t'] ?? $a['elapsed_sec'] ?? 0);
                $tb = (int) ($b['t'] ?? $b['elapsed_sec'] ?? 0);
                if ($ta === $tb) {
                    return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
                }

                return $ta <=> $tb;
            });

            $out = [];
            $entryClock = (string) ($row['entry_clock'] ?? '10:24:00');
            $base = $this->parseClock($entryClock);
            foreach (array_slice($flat, 0, 20) as $i => $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $type = $this->normalizeEventType((string) ($ev['type'] ?? $ev['kind'] ?? $ev['label'] ?? ''));
                $elapsedMs = (int) ($ev['t'] ?? 0);
                $elapsed = (int) ($ev['elapsed_sec'] ?? ($elapsedMs > 1000 ? (int) floor($elapsedMs / 1000) : $elapsedMs));
                if ($elapsed <= 0 && isset($ev['at'])) {
                    $elapsed = $i * 18;
                }
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
                $hasExit = collect($out)->contains(fn ($e) => ($e['type'] ?? '') === 'exit');
                if (! $hasExit) {
                    $lastElapsed = (int) ($out[count($out) - 1]['elapsed_sec'] ?? 0) + 20;
                    $out[] = $this->timelineEvent([
                        'type' => 'exit',
                        'label' => 'Exit',
                        'event' => 'session_end',
                        'kind' => 'Exit',
                        'time' => $this->formatClock($base + $lastElapsed),
                        'elapsed_sec' => $lastElapsed,
                        'page' => $pages[count($pages) - 1] ?? ($out[count($out) - 1]['page'] ?? '/'),
                        'note' => '',
                        'status' => 'Session ended',
                    ]);
                }

                return $out;
            }
        }

        $entryClock = (string) ($row['entry_clock'] ?? '10:24:00');
        $base = $this->parseClock($entryClock);
        $out = [];
        $elapsed = 0;
        foreach (array_slice($pages, 0, 4) as $i => $page) {
            $elapsed = $i === 0 ? 0 : $elapsed + 18;
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
            if ($i === 0 && (int) ($row['scroll_events'] ?? 0) > 0) {
                $elapsed += 8;
                $out[] = $this->timelineEvent([
                    'type' => 'scroll',
                    'label' => 'Scroll',
                    'event' => 'scroll',
                    'kind' => 'Scroll',
                    'time' => $this->formatClock($base + $elapsed),
                    'elapsed_sec' => $elapsed,
                    'page' => $page,
                    'note' => '',
                    'status' => 'Scroll recorded',
                ]);
            }
        }
        if ((int) ($row['form_starts'] ?? 0) > 0 || (int) ($row['form_submits'] ?? 0) > 0) {
            $elapsed += 18;
            $page = $pages[min(1, max(0, count($pages) - 1))] ?? '/';
            $out[] = $this->timelineEvent([
                'type' => 'form',
                'label' => 'availability check',
                'event' => 'availability_check',
                'kind' => 'Form submit',
                'time' => $this->formatClock($base + $elapsed),
                'elapsed_sec' => $elapsed,
                'page' => $page,
                'note' => '',
                'status' => 'Submitted',
            ]);
        }
        if ((int) ($row['cta_clicks'] ?? 0) > 0 || (int) ($row['tel_clicks'] ?? 0) > 0) {
            $elapsed += 16;
            $page = $pages[min(1, max(0, count($pages) - 1))] ?? '/';
            $out[] = $this->timelineEvent([
                'type' => 'cta',
                'label' => 'Call click',
                'event' => 'call_button_click',
                'kind' => 'CTA click',
                'time' => $this->formatClock($base + $elapsed),
                'elapsed_sec' => $elapsed,
                'page' => $page,
                'note' => 'Call outcome unavailable.',
                'status' => 'Click recorded',
            ]);
        }
        $dur = $this->durationToSeconds((string) ($row['time_on_site'] ?? '00:00:00'));
        $elapsed = max($elapsed + 20, $dur > 0 ? $dur : $elapsed + 20);
        $out[] = $this->timelineEvent([
            'type' => 'exit',
            'label' => 'Exit',
            'event' => 'session_end',
            'kind' => 'Exit',
            'time' => $this->formatClock($base + $elapsed),
            'elapsed_sec' => $elapsed,
            'page' => $pages[count($pages) - 1] ?? '/',
            'note' => '',
            'status' => 'Session ended',
        ]);

        return $out;
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
        $landing = collect($current['top_landing_pages'] ?? [])->take(3)->values();
        if ($landing->isEmpty()) {
            $landing = collect($recent)
                ->groupBy('landing_page')
                ->map(fn ($rows, $path) => ['label' => $path, 'value' => $rows->count()])
                ->sortByDesc('value')
                ->take(3)
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
            if ($i >= 3) {
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

        $call = 0;
        $form = 0;
        $none = 0;
        $exitAction = 0;
        $lead = 0;
        $pending = 0;
        $noConv = 0;
        foreach ($recent as $s) {
            $tones = collect($s['path_chips'] ?? [])->pluck('tone')->all();
            if (in_array('action', $tones, true)) {
                $call++;
            } elseif (in_array('form', $tones, true)) {
                $form++;
            } elseif (in_array('exit', $tones, true) && count($s['path_chips'] ?? []) <= 2) {
                $exitAction++;
            } else {
                $none++;
            }
            $ot = $s['outcome']['tone'] ?? 'none';
            if ($ot === 'lead') {
                $lead++;
            } elseif ($ot === 'pending') {
                $pending++;
            } else {
                $noConv++;
            }
        }
        $scale = $tracked / max(1, count($recent));
        $colAction = [
            ['id' => 'a:call', 'label' => 'Call button click', 'value' => max(1, (int) round($call * $scale)), 'pct' => 0, 'tone' => 'action'],
            ['id' => 'a:form', 'label' => 'Form started', 'value' => max(1, (int) round($form * $scale)), 'pct' => 0, 'tone' => 'form'],
            ['id' => 'a:none', 'label' => 'No action', 'value' => max(1, (int) round($none * $scale)), 'pct' => 0, 'tone' => 'default'],
            ['id' => 'a:exit', 'label' => 'Exit', 'value' => max(1, (int) round($exitAction * $scale)), 'pct' => 0, 'tone' => 'exit'],
        ];
        $actionSum = max(1, array_sum(array_column($colAction, 'value')));
        foreach ($colAction as &$n) {
            $n['pct'] = round(($n['value'] / max(1, $tracked)) * 100, 1);
        }
        unset($n);

        $colOutcome = [
            ['id' => 'o:lead', 'label' => 'Lead confirmed', 'value' => max(0, (int) round($lead * $scale)), 'pct' => 0, 'tone' => 'lead'],
            ['id' => 'o:wait', 'label' => 'Awaiting outcome', 'value' => max(0, (int) round($pending * $scale)), 'pct' => 0, 'tone' => 'pending'],
            ['id' => 'o:exit', 'label' => 'Exit', 'value' => max(0, (int) round($noConv * $scale)), 'pct' => 0, 'tone' => 'exit'],
        ];
        if (array_sum(array_column($colOutcome, 'value')) === 0) {
            $colOutcome[2]['value'] = $tracked;
        }
        foreach ($colOutcome as &$n) {
            $n['pct'] = round(($n['value'] / max(1, $tracked)) * 100, 1);
        }
        unset($n);

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

    /** @return array<string, mixed> */
    private function samplePayload(): array
    {
        $mk = function (array $d): array {
            return $this->timelineEvent($d);
        };

        $sessions = [
            [
                'session_id' => 'ses_42a8',
                'session_key' => 'ses_42a8',
                'device_id' => 'dev_7c9b1a2f',
                'device' => 'Mobile',
                'status' => 'Ended',
                'campaign' => 'Frontier Search',
                'duration' => '2m 11s',
                'path_chips' => [
                    ['label' => '/frontier', 'tone' => 'page'],
                    ['label' => '/plans', 'tone' => 'page'],
                    ['label' => 'call button click', 'tone' => 'action'],
                ],
                'outcome' => ['label' => 'Pending', 'tone' => 'pending'],
                'timeline' => [
                    $mk(['type' => 'page', 'label' => '/frontier', 'event' => '/frontier', 'kind' => 'Page view', 'time' => '10:24:00', 'elapsed_sec' => 0, 'page' => '/frontier', 'status' => 'Page viewed']),
                    $mk(['type' => 'page', 'label' => '/plans', 'event' => '/plans', 'kind' => 'Page view', 'time' => '10:24:18', 'elapsed_sec' => 18, 'page' => '/plans', 'status' => 'Page viewed']),
                    $mk(['type' => 'scroll', 'label' => 'Scroll', 'event' => 'scroll', 'kind' => 'Scroll', 'time' => '10:24:28', 'elapsed_sec' => 28, 'page' => '/plans', 'status' => 'Scroll recorded']),
                    $mk(['type' => 'form', 'label' => 'availability check', 'event' => 'availability_check', 'kind' => 'Form submit', 'time' => '10:24:36', 'elapsed_sec' => 36, 'page' => '/plans', 'status' => 'Submitted']),
                    $mk(['type' => 'cta', 'label' => 'Call click', 'event' => 'call_button_click', 'kind' => 'CTA click', 'time' => '10:24:52', 'elapsed_sec' => 52, 'page' => '/plans', 'note' => 'Call outcome unavailable.', 'status' => 'Click recorded']),
                    $mk(['type' => 'exit', 'label' => 'Exit', 'event' => 'session_end', 'kind' => 'Exit', 'time' => '10:26:11', 'elapsed_sec' => 131, 'page' => '/plans', 'status' => 'Session ended']),
                ],
            ],
            [
                'session_id' => 'ses_b4d3',
                'session_key' => 'ses_b4d3',
                'device_id' => 'dev_3f8e2b1c',
                'device' => 'Desktop',
                'status' => 'Ended',
                'campaign' => 'Verizon Brand',
                'duration' => '1m 48s',
                'path_chips' => [
                    ['label' => '/verizon', 'tone' => 'page'],
                    ['label' => '/availability', 'tone' => 'page'],
                    ['label' => 'form submit', 'tone' => 'form'],
                ],
                'outcome' => ['label' => 'Lead confirmed', 'tone' => 'lead'],
                'timeline' => [
                    $mk(['type' => 'page', 'label' => '/verizon', 'event' => '/verizon', 'kind' => 'Page view', 'time' => '11:02:00', 'elapsed_sec' => 0, 'page' => '/verizon', 'status' => 'Page viewed']),
                    $mk(['type' => 'page', 'label' => '/availability', 'event' => '/availability', 'kind' => 'Page view', 'time' => '11:02:40', 'elapsed_sec' => 40, 'page' => '/availability', 'status' => 'Page viewed']),
                    $mk(['type' => 'scroll', 'label' => 'Scroll', 'event' => 'scroll', 'kind' => 'Scroll', 'time' => '11:03:05', 'elapsed_sec' => 65, 'page' => '/availability', 'status' => 'Scroll recorded']),
                    $mk(['type' => 'form', 'label' => 'form_submit', 'event' => 'form_submit', 'kind' => 'Form submit', 'time' => '11:03:22', 'elapsed_sec' => 82, 'page' => '/availability', 'status' => 'Submitted']),
                    $mk(['type' => 'exit', 'label' => 'Exit', 'event' => 'session_end', 'kind' => 'Exit', 'time' => '11:03:48', 'elapsed_sec' => 108, 'page' => '/availability', 'status' => 'Session ended']),
                ],
            ],
            [
                'session_id' => 'ses_c9e1',
                'session_key' => 'ses_c9e1',
                'device_id' => 'dev_9a0b4d2e',
                'device' => 'Mobile',
                'status' => 'Ended',
                'campaign' => 'Kinetic Display',
                'duration' => '0m 48s',
                'path_chips' => [
                    ['label' => '/kinetic', 'tone' => 'page'],
                    ['label' => 'exit', 'tone' => 'exit'],
                ],
                'outcome' => ['label' => 'No conversion', 'tone' => 'none'],
                'timeline' => [
                    $mk(['type' => 'page', 'label' => '/kinetic', 'event' => '/kinetic', 'kind' => 'Page view', 'time' => '09:15:00', 'elapsed_sec' => 0, 'page' => '/kinetic', 'status' => 'Page viewed']),
                    $mk(['type' => 'scroll', 'label' => 'Scroll', 'event' => 'scroll', 'kind' => 'Scroll', 'time' => '09:15:12', 'elapsed_sec' => 12, 'page' => '/kinetic', 'status' => 'Scroll recorded']),
                    $mk(['type' => 'exit', 'label' => 'Exit', 'event' => 'session_end', 'kind' => 'Exit', 'time' => '09:15:48', 'elapsed_sec' => 48, 'page' => '/kinetic', 'status' => 'Session ended']),
                ],
            ],
            [
                'session_id' => 'ses_d7f2',
                'session_key' => 'ses_d7f2',
                'device_id' => 'dev_1c4a88e0',
                'device' => 'Desktop',
                'status' => 'Ended',
                'campaign' => 'Frontier Search',
                'duration' => '2m 05s',
                'path_chips' => [
                    ['label' => '/frontier', 'tone' => 'page'],
                    ['label' => '/contact', 'tone' => 'page'],
                    ['label' => 'call button click', 'tone' => 'action'],
                ],
                'outcome' => ['label' => 'Pending', 'tone' => 'pending'],
                'timeline' => [
                    $mk(['type' => 'page', 'label' => '/frontier', 'event' => '/frontier', 'kind' => 'Page view', 'time' => '12:10:00', 'elapsed_sec' => 0, 'page' => '/frontier', 'status' => 'Page viewed']),
                    $mk(['type' => 'page', 'label' => '/contact', 'event' => '/contact', 'kind' => 'Page view', 'time' => '12:10:35', 'elapsed_sec' => 35, 'page' => '/contact', 'status' => 'Page viewed']),
                    $mk(['type' => 'cta', 'label' => 'Call click', 'event' => 'call_button_click', 'kind' => 'CTA click', 'time' => '12:11:10', 'elapsed_sec' => 70, 'page' => '/contact', 'note' => 'Call outcome unavailable.', 'status' => 'Click recorded']),
                    $mk(['type' => 'exit', 'label' => 'Exit', 'event' => 'session_end', 'kind' => 'Exit', 'time' => '12:12:05', 'elapsed_sec' => 125, 'page' => '/contact', 'status' => 'Session ended']),
                ],
            ],
        ];

        $sessions = array_map(function (array $s): array {
            $pages = collect($s['path_chips'] ?? [])->where('tone', 'page')->pluck('label')->values()->all();
            $timeline = $s['timeline'] ?? [];
            $cta = collect($timeline)->where('type', 'cta')->count();
            $forms = collect($timeline)->where('type', 'form')->count();
            $last = $timeline[count($timeline) - 1] ?? null;
            $first = $timeline[0] ?? null;

            return array_merge([
                'browser' => 'Chrome',
                'os' => ($s['device'] ?? '') === 'Desktop' ? 'Windows' : 'Android',
                'source' => 'Google Ads',
                'landing_page' => $pages[0] ?? '/',
                'exit_page' => $pages[count($pages) - 1] ?? '/',
                'page_views' => max(1, count($pages)),
                'cta_clicks' => $cta,
                'form_submits' => $forms,
                'gclid_captured' => true,
                'start_time' => (string) ($first['time'] ?? '10:24:00'),
                'start_label' => $this->ampmFromClock((string) ($first['time'] ?? '10:24:00')),
                'last_event_time' => (string) ($last['time'] ?? ''),
                'path_footer' => array_values(array_merge(
                    array_map(fn ($p) => ['label' => $p, 'tone' => 'page'], $pages),
                    [['label' => 'Exit', 'tone' => 'exit']],
                )),
                'alert' => $this->sessionAlert($timeline),
            ], $s);
        }, $sessions);

        $flow = [
            'columns' => [
                [
                    'key' => 'landing',
                    'label' => 'Landing Page',
                    'nodes' => [
                        ['id' => 'l:/frontier', 'label' => '/frontier', 'value' => 420, 'pct' => 42.0, 'tone' => 'default'],
                        ['id' => 'l:/verizon', 'label' => '/verizon', 'value' => 350, 'pct' => 35.0, 'tone' => 'default'],
                        ['id' => 'l:/kinetic', 'label' => '/kinetic', 'value' => 230, 'pct' => 23.0, 'tone' => 'default'],
                    ],
                ],
                [
                    'key' => 'next',
                    'label' => 'Next Page',
                    'nodes' => [
                        ['id' => 'n:/plans', 'label' => '/plans', 'value' => 380, 'pct' => 38.0, 'tone' => 'default'],
                        ['id' => 'n:/availability', 'label' => '/availability', 'value' => 300, 'pct' => 30.0, 'tone' => 'default'],
                        ['id' => 'n:/contact', 'label' => '/contact', 'value' => 240, 'pct' => 24.0, 'tone' => 'default'],
                        ['id' => 'n:Exit', 'label' => 'Exit', 'value' => 80, 'pct' => 8.0, 'tone' => 'exit'],
                    ],
                ],
                [
                    'key' => 'action',
                    'label' => 'Action',
                    'nodes' => [
                        ['id' => 'a:call', 'label' => 'Call button click', 'value' => 280, 'pct' => 28.0, 'tone' => 'action'],
                        ['id' => 'a:form', 'label' => 'Form started', 'value' => 240, 'pct' => 24.0, 'tone' => 'form'],
                        ['id' => 'a:none', 'label' => 'No action', 'value' => 320, 'pct' => 32.0, 'tone' => 'default'],
                        ['id' => 'a:exit', 'label' => 'Exit', 'value' => 160, 'pct' => 16.0, 'tone' => 'exit'],
                    ],
                ],
                [
                    'key' => 'outcome',
                    'label' => 'Outcome',
                    'nodes' => [
                        ['id' => 'o:lead', 'label' => 'Lead confirmed', 'value' => 80, 'pct' => 8.0, 'tone' => 'lead'],
                        ['id' => 'o:wait', 'label' => 'Awaiting outcome', 'value' => 140, 'pct' => 14.0, 'tone' => 'pending'],
                        ['id' => 'o:exit', 'label' => 'Exit', 'value' => 780, 'pct' => 78.0, 'tone' => 'exit'],
                    ],
                ],
            ],
            'links' => [],
        ];
        $flow['links'] = $this->syntheticLinks($flow['columns']);

        return [
            'kpis' => [
                ['key' => 'tracked_sessions', 'label' => 'Tracked Sessions', 'value' => 1000, 'display' => '1,000', 'delta' => 12.4, 'vs_label' => 'vs previous 30 days', 'tone' => 'orange', 'spark' => [4, 5, 4, 6, 7, 6, 8]],
                ['key' => 'avg_duration', 'label' => 'Avg. Session Duration', 'value' => 138, 'display' => '00:02:18', 'delta' => 8.7, 'vs_label' => 'vs previous 30 days', 'tone' => 'orange', 'spark' => [3, 4, 5, 4, 6, 5, 7]],
                ['key' => 'pages_per_session', 'label' => 'Pages per Session', 'value' => 2.64, 'display' => '2.64', 'delta' => 6.1, 'vs_label' => 'vs previous 30 days', 'tone' => 'orange', 'spark' => [2, 3, 3, 4, 3, 5, 4]],
                ['key' => 'lead_conversion', 'label' => 'Lead Conversion Rate', 'value' => 8.0, 'display' => '8.0%', 'delta' => 2.8, 'vs_label' => 'vs previous 30 days', 'tone' => 'orange', 'spark' => [2, 2, 3, 3, 4, 3, 5]],
                ['key' => 'single_page', 'label' => 'Single-Page Sessions', 'value' => 38.0, 'display' => '38.0%', 'delta' => -4.1, 'vs_label' => 'vs previous 30 days', 'tone' => 'orange', 'spark' => [6, 5, 5, 4, 4, 3, 3], 'delta_bad_when_up' => true],
                ['key' => 'engaged_sessions', 'label' => 'Engaged Sessions', 'value' => 620, 'display' => '620', 'delta' => 15.6, 'vs_label' => 'vs previous 30 days', 'tone' => 'orange', 'spark' => [3, 4, 5, 6, 5, 7, 8]],
            ],
            'flow' => $flow,
            'common_paths' => [
                ['rank' => 1, 'path' => '/frontier → /plans → call button click', 'steps' => ['/frontier', '/plans', 'call button click'], 'value' => 280, 'pct' => 28.0],
                ['rank' => 2, 'path' => '/verizon → /availability → form started', 'steps' => ['/verizon', '/availability', 'form started'], 'value' => 190, 'pct' => 19.0],
                ['rank' => 3, 'path' => '/kinetic → /contact → exit', 'steps' => ['/kinetic', '/contact', 'exit'], 'value' => 120, 'pct' => 12.0],
                ['rank' => 4, 'path' => '/frontier → exit', 'steps' => ['/frontier', 'exit'], 'value' => 80, 'pct' => 8.0],
            ],
            'landing_pages' => [
                ['label' => '/frontier', 'value' => 420, 'pct' => 42.0],
                ['label' => '/verizon', 'value' => 350, 'pct' => 35.0],
                ['label' => '/kinetic', 'value' => 230, 'pct' => 23.0],
            ],
            'exit_pages' => [
                ['label' => '/plans', 'value' => 310, 'pct' => 31.0],
                ['label' => '/availability', 'value' => 260, 'pct' => 26.0],
                ['label' => '/frontier', 'value' => 180, 'pct' => 18.0],
            ],
            'outcomes' => [
                'total' => 1000,
                'slices' => [
                    ['key' => 'lead', 'label' => 'Confirmed leads', 'value' => 80, 'color' => '#22C55E'],
                    ['key' => 'pending', 'label' => 'Pending', 'value' => 140, 'color' => '#EAB308'],
                    ['key' => 'none', 'label' => 'No conversion', 'value' => 780, 'color' => '#EF4444'],
                ],
            ],
            'sessions' => $sessions,
            'selected' => $sessions[0],
            'timeline' => $sessions[0]['timeline'],
            'meta' => ['session_total' => 1000, 'tracked' => 1000, 'campaigns' => ['Frontier Search', 'Verizon Brand', 'Kinetic Display'], 'days' => 30],
        ];
    }
}
