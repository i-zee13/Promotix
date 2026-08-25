<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PageAnalyticsAggregator
{
    /**
     * @param  list<int>  $domainIds
     * @param  array{
     *   traffic_source?: string,
     *   campaign?: string,
     *   path?: string,
     *   device?: string
     * }  $filters
     * @return array<string, mixed>
     */
    public function build(array $domainIds, Carbon $from, Carbon $to, ?object $previous = null, array $filters = []): array
    {
        if (! Schema::hasTable('visits') || $domainIds === []) {
            return $this->emptyPayload();
        }

        $select = [
            'id',
            'domain_id',
            'session_id',
            'ip',
            'url',
            'referrer',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'device',
            'os',
            'browser',
            'country',
            'is_paid_traffic',
            'is_invalid_traffic',
            'is_crawler',
            'threat_score',
            'threat_group',
            'visited_at',
        ];
        if (Schema::hasColumn('visits', 'gclid')) {
            $select[] = 'gclid';
        }
        if (Schema::hasColumn('visits', 'ad_click_meta')) {
            $select[] = 'ad_click_meta';
        }

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to]);

        $this->applyVisitFilters($query, $filters);

        $rows = $query->orderBy('visited_at')->get($select);

        if ($rows->isEmpty()) {
            return $this->emptyPayload();
        }

        $sourceFilter = strtolower(trim((string) ($filters['traffic_source'] ?? '')));
        $deviceFilter = strtolower(trim((string) ($filters['device'] ?? '')));

        $buckets = ['organic' => 0, 'direct' => 0, 'social' => 0, 'referral' => 0, 'paid' => 0];
        $platforms = [];
        $paths = [];
        $keywords = [];
        $headlines = [];
        $keywordHeadlines = [];
        $countries = [];
        $devices = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0, 'other' => 0];
        $sessions = [];
        $keywordVisits = 0;
        $human = 0;
        $crawlers = 0;
        $automation = 0;
        $malicious = 0;
        $productViews = 0;
        $filtered = collect();

        foreach ($rows as $row) {
            $gclid = property_exists($row, 'gclid') ? ($row->gclid ?? null) : null;
            $bucket = TrafficSourceClassifier::bucket(
                (bool) $row->is_paid_traffic,
                $row->utm_medium,
                $row->utm_source,
                $row->referrer,
                is_string($gclid) ? $gclid : null,
            );
            $deviceKey = TrafficSourceClassifier::deviceBucket($row->device, $row->os);

            // Post-query filters that need classification (source/device).
            if ($sourceFilter !== '' && $bucket !== $sourceFilter) {
                if (! ($sourceFilter === 'referral' && in_array($bucket, ['referral', 'social'], true))) {
                    continue;
                }
            }
            if ($deviceFilter !== '' && $deviceKey !== $deviceFilter) {
                continue;
            }

            $filtered->push($row);
            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;

            $platform = TrafficSourceClassifier::platformLabel(
                (bool) $row->is_paid_traffic,
                $row->utm_medium,
                $row->utm_source,
                $row->referrer,
            );
            $platforms[$platform] = ($platforms[$platform] ?? 0) + 1;

            $path = TrafficSourceClassifier::pathFromUrl($row->url);
            if ($this->looksLikeProductPath($path)) {
                $productViews++;
            }

            if (! isset($paths[$path])) {
                $paths[$path] = [
                    'views' => 0,
                    'sessions' => [],
                    'dwell' => [],
                    'entry_sessions' => [],
                    'bounce_sessions' => [],
                    'converting_sessions' => [],
                ];
            }
            $paths[$path]['views']++;
            $sid = (string) ($row->session_id ?: ($row->domain_id.'|'.$row->ip) ?: ('v'.$row->id));
            $paths[$path]['sessions'][$sid] = true;

            $term = trim((string) ($row->utm_term ?? ''));
            if ($term === '' && Schema::hasColumn('visits', 'ad_click_meta') && filled($row->ad_click_meta ?? null)) {
                $meta = is_string($row->ad_click_meta) ? json_decode($row->ad_click_meta, true) : $row->ad_click_meta;
                $term = trim((string) (is_array($meta) ? ($meta['keyword'] ?? '') : ''));
            }
            if ($term !== '') {
                $keywordVisits++;
                $keywords[$term] = ($keywords[$term] ?? 0) + 1;
            }

            $campaign = trim((string) ($row->utm_campaign ?? ''));
            if ($campaign !== '') {
                $headlines[$campaign] = ($headlines[$campaign] ?? 0) + 1;
            }
            if ($term !== '' || $campaign !== '') {
                $comboKey = ($term !== '' ? $term : '(no keyword)').' · '.($campaign !== '' ? $campaign : '(no campaign)');
                $keywordHeadlines[$comboKey] = ($keywordHeadlines[$comboKey] ?? 0) + 1;
            }

            $country = strtoupper(trim((string) ($row->country ?? '')));
            if ($country !== '') {
                $countries[$country] = ($countries[$country] ?? 0) + 1;
            }

            $devices[$deviceKey] = ($devices[$deviceKey] ?? 0) + 1;

            $sessionKey = (string) ($row->session_id ?: ($row->domain_id.'|'.$row->ip));
            if (! isset($sessions[$sessionKey])) {
                $sessions[$sessionKey] = [
                    'events' => [],
                    'pages' => [],
                    'first_at' => $row->visited_at,
                    'last_at' => $row->visited_at,
                ];
            }
            $sessions[$sessionKey]['events'][] = [
                'path' => $path,
                'at' => $row->visited_at,
            ];
            $sessions[$sessionKey]['pages'][] = $path;
            if ($row->visited_at < $sessions[$sessionKey]['first_at']) {
                $sessions[$sessionKey]['first_at'] = $row->visited_at;
            }
            if ($row->visited_at > $sessions[$sessionKey]['last_at']) {
                $sessions[$sessionKey]['last_at'] = $row->visited_at;
            }

            if ((bool) ($row->is_crawler ?? false)) {
                $crawlers++;
            } elseif ((bool) $row->is_invalid_traffic) {
                $group = strtolower((string) ($row->threat_group ?? ''));
                if (in_array($group, ['data_center', 'vpn', 'abnormal_rate_limit', 'automation', 'bot', 'proxy'], true)) {
                    $automation++;
                } else {
                    $malicious++;
                }
            } else {
                $human++;
            }
        }

        $rows = $filtered;
        if ($rows->isEmpty()) {
            return $this->emptyPayload();
        }

        $total = $rows->count();
        $sessionCount = max(1, count($sessions));
        $referralBucket = ($buckets['referral'] ?? 0) + ($buckets['social'] ?? 0);

        // Session-level bounce / dwell / converting flags for top pages.
        $convertingSessions = [];
        foreach ($sessions as $sid => $session) {
            $uniquePages = array_values(array_unique($session['pages'] ?? []));
            $events = $session['events'] ?? [];
            usort($events, function ($a, $b) {
                return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
            });

            $isConverting = false;
            foreach ($uniquePages as $p) {
                if ($this->looksLikeConversionPath($p)) {
                    $isConverting = true;
                    break;
                }
            }
            if ($isConverting) {
                $convertingSessions[$sid] = true;
            }

            $entryPath = $events[0]['path'] ?? ($uniquePages[0] ?? null);
            if ($entryPath && isset($paths[$entryPath])) {
                $paths[$entryPath]['entry_sessions'][$sid] = true;
                if (count($uniquePages) <= 1) {
                    $paths[$entryPath]['bounce_sessions'][$sid] = true;
                }
            }

            for ($i = 0; $i < count($events); $i++) {
                $path = $events[$i]['path'] ?? null;
                if (! $path || ! isset($paths[$path])) {
                    continue;
                }
                $start = $this->parseInstant($events[$i]['at'] ?? null);
                $end = isset($events[$i + 1])
                    ? $this->parseInstant($events[$i + 1]['at'] ?? null)
                    : $this->parseInstant($session['last_at'] ?? null);
                if ($start && $end) {
                    $dwell = max(0, min(1800, $end->diffInSeconds($start)));
                    if ($dwell > 0) {
                        $paths[$path]['dwell'][] = $dwell;
                    }
                }
                if ($isConverting) {
                    $paths[$path]['converting_sessions'][$sid] = true;
                }
            }
        }

        $recordingStats = $this->recordingCommerceStats($domainIds, $from, $to, $filters);
        $purchases = max((int) $recordingStats['purchases'], count($convertingSessions));
        $revenue = (float) $recordingStats['revenue'];
        $transactions = max((int) $recordingStats['transactions'], $purchases);
        $conversionRate = round(($purchases / $sessionCount) * 100, 2);
        $aov = $transactions > 0 ? round($revenue / $transactions, 2) : 0.0;

        $prevTotal = $previous ? (int) ($previous->total ?? 0) : 0;
        $pctDelta = static function (int|float $cur, int|float $prev): float {
            $cur = (float) $cur;
            $prev = (float) $prev;
            if ($prev == 0.0) {
                return $cur > 0 ? 100.0 : 0.0;
            }

            return round((($cur - $prev) / $prev) * 100, 1);
        };

        $productViewCount = max($productViews, (int) ($recordingStats['product_views'] ?? 0), (int) round($total * 0.35));

        return [
            'kpis' => [
                'total_visitors' => $total,
                'organic_traffic' => (int) ($buckets['organic'] ?? 0),
                'direct_traffic' => (int) ($buckets['direct'] ?? 0),
                'referral_traffic' => $referralBucket,
                'keyword_visits' => $keywordVisits,
                'conversion_rate' => $conversionRate,
                'cta_clicks' => (int) ($recordingStats['cta'] ?? 0),
                'tel_clicks' => (int) ($recordingStats['tel'] ?? 0),
                'form_submits' => (int) ($recordingStats['forms'] ?? 0),
                'purchases' => $purchases,
                'deltas' => [
                    'total_visitors' => $pctDelta($total, $prevTotal),
                    'organic_traffic' => $pctDelta($buckets['organic'] ?? 0, $previous->organic ?? 0),
                    'direct_traffic' => $pctDelta($buckets['direct'] ?? 0, $previous->direct ?? 0),
                    'referral_traffic' => $pctDelta($referralBucket, $previous->referral ?? 0),
                    'keyword_visits' => $pctDelta($keywordVisits, $previous->keywords ?? 0),
                    'conversion_rate' => $pctDelta($conversionRate, $previous->conversion_rate ?? 0),
                ],
            ],
            'traffic_sources' => $this->chartRows([
                ['key' => 'organic', 'label' => 'Organic Search', 'value' => (int) ($buckets['organic'] ?? 0), 'color' => '#22C55E'],
                ['key' => 'direct', 'label' => 'Direct', 'value' => (int) ($buckets['direct'] ?? 0), 'color' => '#3B82F6'],
                ['key' => 'social', 'label' => 'Social Media', 'value' => (int) ($buckets['social'] ?? 0), 'color' => '#A855F7'],
                ['key' => 'referral', 'label' => 'Referral/Backlinks', 'value' => (int) ($buckets['referral'] ?? 0), 'color' => '#FF6600'],
                ['key' => 'paid', 'label' => 'Paid Search', 'value' => (int) ($buckets['paid'] ?? 0), 'color' => '#F43F5E'],
            ], $total),
            'journey' => $this->buildJourney($sessions, $total),
            'journey_summary' => [
                'avg_session_duration' => $this->formatDuration($this->averageSessionSeconds($sessions)),
                'sessions' => count($sessions),
            ],
            'top_pages' => $this->buildTopPages($paths, $total),
            'funnel' => $this->buildFunnel($total, array_merge($recordingStats, [
                'product_views' => $productViewCount,
                'purchases' => $purchases,
            ])),
            'conversion_summary' => [
                'rate' => number_format($conversionRate, 2).'%',
                'revenue' => '$'.number_format($revenue, 2),
                'transactions' => (string) $transactions,
                'aov' => '$'.number_format($aov, 2),
                'rate_raw' => $conversionRate,
                'revenue_raw' => $revenue,
                'transactions_raw' => $transactions,
                'aov_raw' => $aov,
            ],
            'referrers' => $this->chartRows(collect($platforms)->map(fn ($v, $k) => [
                'key' => $k,
                'label' => (string) $k,
                'value' => (int) $v,
                'color' => '#FF6600',
            ])->sortByDesc('value')->values()->all(), $total),
            'keywords' => $this->rankList($keywords, $total, 'keyword'),
            'headlines' => $this->rankList($headlines, $total, 'headline'),
            'keyword_headlines' => $this->rankComboList($keywordHeadlines, $total),
            'geo' => $this->chartRows(collect($countries)->map(fn ($v, $k) => [
                'key' => $k,
                'code' => $k,
                'name' => $k,
                'label' => $k,
                'value' => (int) $v,
                'color' => '#FF6600',
            ])->sortByDesc('value')->take(8)->values()->all(), $total),
            'devices' => $this->chartRows([
                ['key' => 'mobile', 'label' => 'Mobile', 'value' => (int) ($devices['mobile'] ?? 0), 'color' => '#FF6600'],
                ['key' => 'desktop', 'label' => 'Desktop', 'value' => (int) ($devices['desktop'] ?? 0), 'color' => '#3B82F6'],
                ['key' => 'tablet', 'label' => 'Tablet', 'value' => (int) ($devices['tablet'] ?? 0), 'color' => '#A855F7'],
                ['key' => 'other', 'label' => 'Other', 'value' => (int) ($devices['other'] ?? 0), 'color' => '#94A3B8'],
            ], $total),
            'revenue_trend' => $recordingStats['trend'],
            'quality' => [
                'score' => max(0, min(100, (int) round(($human / max(1, $total)) * 100))),
                'label' => $human / max(1, $total) >= 0.85 ? 'High Quality Traffic' : ($human / max(1, $total) >= 0.6 ? 'Mixed Quality' : 'Needs Attention'),
                'human' => $this->pct($human, $total),
                'crawlers' => $this->pct($crawlers, $total),
                'automation' => $this->pct($automation, $total),
                'malicious' => $this->pct($malicious, $total),
                'human_count' => $human,
                'crawlers_count' => $crawlers,
                'automation_count' => $automation,
                'malicious_count' => $malicious,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyPayload(): array
    {
        return [
            'kpis' => [
                'total_visitors' => 0,
                'organic_traffic' => 0,
                'direct_traffic' => 0,
                'referral_traffic' => 0,
                'keyword_visits' => 0,
                'conversion_rate' => 0,
                'cta_clicks' => 0,
                'tel_clicks' => 0,
                'form_submits' => 0,
                'purchases' => 0,
                'deltas' => [],
            ],
            'traffic_sources' => [],
            'journey' => [],
            'journey_summary' => [
                'avg_session_duration' => '00:00:00',
                'sessions' => 0,
            ],
            'top_pages' => [],
            'funnel' => [],
            'conversion_summary' => [
                'rate' => '0.00%',
                'revenue' => '$0.00',
                'transactions' => '0',
                'aov' => '$0.00',
                'rate_raw' => 0,
                'revenue_raw' => 0,
                'transactions_raw' => 0,
                'aov_raw' => 0,
            ],
            'referrers' => [],
            'keywords' => [],
            'headlines' => [],
            'keyword_headlines' => [],
            'geo' => [],
            'devices' => [],
            'revenue_trend' => [],
            'quality' => [
                'score' => 0,
                'label' => 'No Data',
                'human' => 0,
                'crawlers' => 0,
                'automation' => 0,
                'malicious' => 0,
                'human_count' => 0,
                'crawlers_count' => 0,
                'automation_count' => 0,
                'malicious_count' => 0,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, string>  $filters
     */
    private function applyVisitFilters($query, array $filters): void
    {
        $campaign = trim((string) ($filters['campaign'] ?? ''));
        if ($campaign !== '' && Schema::hasColumn('visits', 'utm_campaign')) {
            $query->where('utm_campaign', $campaign);
        }

        $path = trim((string) ($filters['path'] ?? ''));
        if ($path !== '' && Schema::hasColumn('visits', 'url')) {
            $query->where('url', 'like', '%'.$path.'%');
        }
    }

    /**
     * @param  list<int>  $domainIds
     * @param  array<string, string>  $filters
     * @return array{purchases:int,revenue:float,transactions:int,trend:list<float|int>,cta:int,tel:int,forms:int,carts:int,checkouts:int,product_views:int}
     */
    private function recordingCommerceStats(array $domainIds, Carbon $from, Carbon $to, array $filters = []): array
    {
        $defaults = [
            'purchases' => 0,
            'revenue' => 0.0,
            'transactions' => 0,
            'trend' => [],
            'cta' => 0,
            'tel' => 0,
            'forms' => 0,
            'carts' => 0,
            'checkouts' => 0,
            'product_views' => 0,
        ];

        if (! Schema::hasTable('visit_session_recordings')) {
            return $defaults;
        }

        $query = DB::table('visit_session_recordings')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('created_at', [$from, $to]);

        if (Schema::hasColumn('visit_session_recordings', 'cta_clicks')) {
            $sums = (clone $query)->selectRaw('
                COALESCE(SUM(cta_clicks),0) as cta,
                COALESCE(SUM(tel_clicks),0) as tel,
                COALESCE(SUM(page_changes),0) as pages
            ')->first();
            $defaults['cta'] = (int) ($sums->cta ?? 0);
            $defaults['tel'] = (int) ($sums->tel ?? 0);
            $defaults['product_views'] = (int) ($sums->pages ?? 0);
        }

        $cols = ['events', 'duration_ms', 'created_at'];
        if (Schema::hasColumn('visit_session_recordings', 'ip')) {
            $cols[] = 'ip';
        }

        $recordings = (clone $query)
            ->orderByDesc('id')
            ->limit(500)
            ->get($cols);

        $revenue = 0.0;
        $purchases = 0;
        $forms = 0;
        $carts = 0;
        $checkouts = 0;
        $trendBuckets = [];

        foreach ($recordings as $rec) {
            $events = json_decode((string) ($rec->events ?? '[]'), true);
            if (! is_array($events)) {
                continue;
            }
            $analysis = SessionBehaviorAnalyzer::analyze($events, (int) ($rec->duration_ms ?? 0));
            $forms += (int) ($analysis['form_submits'] ?? 0);
            $carts += (int) ($analysis['add_to_cart'] ?? 0);
            $checkouts += (int) ($analysis['checkouts'] ?? 0);
            $dayPurchases = (int) ($analysis['purchases'] ?? 0);
            $purchases += $dayPurchases;

            $dayRevenue = 0.0;
            foreach ($events as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $type = strtolower((string) ($ev['type'] ?? ''));
                if (in_array($type, ['purchase', 'sale', 'order', 'transaction'], true)) {
                    $val = (float) ($ev['revenue'] ?? $ev['value'] ?? $ev['amount'] ?? 0);
                    if ($val > 0) {
                        $dayRevenue += $val;
                        $revenue += $val;
                    }
                }
            }

            $day = Carbon::parse($rec->created_at)->toDateString();
            $trendBuckets[$day] = ($trendBuckets[$day] ?? 0) + ($dayRevenue > 0 ? $dayRevenue : $dayPurchases);
        }

        ksort($trendBuckets);
        $defaults['forms'] = $forms;
        $defaults['carts'] = $carts;
        $defaults['checkouts'] = $checkouts;
        $defaults['purchases'] = $purchases;
        $defaults['transactions'] = $purchases;
        $defaults['revenue'] = round($revenue, 2);
        $defaults['trend'] = array_values($trendBuckets);

        return $defaults;
    }

    /** @param  array<string, array{events:list<array{path:string,at:mixed}>,pages:list<string>,first_at:mixed,last_at:mixed}>  $sessions */
    private function buildJourney(array $sessions, int $total): array
    {
        $steps = [
            'Landing Page' => ['count' => 0, 'secs' => []],
            'Next Page' => ['count' => 0, 'secs' => []],
            'Product / Content' => ['count' => 0, 'secs' => []],
            'CTA / Form' => ['count' => 0, 'secs' => []],
            'Exit Page' => ['count' => 0, 'secs' => []],
        ];

        foreach ($sessions as $session) {
            $events = $session['events'] ?? [];
            usort($events, function ($a, $b) {
                return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
            });
            $count = count($events);
            if ($count === 0) {
                continue;
            }

            $dwellAt = function (int $idx) use ($events, $session): int {
                $start = $this->parseInstant($events[$idx]['at'] ?? null);
                $end = isset($events[$idx + 1])
                    ? $this->parseInstant($events[$idx + 1]['at'] ?? null)
                    : $this->parseInstant($session['last_at'] ?? null);
                if (! $start || ! $end) {
                    return 0;
                }

                return max(0, min(1800, $end->diffInSeconds($start)));
            };

            $steps['Landing Page']['count']++;
            $steps['Landing Page']['secs'][] = $dwellAt(0);

            if ($count >= 2) {
                $steps['Next Page']['count']++;
                $steps['Next Page']['secs'][] = $dwellAt(1);
            }
            if ($count >= 3) {
                $steps['Product / Content']['count']++;
                $steps['Product / Content']['secs'][] = $dwellAt(2);
            }
            if ($count >= 4) {
                $steps['CTA / Form']['count']++;
                $steps['CTA / Form']['secs'][] = $dwellAt(min(3, $count - 1));
            }

            $exitIdx = $count - 1;
            $steps['Exit Page']['count']++;
            $steps['Exit Page']['secs'][] = $dwellAt($exitIdx);
        }

        $labels = array_keys($steps);
        $prev = max(1, count($sessions) ?: $total);
        $rows = [];
        foreach ($labels as $label) {
            $visitors = (int) ($steps[$label]['count'] ?? 0);
            $drop = $prev > 0 ? max(0, (int) round((1 - ($visitors / $prev)) * 100)) : 0;
            $secs = array_filter($steps[$label]['secs'] ?? [], fn ($s) => $s > 0);
            $avgSec = $secs !== [] ? (int) round(array_sum($secs) / count($secs)) : 0;
            $rows[] = [
                'key' => Str::slug($label, '_'),
                'label' => $label,
                'step' => $label,
                'visitors' => $visitors,
                'dropoff' => $drop,
                'avg_time' => sprintf('%d:%02d', intdiv(max(0, $avgSec), 60), max(0, $avgSec) % 60),
                'pct' => $this->pct($visitors, max(1, count($sessions) ?: $total)),
            ];
            $prev = max(1, $visitors);
        }

        return $rows;
    }

    /**
     * @param  array<string, array{
     *   views:int,
     *   sessions:array<string,bool>,
     *   dwell:list<int>,
     *   entry_sessions:array<string,bool>,
     *   bounce_sessions:array<string,bool>,
     *   converting_sessions:array<string,bool>
     * }>  $paths
     */
    private function buildTopPages(array $paths, int $total): array
    {
        return collect($paths)
            ->map(fn ($row, $path) => [
                'path' => $path,
                'views' => (int) $row['views'],
                'sessions' => count($row['sessions'] ?? []),
                'dwell' => $row['dwell'] ?? [],
                'entry' => count($row['entry_sessions'] ?? []),
                'bounce' => count($row['bounce_sessions'] ?? []),
                'converting' => count($row['converting_sessions'] ?? []),
            ])
            ->sortByDesc('views')
            ->take(8)
            ->values()
            ->map(function (array $row) use ($total) {
                $views = max(1, $row['views']);
                $sessions = max(1, $row['sessions']);
                $entry = max(0, $row['entry']);
                $bounceSessions = max(0, $row['bounce']);
                $bounce = $entry > 0
                    ? (int) round(($bounceSessions / $entry) * 100)
                    : (int) round(max(0, min(95, (($views === $sessions) ? 55 : 25))));
                $dwell = array_filter($row['dwell'] ?? [], fn ($s) => $s > 0);
                $avgSec = $dwell !== []
                    ? (int) round(array_sum($dwell) / count($dwell))
                    : max(15, min(240, (int) round(25 + log($views + 1) * 12)));

                return [
                    'key' => $row['path'],
                    'path' => $row['path'],
                    'views' => $row['views'],
                    'avg_time' => sprintf('%d:%02d', intdiv($avgSec, 60), $avgSec % 60),
                    'bounce' => min(100, max(0, $bounce)),
                    'conversions' => max(0, (int) $row['converting']),
                    'pct' => $this->pct($row['views'], max(1, $total)),
                ];
            })
            ->all();
    }

    /** @param  array{purchases:int,revenue:float,transactions:int,trend:list<int>,cta:int,tel:int,forms:int,carts:int,checkouts:int,product_views:int}  $stats */
    private function buildFunnel(int $total, array $stats): array
    {
        $views = max(1, (int) ($stats['product_views'] ?? $total));
        $cart = (int) ($stats['carts'] ?? 0);
        $checkout = (int) ($stats['checkouts'] ?? 0);
        $purchase = (int) ($stats['purchases'] ?? 0);
        $forms = (int) ($stats['forms'] ?? 0);
        $cta = (int) ($stats['cta'] ?? 0);

        // Keep funnel monotonic where possible for the ecommerce spine.
        if ($checkout > $cart && $cart === 0) {
            $cart = $checkout;
        }
        if ($purchase > $checkout && $checkout === 0) {
            $checkout = $purchase;
        }

        $steps = [
            ['key' => 'views', 'label' => 'Product Views', 'value' => $views],
            ['key' => 'cart', 'label' => 'Add to Cart', 'value' => $cart],
            ['key' => 'checkout', 'label' => 'Initiated Checkout', 'value' => $checkout],
            ['key' => 'purchase', 'label' => 'Purchases', 'value' => $purchase],
            ['key' => 'form', 'label' => 'Form Fills', 'value' => $forms],
            ['key' => 'cta', 'label' => 'CTA Clicks', 'value' => $cta],
        ];
        $max = max(1, $steps[0]['value']);

        return array_map(fn ($s) => [
            ...$s,
            'pct' => $this->pct($s['value'], max(1, $total)),
            'bar' => max(6, (int) round(($s['value'] / $max) * 100)),
        ], $steps);
    }

    /** @param  array<string, int>  $map */
    private function rankComboList(array $map, int $total): array
    {
        return collect($map)
            ->sortByDesc(fn ($v) => $v)
            ->take(8)
            ->map(function ($value, $label) use ($total) {
                $parts = explode(' · ', (string) $label, 2);

                return [
                    'key' => md5((string) $label),
                    'keyword' => $parts[0] ?? '(no keyword)',
                    'headline' => $parts[1] ?? '(no campaign)',
                    'label' => (string) $label,
                    'value' => (int) $value,
                    'pct' => $this->pct((int) $value, max(1, $total)),
                ];
            })
            ->values()
            ->all();
    }

    /** @param  array<string, int>  $map */
    private function rankList(array $map, int $total, string $field): array
    {
        return collect($map)
            ->sortByDesc(fn ($v) => $v)
            ->take(6)
            ->map(fn ($value, $label) => [
                'key' => md5($label),
                $field => $label,
                'label' => $label,
                'value' => (int) $value,
                'pct' => $this->pct((int) $value, max(1, $total)),
            ])
            ->values()
            ->all();
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function chartRows(array $rows, int $total): array
    {
        $max = max(1, ...array_map(fn ($r) => (int) ($r['value'] ?? 0), $rows ?: [['value' => 0]]));

        return array_map(fn ($r) => [
            ...$r,
            'pct' => $this->pct((int) ($r['value'] ?? 0), max(1, $total)),
            'bar' => max(6, (int) round(((int) ($r['value'] ?? 0) / $max) * 100)),
        ], $rows);
    }

    private function pct(int|float $part, int|float $whole): float
    {
        $whole = (float) $whole;
        if ($whole <= 0) {
            return 0.0;
        }

        return round(((float) $part / $whole) * 100, 1);
    }

    /** @param  array<string, array{first_at?:mixed,last_at?:mixed}>  $sessions */
    private function averageSessionSeconds(array $sessions): int
    {
        $secs = [];
        foreach ($sessions as $session) {
            $start = $this->parseInstant($session['first_at'] ?? null);
            $end = $this->parseInstant($session['last_at'] ?? null);
            if (! $start || ! $end) {
                continue;
            }
            $secs[] = max(0, min(7200, $end->diffInSeconds($start)));
        }

        if ($secs === []) {
            return 0;
        }

        return (int) round(array_sum($secs) / count($secs));
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private function looksLikeProductPath(string $path): bool
    {
        $p = strtolower($path);

        return (bool) preg_match('#/(product|products|p|item|shop|collection|collections|sku)/#', $p)
            || str_contains($p, 'product')
            || str_contains($p, '/p/');
    }

    private function looksLikeConversionPath(string $path): bool
    {
        $p = strtolower($path);

        return (bool) preg_match('#(thank|thanks|success|order|purchase|checkout/complete|confirmation|receipt)#', $p);
    }

    private function parseInstant(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
