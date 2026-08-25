<?php

namespace App\Support;

use App\Models\Domain;
use App\Support\GoogleClickAttribution;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrafficControlSessionQuery
{
    /**
     * @param  list<int>  $domainIds
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $domainIds, Carbon $from, Carbon $to, Request $request, int $page, int $perPage): array
    {
        if (! Schema::hasTable('visits') || $domainIds === []) {
            return ['data' => [], 'total' => 0];
        }

        $sessionExpr = Schema::hasColumn('visits', 'session_id')
            ? "COALESCE(NULLIF(visits.session_id, ''), CONCAT('ip:', visits.ip))"
            : "CONCAT('ip:', visits.ip)";

        $base = DB::table('visits')
            ->leftJoin('domains', 'domains.id', '=', 'visits.domain_id')
            ->whereIn('visits.domain_id', $domainIds)
            ->whereBetween('visits.visited_at', [$from, $to]);

        GoogleClickAttribution::excludeClickIdsForPaidDomains(
            $base,
            $this->paidMarketingDomainIds($domainIds),
            'visits',
        );

        $this->applyFilters($base, $request);

        $total = (int) (clone $base)
            ->selectRaw("COUNT(DISTINCT CONCAT(visits.domain_id, '|', {$sessionExpr})) as aggregate_count")
            ->value('aggregate_count');

        $select = [
            DB::raw("{$sessionExpr} as session_key"),
            'visits.domain_id',
            'domains.hostname',
            DB::raw('MIN(visits.ip) as ip'),
            DB::raw('COUNT(*) as page_views'),
            DB::raw('MIN(visits.visited_at) as first_seen'),
            DB::raw('MAX(visits.visited_at) as last_seen'),
            DB::raw('MAX(visits.country) as country'),
            DB::raw('MAX(visits.browser) as browser'),
            DB::raw('MAX(visits.os) as os'),
            DB::raw('MAX(visits.device) as device'),
            DB::raw('MAX(visits.referrer) as referrer'),
            DB::raw('MAX(visits.utm_source) as utm_source'),
            DB::raw('MAX(visits.utm_medium) as utm_medium'),
            DB::raw('MAX(visits.utm_campaign) as utm_campaign'),
            DB::raw('MAX(visits.utm_term) as utm_term'),
            DB::raw('MAX(visits.threat_score) as threat_score'),
            DB::raw('MAX(CASE WHEN visits.is_crawler = 1 THEN 1 ELSE 0 END) as is_crawler'),
            DB::raw('MAX(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as is_invalid'),
        ];

        if (Schema::hasColumn('visits', 'session_id')) {
            $select[] = DB::raw('MAX(visits.session_id) as session_id');
        }
        if (Schema::hasColumn('visits', 'fingerprint_id')) {
            $select[] = DB::raw('MAX(visits.fingerprint_id) as fingerprint_id');
        }

        $rows = (clone $base)
            ->select($select)
            ->groupBy('visits.domain_id', 'domains.hostname', DB::raw($sessionExpr))
            ->orderByDesc('last_seen')
            ->forPage($page, $perPage)
            ->get();

        $sessionKeys = $rows->pluck('session_key')->filter()->values();
        $recordings = $this->loadRecordings($domainIds, $sessionKeys, $from, $to);
        $landingPages = $this->loadLandingPages($domainIds, $from, $to, $sessionExpr);

        $data = $rows->map(function ($row) use ($recordings, $landingPages, $request) {
            $key = (string) $row->session_key;
            $rec = $recordings->get($key);
            $landing = $landingPages->get($key);
            $exit = $this->loadExitPage($row->domain_id, $key, $row->first_seen, $row->last_seen);

            $first = Carbon::parse($row->first_seen);
            $last = Carbon::parse($row->last_seen);
            $durationSec = max(0, $last->diffInSeconds($first));

            $platform = TrafficSourceClassifier::platformLabel(
                false,
                $row->utm_medium,
                $row->utm_source,
                $row->referrer,
            );

            $crawlerScore = (bool) ($row->is_crawler ?? false) ? min(100, 40 + (int) ($row->threat_score ?? 0)) : max(0, 10 - (int) ($row->threat_score ?? 0));
            $automationScore = (bool) ($row->is_invalid ?? false) ? min(100, (int) ($row->threat_score ?? 50)) : max(0, (int) ($row->threat_score ?? 0) / 2);
            $maliciousScore = (bool) ($row->is_invalid ?? false) ? min(100, (int) ($row->threat_score ?? 0)) : 0;

            return [
                'id' => (int) sprintf('%u', crc32($row->domain_id.'|'.$key)),
                'session_id' => $row->session_id ?? $key,
                'session_key' => $key,
                'ip' => $row->ip,
                'domain_id' => (int) $row->domain_id,
                'domain' => $row->hostname,
                'fingerprint_id' => $row->fingerprint_id ?? null,
                'source_platform' => $platform,
                'campaign' => $row->utm_campaign,
                'keyword' => $row->utm_term,
                'landing_page' => $landing ?? '/',
                'page_flow' => $rec['page_flow'] ?? '—',
                'pages' => $rec['pages'] ?? [],
                'first_seen' => UserTimezone::formatForUser($first, $request->user(), 'M j, Y g:i a'),
                'last_seen' => UserTimezone::formatForUser($last, $request->user(), 'M j, Y g:i a'),
                'entry_time' => UserTimezone::formatForUser($first, $request->user(), 'g:i a'),
                'exit_time' => UserTimezone::formatForUser($last, $request->user(), 'g:i a'),
                'timezone' => UserTimezone::reportingTimezone($request->user()),
                'time_on_site' => sprintf('%d:%02d', intdiv($durationSec, 60), $durationSec % 60),
                'page_views' => (int) $row->page_views,
                'scroll_events' => (int) ($rec['scroll_count'] ?? 0),
                'cta_clicks' => (int) ($rec['cta_clicks'] ?? 0),
                'tel_clicks' => (int) ($rec['tel_clicks'] ?? 0),
                'form_starts' => (int) ($rec['form_starts'] ?? 0),
                'form_submits' => (int) ($rec['form_submits'] ?? 0),
                'add_to_cart' => (int) ($rec['add_to_cart'] ?? 0),
                'checkout' => (int) ($rec['checkouts'] ?? 0),
                'purchase' => ((int) ($rec['purchases'] ?? 0)) > 0 ? 'Yes' : 'No',
                'revenue' => $rec['revenue'] ?? '—',
                'device' => TrafficSourceClassifier::deviceBucket($row->device, $row->os),
                'browser' => $row->browser,
                'os' => $row->os,
                'country' => $row->country,
                'crawler_score' => $crawlerScore,
                'automation_score' => $automationScore,
                'malicious_score' => $maliciousScore,
                'referrer' => $row->referrer,
                'exit_page' => $exit ?? '—',
                'session_recording_id' => $rec['id'] ?? null,
                'has_session_recording' => ! empty($rec['id']),
                'event_detail' => $rec['event_detail'] ?? [],
            ];
        })->values()->all();

        return ['data' => $data, 'total' => $total];
    }

    /** @param  list<int>  $domainIds */
    private function paidMarketingDomainIds(array $domainIds): array
    {
        return Domain::query()
            ->whereIn('id', $domainIds)
            ->where('monitoring_only_mode', false)
            ->pluck('id')
            ->all();
    }

    private function applyFilters($query, Request $request): void
    {
        if ($domainId = (int) $request->query('domain_id', 0)) {
            $query->where('visits.domain_id', $domainId);
        }
        if ($source = trim((string) ($request->query('source', $request->query('traffic_source', ''))))) {
            $needle = strtolower($source);
            $query->where(function ($q) use ($source, $needle): void {
                if (in_array($needle, ['organic', 'direct', 'social', 'referral', 'paid'], true)) {
                    if ($needle === 'paid') {
                        $q->where('visits.is_paid_traffic', 1);
                    } elseif ($needle === 'direct') {
                        $q->where(function ($inner): void {
                            $inner->whereNull('visits.referrer')->orWhere('visits.referrer', '');
                        })->where(function ($inner): void {
                            $inner->whereNull('visits.utm_source')->orWhere('visits.utm_source', '');
                        });
                    } else {
                        $q->where('visits.utm_medium', 'like', "%{$source}%")
                            ->orWhere('visits.utm_source', 'like', "%{$source}%")
                            ->orWhere('visits.referrer', 'like', "%{$source}%");
                    }
                } else {
                    $q->where('visits.utm_source', 'like', "%{$source}%")
                        ->orWhere('visits.referrer', 'like', "%{$source}%");
                }
            });
        }
        if ($campaign = trim((string) $request->query('campaign', ''))) {
            $query->where('visits.utm_campaign', 'like', "%{$campaign}%");
        }
        if ($path = trim((string) $request->query('path', ''))) {
            $query->where('visits.url', 'like', "%{$path}%");
        }
        if ($ip = trim((string) $request->query('ip', ''))) {
            $query->where('visits.ip', 'like', "%{$ip}%");
        }
        if ($device = trim((string) $request->query('device', ''))) {
            $query->where(function ($q) use ($device): void {
                $q->where('visits.device', 'like', "%{$device}%")
                    ->orWhere('visits.os', 'like', "%{$device}%");
            });
        }
    }

    /** @param  \Illuminate\Support\Collection<int, string>  $sessionKeys */
    private function loadRecordings(array $domainIds, $sessionKeys, Carbon $from, Carbon $to)
    {
        if (! Schema::hasTable('visit_session_recordings') || $sessionKeys->isEmpty()) {
            return collect();
        }

        $rows = DB::table('visit_session_recordings')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('created_at', [$from, $to])
            ->when(Schema::hasColumn('visit_session_recordings', 'session_id'), function ($q) use ($sessionKeys): void {
                $q->whereIn('session_id', $sessionKeys);
            })
            ->orderByDesc('id')
            ->get();

        return $rows->groupBy(fn ($r) => (string) ($r->session_id ?: $r->ip))->map(function ($group) {
            $rec = $group->first();
            $events = json_decode((string) ($rec->events ?? '[]'), true);
            $analysis = is_array($events)
                ? SessionBehaviorAnalyzer::analyze($events, (int) ($rec->duration_ms ?? 0))
                : SessionBehaviorAnalyzer::analyze([], 0);

            $pages = [];
            $pageEvents = [];
            foreach ($events as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                if (($ev['type'] ?? '') === 'page' && ! empty($ev['url'])) {
                    $path = TrafficSourceClassifier::pathFromUrl((string) $ev['url']);
                    $pages[] = $path;
                    $pageEvents[] = [
                        'label' => 'Page: '.$path,
                        'detail' => $path,
                        'kind' => 'page',
                        't' => (int) ($ev['t'] ?? 0),
                    ];
                }
            }
            $pageFlow = $pages !== [] ? implode(' → ', array_slice(array_unique($pages), 0, 6)) : '—';

            $revenue = 0.0;
            foreach ($events as $ev) {
                if (is_array($ev) && in_array(strtolower((string) ($ev['type'] ?? '')), ['purchase', 'sale'], true)) {
                    $revenue += (float) ($ev['revenue'] ?? $ev['value'] ?? 0);
                }
            }

            $timeline = $analysis['timeline'] ?? [];

            return [
                'id' => (int) $rec->id,
                'cta_clicks' => (int) ($rec->cta_clicks ?? $analysis['cta_clicks'] ?? 0),
                'tel_clicks' => (int) ($rec->tel_clicks ?? $analysis['tel_clicks'] ?? 0),
                'scroll_count' => (int) ($rec->scroll_count ?? $analysis['scroll_count'] ?? 0),
                'form_starts' => (int) ($analysis['form_starts'] ?? 0),
                'form_submits' => (int) ($analysis['form_submits'] ?? 0),
                'add_to_cart' => (int) ($analysis['add_to_cart'] ?? 0),
                'checkouts' => (int) ($analysis['checkouts'] ?? 0),
                'purchases' => (int) ($analysis['purchases'] ?? 0),
                'revenue' => $revenue > 0 ? '$'.number_format($revenue, 2) : '—',
                'page_flow' => $pageFlow,
                'pages' => array_values(array_unique($pages)),
                'event_detail' => [
                    'cta' => $timeline,
                    'timeline' => $timeline,
                    'pages' => $pageEvents,
                ],
            ];
        });
    }

    private function loadLandingPages(array $domainIds, Carbon $from, Carbon $to, string $sessionExpr)
    {
        $rows = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->select([
                DB::raw("{$sessionExpr} as session_key"),
                'url',
                'visited_at',
            ])
            ->orderBy('visited_at')
            ->get();

        return $rows->groupBy('session_key')->map(function ($group) {
            $first = $group->first();

            return TrafficSourceClassifier::pathFromUrl($first->url ?? '/');
        });
    }

    private function loadExitPage(int $domainId, string $sessionKey, mixed $from, mixed $to): ?string
    {
        $row = DB::table('visits')
            ->where('domain_id', $domainId)
            ->whereBetween('visited_at', [$from, $to])
            ->when(Schema::hasColumn('visits', 'session_id'), function ($q) use ($sessionKey): void {
                if (str_starts_with($sessionKey, 'ip:')) {
                    $q->where('ip', substr($sessionKey, 3));
                } else {
                    $q->where('session_id', $sessionKey);
                }
            }, function ($q) use ($sessionKey): void {
                $q->where('ip', str_starts_with($sessionKey, 'ip:') ? substr($sessionKey, 3) : $sessionKey);
            })
            ->orderByDesc('visited_at')
            ->value('url');

        return $row ? TrafficSourceClassifier::pathFromUrl((string) $row) : null;
    }
}
