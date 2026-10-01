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
     * @param  'bot'|'paid'|'all'  $trafficMode  bot = hide paid click-IDs on paid domains (Traffic Control);
     *                                           paid = Google Ads click-ID sessions only (Visitor Journey);
     *                                           all = no click-ID attribution filter
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(
        array $domainIds,
        Carbon $from,
        Carbon $to,
        Request $request,
        int $page,
        int $perPage,
        string $trafficMode = 'bot',
        bool $withTotal = true,
        bool $withRecordings = true,
    ): array {
        if (! Schema::hasTable('visits') || $domainIds === []) {
            return ['data' => [], 'total' => 0];
        }

        $sessionExpr = Schema::hasColumn('visits', 'session_id')
            ? "COALESCE(NULLIF(visits.session_id, ''), CONCAT('ip:', visits.ip))"
            : "CONCAT('ip:', visits.ip)";

        $searchTerm = trim((string) $request->query('ip', ''));
        $isDeviceSearch = $searchTerm !== '' && DeviceIdLabel::looksLikeDeviceId($searchTerm);

        $base = DB::table('visits')
            ->leftJoin('domains', 'domains.id', '=', 'visits.domain_id')
            ->whereIn('visits.domain_id', $domainIds);

        // Device ID pasted from Journey: widen date so a narrow chip doesn't hide the row.
        if ($isDeviceSearch) {
            $base->where('visits.visited_at', '>=', now()->subYear());
        } else {
            $base->whereBetween('visits.visited_at', [$from, $to]);
        }

        // Identity search must include paid click-ID sessions (same rows Journey shows).
        if ($isDeviceSearch) {
            $trafficMode = 'all';
        }

        $paidMarketingIds = $this->paidMarketingDomainIds($domainIds);

        if ($trafficMode === 'paid') {
            if ($paidMarketingIds === []) {
                return ['data' => [], 'total' => 0];
            }
            $base->whereIn('visits.domain_id', $paidMarketingIds);
            GoogleClickAttribution::applyHasClickIdFilter($base, 'visits');
        } elseif ($trafficMode !== 'all') {
            GoogleClickAttribution::excludeClickIdsForPaidDomains(
                $base,
                $paidMarketingIds,
                'visits',
            );
        }

        $this->applyFilters($base, $request, $paidMarketingIds);

        $total = 0;
        if ($withTotal) {
            $total = (int) (clone $base)
                ->selectRaw("COUNT(DISTINCT CONCAT(visits.domain_id, '|', {$sessionExpr})) as aggregate_count")
                ->value('aggregate_count');
        }

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
        if (Schema::hasColumn('visits', 'device_id')) {
            $select[] = DB::raw('MAX(visits.device_id) as device_id');
        }
        if (Schema::hasColumn('visits', 'fingerprint_id')) {
            $select[] = DB::raw('MAX(visits.fingerprint_id) as fingerprint_id');
        }
        if (Schema::hasColumn('visits', 'is_paid_traffic')) {
            $select[] = DB::raw('MAX(CASE WHEN visits.is_paid_traffic = 1 THEN 1 ELSE 0 END) as is_paid_traffic');
        }
        if (Schema::hasColumn('visits', 'gclid')) {
            $select[] = DB::raw('MAX(visits.gclid) as gclid');
        }
        if (Schema::hasColumn('visits', 'utm_content')) {
            $select[] = DB::raw('MAX(visits.utm_content) as utm_content');
        }
        if (Schema::hasColumn('visits', 'region')) {
            $select[] = DB::raw('MAX(visits.region) as region');
        } elseif (Schema::hasColumn('visits', 'city')) {
            $select[] = DB::raw('MAX(visits.city) as city');
        }

        $rows = (clone $base)
            ->select($select)
            ->groupBy('visits.domain_id', 'domains.hostname', DB::raw($sessionExpr))
            ->orderByDesc('last_seen')
            ->forPage($page, $perPage)
            ->get();

        $sessionKeys = $rows->pluck('session_key')->filter()->values();
        $recordings = $withRecordings
            ? $this->loadRecordings($domainIds, $sessionKeys, $from, $to)
            : collect();
        $landingPages = $this->loadLandingPages($domainIds, $from, $to, $sessionExpr, $sessionKeys);
        $exitPages = $this->loadExitPages($domainIds, $from, $to, $sessionExpr, $sessionKeys);
        $visitTrails = $this->loadVisitTrails($domainIds, $from, $to, $sessionExpr, $sessionKeys);

        $data = $rows->map(function ($row) use ($recordings, $landingPages, $exitPages, $visitTrails, $request, $trafficMode, $paidMarketingIds) {
            $key = (string) $row->session_key;
            $rec = $recordings->get($key);
            if (! is_array($rec)) {
                $ip = trim((string) ($row->ip ?? ''));
                if ($ip !== '') {
                    $rec = $recordings->get('ip:'.$ip) ?? $recordings->get($ip);
                }
            }
            if (! is_array($rec)) {
                $rec = [];
            }
            $landing = $landingPages->get($key);
            $exit = $exitPages->get($key);
            $trail = $visitTrails->get($key);
            if (! is_array($trail)) {
                $trail = [];
            }
            // Prefer recording pages; fill gaps from every visit URL change in this session.
            $rec = $this->mergeVisitTrailIntoRecording($rec, $trail, $landing, $exit);

            $first = Carbon::parse($row->first_seen);
            $last = Carbon::parse($row->last_seen);
            $durationSec = $this->resolveEngagedDurationSec($first, $last, $rec !== [] ? $rec : null);

            $adsLinked = in_array((int) $row->domain_id, $paidMarketingIds, true);
            $isPaid = $adsLinked && ((bool) ($row->is_paid_traffic ?? false) || $trafficMode === 'paid');
            $utmMedium = $row->utm_medium;
            if (! $adsLinked) {
                $medium = strtolower(trim((string) $utmMedium));
                if (in_array($medium, ['cpc', 'ppc', 'paid', 'paidsearch', 'cpm'], true)) {
                    $utmMedium = null;
                }
            }
            $platform = TrafficSourceClassifier::platformLabel(
                $isPaid,
                $utmMedium,
                $row->utm_source,
                $row->referrer,
            );
            if ($trafficMode === 'paid' && $adsLinked) {
                $platform = 'Google Ads';
            } elseif ($platform === 'Google' && ! $isPaid) {
                $platform = 'Google Organic';
            } elseif ($platform === 'Backlinks') {
                $platform = 'Backlink';
            }

            $crawlerScore = (bool) ($row->is_crawler ?? false) ? min(100, 40 + (int) ($row->threat_score ?? 0)) : max(0, 10 - (int) ($row->threat_score ?? 0));
            $automationScore = (bool) ($row->is_invalid ?? false) ? min(100, (int) ($row->threat_score ?? 50)) : max(0, (int) ($row->threat_score ?? 0) / 2);
            $maliciousScore = (bool) ($row->is_invalid ?? false) ? min(100, (int) ($row->threat_score ?? 0)) : 0;

            $headline = trim((string) ($row->utm_content ?? ''));
            if ($headline === '') {
                $headline = trim((string) ($row->utm_campaign ?? ''));
            }
            $region = null;
            if (isset($row->region) && trim((string) $row->region) !== '') {
                $region = trim((string) $row->region);
            } elseif (isset($row->city) && trim((string) $row->city) !== '') {
                $region = trim((string) $row->city);
            }

            $formFills = (int) ($rec['form_submits'] ?? 0);
            $pageViews = max((int) $row->page_views, count($rec['pages'] ?? []));
            $ctaClicks = (int) ($rec['cta_clicks'] ?? 0);
            $addToCart = (int) ($rec['add_to_cart'] ?? 0);
            $checkouts = (int) ($rec['checkouts'] ?? 0);
            $purchases = (int) ($rec['purchases'] ?? 0);
            $scrollEvents = (int) ($rec['scroll_count'] ?? 0);
            $telClicks = (int) ($rec['tel_clicks'] ?? 0);
            $formStarts = (int) ($rec['form_starts'] ?? 0);
            $revenueRaw = (float) ($rec['revenue_raw'] ?? 0);

            $eventActions = [];
            if ($pageViews > 0) {
                $eventActions[] = ['key' => 'page_view', 'count' => $pageViews];
            }
            if ($ctaClicks > 0) {
                $eventActions[] = ['key' => 'cta_click', 'count' => $ctaClicks];
            }
            if ($addToCart > 0) {
                $eventActions[] = ['key' => 'add_to_cart', 'count' => $addToCart];
            }
            if ($checkouts > 0) {
                $eventActions[] = ['key' => 'checkout', 'count' => $checkouts];
            }
            if ($purchases > 0) {
                $eventActions[] = ['key' => 'purchase', 'count' => $purchases];
            }
            if ($scrollEvents > 0) {
                $eventActions[] = ['key' => 'scroll', 'count' => $scrollEvents];
            }
            if ($formStarts > 0) {
                $eventActions[] = ['key' => 'form_start', 'count' => $formStarts];
            }
            if ($formFills > 0) {
                $eventActions[] = ['key' => 'form_submit', 'count' => $formFills];
            }
            if ($telClicks > 0) {
                $eventActions[] = ['key' => 'tel_click', 'count' => $telClicks];
            }

            $hours = intdiv($durationSec, 3600);
            $mins = intdiv($durationSec % 3600, 60);
            $secs = $durationSec % 60;
            $deviceBucket = TrafficSourceClassifier::deviceBucket($row->device, $row->os);

            $deviceRaw = trim((string) ($row->device_id ?? ''));
            $fpRaw = trim((string) ($row->fingerprint_id ?? ''));
            $ipRaw = trim((string) ($row->ip ?? ''));
            $deviceLabel = DeviceIdLabel::format($deviceRaw, $fpRaw, $ipRaw);

            return [
                'id' => (int) sprintf('%u', crc32($row->domain_id.'|'.$key)),
                'session_id' => $row->session_id ?? $key,
                'session_key' => $key,
                'ip' => $ipRaw !== '' ? $ipRaw : null,
                'domain_id' => (int) $row->domain_id,
                'domain' => $row->hostname,
                'device_id' => $deviceLabel,
                'device_id_raw' => $deviceRaw,
                'fingerprint_id' => $fpRaw,
                'is_paid' => $isPaid,
                'gclid' => trim((string) ($row->gclid ?? '')) ?: null,
                'source_platform' => $platform,
                'campaign' => $row->utm_campaign,
                'keyword' => $row->utm_term,
                'headline' => $headline !== '' ? $headline : null,
                'landing_page' => $landing ?? '/',
                'page_flow' => $rec['page_flow'] ?? '—',
                'pages' => $rec['pages'] ?? [],
                'first_seen' => UserTimezone::formatForUser($first, $request->user(), 'M j, Y g:i a'),
                'last_seen' => UserTimezone::formatForUser($last, $request->user(), 'M j, Y g:i a'),
                'entry_time' => UserTimezone::formatForUser($first, $request->user(), 'm/d/y'),
                'entry_clock' => UserTimezone::formatForUser($first, $request->user(), 'H:i:s'),
                'exit_time' => UserTimezone::formatForUser($last, $request->user(), 'm/d/y'),
                'exit_clock' => UserTimezone::formatForUser($last, $request->user(), 'H:i:s'),
                'timezone' => UserTimezone::reportingTimezoneForUser($request->user()),
                'time_on_site' => sprintf('%02d:%02d:%02d', $hours, $mins, $secs),
                'duration_sec' => $durationSec,
                'duration_ms' => (int) ($rec['duration_ms'] ?? max(0, $durationSec * 1000)),
                'first_seen_at' => $first->toIso8601String(),
                'last_seen_at' => $last->toIso8601String(),
                'page_views' => $pageViews,
                'event_actions' => $eventActions,
                'scroll_events' => $scrollEvents,
                'cta_clicks' => $ctaClicks,
                'tel_clicks' => $telClicks,
                'form_starts' => $formStarts,
                'form_submits' => $formFills,
                'form_fills' => $formFills,
                'add_to_cart' => $addToCart,
                'checkout' => $checkouts,
                'purchase' => $purchases > 0 ? 'Yes' : 'No',
                'revenue' => '$'.number_format($revenueRaw, 2),
                'device' => ucfirst($deviceBucket),
                'browser' => $row->browser,
                'os' => $row->os,
                'country' => $row->country,
                'region' => $region,
                'crawler_score' => $crawlerScore,
                'automation_score' => $automationScore,
                'malicious_score' => $maliciousScore,
                'referrer' => $row->referrer,
                'exit_page' => $exit ?? '—',
                'session_recording_id' => $rec['id'] ?? null,
                'has_session_recording' => ! empty($rec['id']),
                'recording_session_id' => trim((string) ($rec['session_id'] ?? '')) ?: null,
                'event_detail' => $rec['event_detail'] ?? [],
            ];
        })->values()->all();

        if ($withRecordings) {
            $data = app(SessionBehaviorEventEnricher::class)->enrich($data, $domainIds, $from, $to);
        }

        if (! $withTotal) {
            $total = count($data);
        }

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Engaged session length — not idle wall-clock between sparse hits hours apart.
     * Prefer recorder / event timeline; otherwise cap first→last at a 30-minute session timeout.
     *
     * @param  array<string, mixed>|null  $rec
     */
    private function resolveEngagedDurationSec(Carbon $first, Carbon $last, ?array $rec): int
    {
        // Carbon 3 returns a signed diff; absolute span between first and last hit.
        $wallClock = max(0, (int) round($first->diffInSeconds($last, true)));
        $recDur = (int) floor(((int) ($rec['duration_ms'] ?? 0)) / 1000);
        $timelineMax = 0;
        if (is_array($rec['event_detail']['timeline'] ?? null)) {
            foreach ($rec['event_detail']['timeline'] as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $t = (int) ($ev['elapsed_sec'] ?? 0);
                if ($t <= 0) {
                    $raw = (int) ($ev['t'] ?? 0);
                    // Recorder t is usually ms; values under 1000 are treated as seconds.
                    $t = $raw >= 1000 ? (int) floor($raw / 1000) : $raw;
                }
                $timelineMax = max($timelineMax, $t);
            }
        }
        $engaged = max($recDur, $timelineMax);
        // GA-style idle timeout: sparse pageviews hours apart are not continuous browsing.
        $idleCap = 30 * 60;

        if ($engaged > 0) {
            return $engaged;
        }

        return min($wallClock, $idleCap);
    }

    /** @param  list<int>  $domainIds */
    private function paidMarketingDomainIds(array $domainIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $domainIds)));
        if ($ids === []) {
            return [];
        }

        return Domain::query()
            ->whereIn('id', $ids)
            ->forPaidMarketing()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $paidMarketingIds
     */
    private function applyFilters($query, Request $request, array $paidMarketingIds = []): void
    {
        if ($domainId = (int) $request->query('domain_id', 0)) {
            $query->where('visits.domain_id', $domainId);
        }
        if ($source = trim((string) ($request->query('source', $request->query('traffic_source', ''))))) {
            $needle = strtolower($source);
            $query->where(function ($q) use ($source, $needle, $paidMarketingIds): void {
                if (in_array($needle, ['organic', 'direct', 'social', 'referral', 'paid'], true)) {
                    if ($needle === 'paid') {
                        if ($paidMarketingIds === []) {
                            $q->whereRaw('1 = 0');
                        } else {
                            $q->whereIn('visits.domain_id', $paidMarketingIds)
                                ->where('visits.is_paid_traffic', 1);
                        }
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
            if (DeviceIdLabel::looksLikeDeviceId($ip)
                || preg_match('/^ses_/i', $ip)
                || (! filter_var($ip, FILTER_VALIDATE_IP) && ! preg_match('/^\d{1,3}(\.\d{1,3}){0,3}$/', $ip) && strlen($ip) >= 6)
            ) {
                DeviceIdLabel::applyVisitIdentityFilter($query, $ip, 'visits');
            } else {
                $query->where('visits.ip', 'like', '%'.$ip.'%');
            }
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

        $ids = [];
        $ips = [];
        foreach ($sessionKeys as $key) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            if (str_starts_with($key, 'ip:')) {
                $ips[] = substr($key, 3);
            } else {
                $ids[] = $key;
            }
        }
        $ids = array_values(array_unique($ids));
        $ips = array_values(array_unique(array_filter($ips)));

        $rows = DB::table('visit_session_recordings')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('created_at', [$from->copy()->subDay(), $to->copy()->addDay()])
            ->where(function ($q) use ($ids, $ips): void {
                $added = false;
                if ($ids !== [] && Schema::hasColumn('visit_session_recordings', 'session_id')) {
                    $q->whereIn('session_id', $ids);
                    $added = true;
                }
                if ($ips !== [] && Schema::hasColumn('visit_session_recordings', 'ip')) {
                    if ($added) {
                        $q->orWhereIn('ip', $ips);
                    } else {
                        $q->whereIn('ip', $ips);
                        $added = true;
                    }
                }
                if (! $added) {
                    $q->whereRaw('0 = 1');
                }
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $processed = $rows->groupBy(function ($r) {
            $sid = trim((string) ($r->session_id ?? ''));
            if ($sid !== '') {
                return $sid;
            }
            $ip = trim((string) ($r->ip ?? ''));

            return $ip !== '' ? 'ip:'.$ip : 'unknown';
        })->map(function ($group) {
            $rec = $group->first();
            $events = json_decode((string) ($rec->events ?? '[]'), true);
            $analysis = is_array($events)
                ? SessionBehaviorAnalyzer::analyze($events, (int) ($rec->duration_ms ?? 0))
                : SessionBehaviorAnalyzer::analyze([], 0);

            $pages = [];
            $pageEvents = [];
            foreach (is_array($events) ? $events : [] as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $type = strtolower((string) ($ev['type'] ?? ''));
                if (in_array($type, ['page', 'page_view', 'page_change'], true)) {
                    $url = (string) ($ev['url'] ?? $ev['page_url'] ?? '');
                    $path = trim((string) ($ev['path'] ?? ''));
                    if ($url === '' && $path === '') {
                        continue;
                    }
                    if ($path === '' && $url !== '') {
                        $path = TrafficSourceClassifier::pathFromUrl($url);
                    }
                    if ($path === '') {
                        continue;
                    }
                    $pages[] = $path;
                    $pageEvents[] = [
                        'label' => 'Page: '.$path,
                        'detail' => trim(($ev['title'] ?? '').' '.$path),
                        'kind' => 'page',
                        'type' => $type === 'page_change' ? 'page_change' : 'page_view',
                        'page_url' => $url !== '' ? $url : null,
                        'path' => $path,
                        'title' => $ev['title'] ?? null,
                        't' => (int) ($ev['t'] ?? 0),
                        'elapsed_sec' => (int) floor(((int) ($ev['t'] ?? 0)) / 1000),
                        'at' => isset($ev['ts']) ? date('c', (int) floor(((int) $ev['ts']) / 1000)) : null,
                    ];
                }
            }
            $revenue = 0.0;
            foreach (is_array($events) ? $events : [] as $ev) {
                if (is_array($ev) && in_array(strtolower((string) ($ev['type'] ?? '')), ['purchase', 'sale'], true)) {
                    $revenue += (float) ($ev['revenue'] ?? $ev['value'] ?? 0);
                }
            }

            $timeline = $analysis['timeline'] ?? [];
            $uniquePages = array_values(array_unique($pages));
            $pageFlow = $uniquePages !== [] ? implode(' -> ', array_slice($uniquePages, 0, 8)) : '—';

            $byKind = [
                'cta' => [],
                'phone' => [],
                'form' => [],
                'commerce' => [],
                'page' => $pageEvents,
                'scroll' => [],
            ];
            foreach ($timeline as $item) {
                $kind = (string) ($item['kind'] ?? '');
                if ($kind === 'cta') {
                    $byKind['cta'][] = $item;
                } elseif ($kind === 'phone') {
                    $byKind['phone'][] = $item;
                } elseif ($kind === 'form') {
                    $byKind['form'][] = $item;
                } elseif ($kind === 'commerce') {
                    $byKind['commerce'][] = $item;
                } elseif ($kind === 'scroll') {
                    $byKind['scroll'][] = $item;
                }
            }

            return [
                'id' => (int) $rec->id,
                'duration_ms' => (int) ($rec->duration_ms ?? 0),
                'cta_clicks' => max((int) ($rec->cta_clicks ?? 0), (int) ($analysis['cta_clicks'] ?? 0)),
                'tel_clicks' => max((int) ($rec->tel_clicks ?? 0), (int) ($analysis['tel_clicks'] ?? 0)),
                'scroll_count' => max((int) ($rec->scroll_count ?? 0), (int) ($analysis['scroll_count'] ?? 0)),
                'form_starts' => (int) ($analysis['form_starts'] ?? 0),
                'form_submits' => (int) ($analysis['form_submits'] ?? 0),
                'add_to_cart' => (int) ($analysis['add_to_cart'] ?? 0),
                'checkouts' => (int) ($analysis['checkouts'] ?? 0),
                'purchases' => (int) ($analysis['purchases'] ?? 0),
                'revenue_raw' => $revenue,
                'revenue' => '$'.number_format($revenue, 2),
                'page_flow' => $pageFlow,
                'pages' => $uniquePages,
                'ip' => trim((string) ($rec->ip ?? '')),
                'session_id' => trim((string) ($rec->session_id ?? '')),
                'event_detail' => [
                    'cta' => $byKind['cta'],
                    'tel' => $byKind['phone'],
                    'phone' => $byKind['phone'],
                    'form' => $byKind['form'],
                    'commerce' => $byKind['commerce'],
                    'pages' => $pageEvents,
                    'scroll' => $byKind['scroll'],
                    'timeline' => $timeline,
                ],
            ];
        });

        // Index under session_id, bare IP, and ip:{ip} so visit session_key always resolves.
        $indexed = collect();
        foreach ($processed as $groupKey => $rec) {
            $indexed->put((string) $groupKey, $rec);
            $sid = (string) ($rec['session_id'] ?? '');
            $ip = (string) ($rec['ip'] ?? '');
            if ($sid !== '') {
                $indexed->put($sid, $rec);
            }
            if ($ip !== '') {
                $indexed->put($ip, $rec);
                $indexed->put('ip:'.$ip, $rec);
            }
        }

        return $indexed;
    }

    /** @param  \Illuminate\Support\Collection<int, string>  $sessionKeys */
    private function loadLandingPages(array $domainIds, Carbon $from, Carbon $to, string $sessionExpr, $sessionKeys)
    {
        if ($sessionKeys->isEmpty()) {
            return collect();
        }

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->select([
                DB::raw("{$sessionExpr} as session_key"),
                'url',
                'visited_at',
            ])
            ->orderBy('visited_at');

        $this->constrainToSessionKeys($query, $sessionExpr, $sessionKeys);

        return $query->get()->groupBy('session_key')->map(function ($group) {
            $first = $group->first();

            return TrafficSourceClassifier::pathFromUrl($first->url ?? '/');
        });
    }

    /** @param  \Illuminate\Support\Collection<int, string>  $sessionKeys */
    private function loadExitPages(array $domainIds, Carbon $from, Carbon $to, string $sessionExpr, $sessionKeys)
    {
        if ($sessionKeys->isEmpty()) {
            return collect();
        }

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->select([
                DB::raw("{$sessionExpr} as session_key"),
                'url',
                'visited_at',
            ])
            ->orderByDesc('visited_at');

        $this->constrainToSessionKeys($query, $sessionExpr, $sessionKeys);

        return $query->get()->groupBy('session_key')->map(function ($group) {
            $last = $group->first();

            return $last ? TrafficSourceClassifier::pathFromUrl((string) ($last->url ?? '/')) : null;
        });
    }

    /**
     * Every visit URL in the session (ordered), so Event Timeline can show page changes
     * even when the recorder only left a single page_view / exit stub.
     *
     * @param  list<int>  $domainIds
     * @param  \Illuminate\Support\Collection<int, string>  $sessionKeys
     * @return \Illuminate\Support\Collection<string, list<array{path:string,url:string,visited_at:string}>>
     */
    private function loadVisitTrails(array $domainIds, Carbon $from, Carbon $to, string $sessionExpr, $sessionKeys)
    {
        if ($sessionKeys->isEmpty()) {
            return collect();
        }

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->select([
                DB::raw("{$sessionExpr} as session_key"),
                'url',
                'visited_at',
            ])
            ->orderBy('visited_at')
            ->limit(5000);

        $this->constrainToSessionKeys($query, $sessionExpr, $sessionKeys);

        return $query->get()->groupBy('session_key')->map(function ($group) {
            $out = [];
            $prevPath = null;
            foreach ($group as $hit) {
                $url = (string) ($hit->url ?? '');
                $path = TrafficSourceClassifier::pathFromUrl($url !== '' ? $url : '/');
                // Keep consecutive same-path hits only once (reload noise).
                if ($path === $prevPath) {
                    continue;
                }
                $prevPath = $path;
                $out[] = [
                    'path' => $path,
                    'url' => $url,
                    'visited_at' => (string) ($hit->visited_at ?? ''),
                ];
                if (count($out) >= 40) {
                    break;
                }
            }

            return $out;
        });
    }

    /**
     * Inject visit-path changes into recording timeline/pages when mid-session trail is thin.
     *
     * @param  array<string, mixed>  $rec
     * @param  list<array{path:string,url:string,visited_at:string}>  $trail
     * @return array<string, mixed>
     */
    private function mergeVisitTrailIntoRecording(array $rec, array $trail, ?string $landing, ?string $exit): array
    {
        if ($trail === [] && ($landing || $exit)) {
            $trail = [];
            if ($landing) {
                $trail[] = ['path' => $landing, 'url' => '', 'visited_at' => ''];
            }
            if ($exit && $exit !== $landing) {
                $trail[] = ['path' => $exit, 'url' => '', 'visited_at' => ''];
            }
        }
        if ($trail === []) {
            return $rec;
        }

        $pages = array_values(array_filter(array_map(
            static fn ($p) => is_string($p) ? $p : '',
            $rec['pages'] ?? []
        )));
        foreach ($trail as $hit) {
            $path = (string) ($hit['path'] ?? '');
            if ($path !== '' && ! in_array($path, $pages, true)) {
                $pages[] = $path;
            }
        }
        if ($pages !== []) {
            $rec['pages'] = array_values(array_slice($pages, 0, 40));
            $rec['page_flow'] = implode(' -> ', array_slice($rec['pages'], 0, 8));
        }

        $detail = is_array($rec['event_detail'] ?? null) ? $rec['event_detail'] : [];
        $timeline = is_array($detail['timeline'] ?? null) ? $detail['timeline'] : [];

        $actionTypes = collect($timeline)->map(function ($ev) {
            return strtolower((string) (is_array($ev) ? ($ev['type'] ?? $ev['kind'] ?? '') : ''));
        })->all();
        $midActions = count(array_filter($actionTypes, static fn ($t) => in_array($t, [
            'cta', 'cta_click', 'phone', 'phone_click', 'tel_click', 'form', 'form_submit', 'form_start', 'scroll', 'click',
        ], true)));
        $pageMarkers = count(array_filter($actionTypes, static fn ($t) => in_array($t, [
            'page', 'page_view', 'page_change', 'meta',
        ], true)));

        // Already has a rich path trail from the recorder — keep it.
        if ($midActions >= 2 || $pageMarkers >= max(2, count($trail))) {
            $rec['event_detail'] = $detail + ['timeline' => $timeline];

            return $rec;
        }

        $startTs = null;
        foreach ($trail as $hit) {
            if (! empty($hit['visited_at'])) {
                try {
                    $startTs = Carbon::parse((string) $hit['visited_at'])->getTimestamp();
                    break;
                } catch (\Throwable) {
                    $startTs = null;
                }
            }
        }

        $existingKeys = [];
        foreach ($timeline as $ev) {
            if (! is_array($ev)) {
                continue;
            }
            $p = strtolower(trim((string) ($ev['path'] ?? $ev['page'] ?? $ev['label'] ?? '')));
            $t = (int) ($ev['elapsed_sec'] ?? 0);
            $existingKeys[$p.'|'.$t] = true;
        }

        $added = [];
        foreach ($trail as $i => $hit) {
            $path = (string) ($hit['path'] ?? '/');
            $elapsed = 0;
            if ($startTs && ! empty($hit['visited_at'])) {
                try {
                    $elapsed = max(0, Carbon::parse((string) $hit['visited_at'])->getTimestamp() - $startTs);
                } catch (\Throwable) {
                    $elapsed = $i;
                }
            } elseif (count($trail) > 1) {
                $elapsed = $i; // sequential placeholders when timestamps missing
            }
            $key = strtolower($path).'|'.$elapsed;
            if (isset($existingKeys[$key])) {
                continue;
            }
            // Also skip same path already present at any time for first marker only.
            if ($i === 0) {
                $dupLanding = false;
                foreach ($existingKeys as $ek => $_) {
                    if (str_starts_with((string) $ek, strtolower($path).'|')) {
                        $dupLanding = true;
                        break;
                    }
                }
                if ($dupLanding) {
                    continue;
                }
            }
            $existingKeys[$key] = true;
            $added[] = [
                'label' => $path,
                'detail' => $path,
                'kind' => 'page',
                'type' => $i === 0 ? 'page_view' : 'page_change',
                'path' => $path,
                'page' => $path,
                'page_url' => (string) ($hit['url'] ?? ''),
                't' => $elapsed * 1000,
                'elapsed_sec' => $elapsed,
                'at' => (string) ($hit['visited_at'] ?? ''),
            ];
        }

        if ($added !== []) {
            $timeline = array_values(array_merge($timeline, $added));
            usort($timeline, static function ($a, $b) {
                $ta = (int) (is_array($a) ? ($a['elapsed_sec'] ?? $a['t'] ?? 0) : 0);
                $tb = (int) (is_array($b) ? ($b['elapsed_sec'] ?? $b['t'] ?? 0) : 0);
                if ($ta >= 1000) {
                    $ta = (int) floor($ta / 1000);
                }
                if ($tb >= 1000) {
                    $tb = (int) floor($tb / 1000);
                }

                return $ta <=> $tb;
            });
            $detail['timeline'] = array_slice($timeline, 0, 80);
            $detail['pages'] = array_values(array_merge(
                is_array($detail['pages'] ?? null) ? $detail['pages'] : [],
                $added
            ));
            $rec['event_detail'] = $detail;
        }

        return $rec;
    }

    /** @param  \Illuminate\Support\Collection<int, string>  $sessionKeys */
    private function constrainToSessionKeys($query, string $sessionExpr, $sessionKeys): void
    {
        $keys = $sessionKeys->values()->all();
        $ips = [];
        $sessionIds = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            if (str_starts_with($key, 'ip:')) {
                $ips[] = substr($key, 3);
            } else {
                $sessionIds[] = $key;
            }
        }

        $query->where(function ($q) use ($sessionIds, $ips): void {
            if ($sessionIds !== [] && Schema::hasColumn('visits', 'session_id')) {
                $q->orWhereIn('session_id', $sessionIds);
            }
            if ($ips !== []) {
                $q->orWhereIn('ip', $ips);
            }
            // Fallback: never match nothing if keys were malformed.
            if ($sessionIds === [] && $ips === []) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
