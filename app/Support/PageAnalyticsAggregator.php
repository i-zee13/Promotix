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
     * @param  array{clicks?:int,cost?:float,impressions?:int}|null  $adsTotals
     * @return array<string, mixed>
     */
    public function build(
        array|\Illuminate\Support\Collection $domainIds,
        Carbon $from,
        Carbon $to,
        ?object $previous = null,
        array $filters = [],
        string $currencyCode = 'USD',
        ?array $adsTotals = null,
    ): array {
        $domainIds = collect($domainIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
        $currencyCode = AccountCurrency::normalize($currencyCode);

        if (! Schema::hasTable('visits') || $domainIds === []) {
            return $this->emptyPayload($currencyCode);
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
            return $this->emptyPayload($currencyCode);
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
        $adsCountries = [];
        $devices = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0, 'other' => 0];
        $sessions = [];
        $keywordVisits = 0;
        $keywordSessionMap = [];
        $human = 0;
        $crawlers = 0;
        $automation = 0;
        $malicious = 0;
        $validUsers = 0;
        $productViews = 0;
        $filtered = collect();
        $performanceBuckets = [];
        $hourly = $from->toDateString() === $to->toDateString();

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
                $sidForKw = (string) ($row->session_id ?: ($row->domain_id.'|'.$row->ip) ?: ('v'.$row->id));
                $keywordSessionMap[$term][$sidForKw] = true;
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
                if ($bucket === 'paid' || (bool) $row->is_paid_traffic) {
                    $adsCountries[$country] = ($adsCountries[$country] ?? 0) + 1;
                }
            }

            $devices[$deviceKey] = ($devices[$deviceKey] ?? 0) + 1;

            $visitedAt = $this->parseInstant($row->visited_at);
            if ($visitedAt) {
                $bucketKey = $hourly
                    ? $visitedAt->format('Y-m-d H:00:00')
                    : $visitedAt->toDateString();
                if (! isset($performanceBuckets[$bucketKey])) {
                    $performanceBuckets[$bucketKey] = [
                        'visitors' => 0,
                        'clicks' => 0,
                        'conversions' => 0,
                        'valid' => 0,
                        'paid' => 0,
                    ];
                }
                $performanceBuckets[$bucketKey]['visitors']++;
                if ($bucket === 'paid' || (bool) $row->is_paid_traffic) {
                    $performanceBuckets[$bucketKey]['clicks']++;
                    $performanceBuckets[$bucketKey]['paid']++;
                }
            }

            $sessionKey = (string) ($row->session_id ?: ($row->domain_id.'|'.$row->ip));
            if (! isset($sessions[$sessionKey])) {
                $sessions[$sessionKey] = [
                    'events' => [],
                    'pages' => [],
                    'first_at' => $row->visited_at,
                    'last_at' => $row->visited_at,
                    'platform' => $platform,
                    'bucket' => $bucket,
                    'ip' => $row->ip,
                    'device' => $deviceKey,
                    'browser' => $row->browser ?? null,
                    'os' => $row->os ?? null,
                    'country' => $country !== '' ? $country : null,
                    'is_paid' => $bucket === 'paid' || (bool) $row->is_paid_traffic,
                    'is_valid' => ! (bool) ($row->is_crawler ?? false) && ! (bool) $row->is_invalid_traffic,
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
            if ($bucket === 'paid' || (bool) $row->is_paid_traffic) {
                $sessions[$sessionKey]['is_paid'] = true;
            }
            if (! (bool) ($row->is_crawler ?? false) && ! (bool) $row->is_invalid_traffic) {
                $sessions[$sessionKey]['is_valid'] = ($sessions[$sessionKey]['is_valid'] ?? true);
            } else {
                $sessions[$sessionKey]['is_valid'] = false;
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
                if ($bucket === 'paid' || (bool) $row->is_paid_traffic) {
                    $validUsers++;
                    if ($visitedAt) {
                        $bucketKey = $hourly
                            ? $visitedAt->format('Y-m-d H:00:00')
                            : $visitedAt->toDateString();
                        $performanceBuckets[$bucketKey]['valid'] = ($performanceBuckets[$bucketKey]['valid'] ?? 0) + 1;
                    }
                }
            }
        }

        $rows = $filtered;
        if ($rows->isEmpty()) {
            return $this->emptyPayload($currencyCode);
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

        $telClicks = (int) ($recordingStats['tel'] ?? 0);
        $ctaClicks = (int) ($recordingStats['cta'] ?? 0);
        $formFills = (int) ($recordingStats['forms'] ?? 0);
        $carts = (int) ($recordingStats['carts'] ?? 0);
        $checkouts = (int) ($recordingStats['checkouts'] ?? 0);

        // Align funnel with journey: count sessions that hit CTA/form-like paths when
        // session-recording events are missing (common when tag fires pageviews only).
        $ctaFormSessions = 0;
        $formPathSessions = 0;
        $ctaPathSessions = 0;
        foreach ($sessions as $session) {
            $hitForm = false;
            $hitCta = false;
            foreach ($session['pages'] ?? [] as $pagePath) {
                $path = (string) $pagePath;
                if ($this->looksLikeCtaOrFormPath($path)) {
                    if (preg_match('#(form|contact|enquiry|inquiry|register|signup|sign-up|subscribe|apply|quote)#i', $path)) {
                        $hitForm = true;
                    } else {
                        $hitCta = true;
                    }
                }
                if ($this->looksLikeConversionPath($path)) {
                    $hitCta = true;
                }
            }
            if ($hitForm || $hitCta) {
                $ctaFormSessions++;
            }
            if ($hitForm) {
                $formPathSessions++;
            }
            if ($hitCta) {
                $ctaPathSessions++;
            }
        }
        if ($formFills === 0 && $formPathSessions > 0) {
            $formFills = $formPathSessions;
        }
        if ($ctaClicks === 0 && $ctaPathSessions > 0) {
            $ctaClicks = $ctaPathSessions;
        }
        if ($formFills === 0 && $ctaClicks === 0 && $ctaFormSessions > 0) {
            // Split ambiguous CTA/form path hits evenly so funnel isn't empty while journey shows activity.
            $formFills = (int) ceil($ctaFormSessions / 2);
            $ctaClicks = (int) floor($ctaFormSessions / 2);
        }

        // Total Conversions = every conversion-funnel action (call, CTA, form, cart, checkout, purchase).
        $totalConversions = $telClicks + $ctaClicks + $formFills + $carts + $checkouts + $purchases;

        // Valid Users = ad visitors who are not invalid/crawler. Fallback to human when no paid traffic.
        if ($validUsers === 0 && $human > 0 && ($buckets['paid'] ?? 0) === 0) {
            $validUsers = $human;
        }

        $liveVisitors = $this->countLiveVisitors($domainIds, 5);
        $inactiveVisitors = max(0, $total - $liveVisitors);

        $googleClicks = (int) ($adsTotals['clicks'] ?? 0);
        $googleCost = (float) ($adsTotals['cost'] ?? 0);
        $avgCpc = $googleClicks > 0 ? round($googleCost / $googleClicks, 4) : 0.0;
        $costPerConversion = $totalConversions > 0
            ? round($googleCost / $totalConversions, 4)
            : 0.0;

        foreach ($convertingSessions as $sid => $_) {
            $session = $sessions[$sid] ?? null;
            if (! $session) {
                continue;
            }
            $at = $this->parseInstant($session['last_at'] ?? null);
            if (! $at) {
                continue;
            }
            $bucketKey = $hourly ? $at->format('Y-m-d H:00:00') : $at->toDateString();
            if (! isset($performanceBuckets[$bucketKey])) {
                $performanceBuckets[$bucketKey] = [
                    'visitors' => 0,
                    'clicks' => 0,
                    'conversions' => 0,
                    'valid' => 0,
                    'paid' => 0,
                ];
            }
            $performanceBuckets[$bucketKey]['conversions']++;
        }

        $keywordRows = $this->rankKeywordPerformance($keywords, $keywordSessionMap, $convertingSessions, $total);

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
        $geoSource = $adsCountries !== [] ? $adsCountries : [];
        $geoTotal = max(1, array_sum($geoSource) ?: 1);

        return [
            'kpis' => [
                'live_visitors' => $liveVisitors,
                'total_visitors' => $total,
                'inactive_visitors' => $inactiveVisitors,
                'valid_users' => $validUsers,
                'total_conversions' => $totalConversions,
                'organic_traffic' => (int) ($buckets['organic'] ?? 0),
                'direct_traffic' => (int) ($buckets['direct'] ?? 0),
                'referral_traffic' => $referralBucket,
                'keyword_visits' => $keywordVisits,
                'conversion_rate' => $conversionRate,
                'cta_clicks' => $ctaClicks,
                'tel_clicks' => $telClicks,
                'form_submits' => $formFills,
                'purchases' => $purchases,
                'deltas' => [
                    'live_visitors' => 0.0,
                    'total_visitors' => $pctDelta($total, $prevTotal),
                    'valid_users' => $pctDelta($validUsers, $previous->valid_users ?? 0),
                    'total_conversions' => $pctDelta($totalConversions, $previous->total_conversions ?? 0),
                    'organic_traffic' => $pctDelta($buckets['organic'] ?? 0, $previous->organic ?? 0),
                    'direct_traffic' => $pctDelta($buckets['direct'] ?? 0, $previous->direct ?? 0),
                    'referral_traffic' => $pctDelta($referralBucket, $previous->referral ?? 0),
                    'keyword_visits' => $pctDelta($keywordVisits, $previous->keywords ?? 0),
                    'conversion_rate' => $pctDelta($conversionRate, $previous->conversion_rate ?? 0),
                    'cost_per_conversion' => 0.0,
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
                'tel' => $telClicks,
            ])),
            'conversion_summary' => [
                'rate' => number_format($conversionRate, 2).'%',
                'revenue' => AccountCurrency::formatAmount($revenue, $currencyCode),
                'transactions' => (string) $transactions,
                'aov' => AccountCurrency::formatAmount($aov, $currencyCode),
                'rate_raw' => $conversionRate,
                'revenue_raw' => $revenue,
                'transactions_raw' => $transactions,
                'aov_raw' => $aov,
                'currency_code' => $currencyCode,
                'currency_symbol' => AccountCurrency::symbol($currencyCode),
            ],
            'cost' => [
                'avg_cpc' => $avgCpc,
                'total_cost' => round($googleCost, 2),
                'cost_per_conversion' => $costPerConversion,
                'google_clicks' => $googleClicks,
                'conversions' => $totalConversions,
                'avg_cpc_label' => AccountCurrency::formatCompact($avgCpc, $currencyCode),
                'total_cost_label' => AccountCurrency::formatCompact($googleCost, $currencyCode),
                'cost_per_conversion_label' => AccountCurrency::formatCompact($costPerConversion, $currencyCode),
                'currency_code' => $currencyCode,
                'currency_symbol' => AccountCurrency::symbol($currencyCode),
                'delta' => 0.0,
            ],
            'performance' => [
                'granularity' => $hourly ? 'hourly' : 'daily',
                'labels' => array_values(array_keys($this->sortedPerformanceBuckets($performanceBuckets, $from, $to, $hourly))),
                'series' => $this->buildPerformanceSeries(
                    $performanceBuckets,
                    $from,
                    $to,
                    $hourly,
                    $this->googleClicksByDay($domainIds, $from, $to),
                ),
            ],
            'referrers' => $this->chartRows(collect($platforms)->map(fn ($v, $k) => [
                'key' => $k,
                'label' => (string) $k,
                'value' => (int) $v,
                'color' => '#FF6600',
            ])->sortByDesc('value')->values()->all(), $total),
            'keywords' => $keywordRows,
            'headlines' => $this->rankList($headlines, $total, 'headline'),
            'keyword_headlines' => $this->rankComboList($keywordHeadlines, $total),
            ...$this->buildSiteKeywordHeadlineStats($domainIds, $from, $to, $filters),
            'geo' => $this->chartRows(collect($geoSource)->map(fn ($v, $k) => [
                'key' => $k,
                'code' => $k,
                'name' => $k,
                'label' => $k,
                'value' => (int) $v,
                'color' => '#FF6600',
            ])->sortByDesc('value')->take(8)->values()->all(), $geoTotal),
            'devices' => $this->chartRows([
                ['key' => 'mobile', 'label' => 'Mobile', 'value' => (int) ($devices['mobile'] ?? 0), 'color' => '#FF6600'],
                ['key' => 'desktop', 'label' => 'Desktop', 'value' => (int) ($devices['desktop'] ?? 0), 'color' => '#3B82F6'],
                ['key' => 'tablet', 'label' => 'Tablet', 'value' => (int) ($devices['tablet'] ?? 0), 'color' => '#A855F7'],
                ['key' => 'other', 'label' => 'Other', 'value' => (int) ($devices['other'] ?? 0), 'color' => '#94A3B8'],
            ], $total),
            'revenue_trend' => $recordingStats['trend'],
            'currency_code' => $currencyCode,
            'currency_symbol' => AccountCurrency::symbol($currencyCode),
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
                // Gauge-friendly aliases for Traffic Control mock
                'crawler_score' => $this->pct($crawlers, $total),
                'automation_score' => $this->pct($automation, $total),
                'malicious_score' => $this->pct($malicious, $total),
            ],
            'engagement' => $this->buildEngagementDistribution($sessions),
            'top_landing_pages' => $this->buildTopLandingPages($paths, $sessionCount),
            'journey_paths' => $this->buildJourneyPaths($sessions),
            'top_exit_pages' => $this->buildTopExitPages($sessions, $sessionCount),
            'conversion_by_source' => $this->buildConversionBySource($sessions, $convertingSessions, $recordingStats),
            'high_value_sessions' => $recordingStats['high_value_sessions'] ?? [],
            'pages_per_session' => round($total / max(1, $sessionCount), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyPayload(string $currencyCode = 'USD'): array
    {
        $currencyCode = AccountCurrency::normalize($currencyCode);
        $zeroMoney = AccountCurrency::formatAmount(0, $currencyCode);

        return [
            'kpis' => [
                'live_visitors' => 0,
                'total_visitors' => 0,
                'inactive_visitors' => 0,
                'valid_users' => 0,
                'total_conversions' => 0,
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
                'revenue' => $zeroMoney,
                'transactions' => '0',
                'aov' => $zeroMoney,
                'rate_raw' => 0,
                'revenue_raw' => 0,
                'transactions_raw' => 0,
                'aov_raw' => 0,
                'currency_code' => $currencyCode,
                'currency_symbol' => AccountCurrency::symbol($currencyCode),
            ],
            'cost' => [
                'avg_cpc' => 0.0,
                'total_cost' => 0.0,
                'cost_per_conversion' => 0.0,
                'google_clicks' => 0,
                'conversions' => 0,
                'avg_cpc_label' => AccountCurrency::formatCompact(0, $currencyCode),
                'total_cost_label' => AccountCurrency::formatCompact(0, $currencyCode),
                'cost_per_conversion_label' => AccountCurrency::formatCompact(0, $currencyCode),
                'currency_code' => $currencyCode,
                'currency_symbol' => AccountCurrency::symbol($currencyCode),
                'delta' => 0.0,
            ],
            'performance' => [
                'granularity' => 'hourly',
                'labels' => [],
                'series' => [],
            ],
            'referrers' => [],
            'keywords' => [],
            'headlines' => [],
            'keyword_headlines' => [],
            'site_keywords' => [],
            'site_headlines' => [],
            'site_keyword_headlines' => [],
            'geo' => [],
            'devices' => [],
            'revenue_trend' => [],
            'currency_code' => $currencyCode,
            'currency_symbol' => AccountCurrency::symbol($currencyCode),
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
                'crawler_score' => 0,
                'automation_score' => 0,
                'malicious_score' => 0,
            ],
            'engagement' => [],
            'top_landing_pages' => [],
            'journey_paths' => [],
            'top_exit_pages' => [],
            'conversion_by_source' => [],
            'high_value_sessions' => [],
            'pages_per_session' => 0,
        ];
    }


    /** @param  list<int>  $domainIds */
    private function countLiveVisitors(array $domainIds, int $minutes = 5): int
    {
        if ($domainIds === [] || ! Schema::hasTable('visits')) {
            return 0;
        }

        return (int) DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->where('visited_at', '>=', now()->subMinutes(max(1, $minutes)))
            ->count();
    }

    /**
     * @param  array<string, int>  $keywords
     * @param  array<string, array<string, bool>>  $keywordSessionMap
     * @param  array<string, bool>  $convertingSessions
     * @return list<array<string, mixed>>
     */
    private function rankKeywordPerformance(array $keywords, array $keywordSessionMap, array $convertingSessions, int $total): array
    {
        $rows = [];
        foreach ($keywords as $keyword => $clicks) {
            $sessions = array_keys($keywordSessionMap[$keyword] ?? []);
            $conversions = 0;
            foreach ($sessions as $sid) {
                if (isset($convertingSessions[$sid])) {
                    $conversions++;
                }
            }
            $rows[] = [
                'key' => (string) $keyword,
                'keyword' => (string) $keyword,
                'label' => (string) $keyword,
                'value' => (int) $clicks,
                'clicks' => (int) $clicks,
                'conversions' => $conversions,
                'pct' => $this->pct((int) $clicks, max(1, $total)),
            ];
        }

        usort($rows, fn ($a, $b) => ($b['clicks'] <=> $a['clicks']));

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     * @return array<string, array<string, int>>
     */
    private function sortedPerformanceBuckets(array $buckets, Carbon $from, Carbon $to, bool $hourly): array
    {
        $filled = [];
        $cursor = $hourly ? $from->copy()->startOfHour() : $from->copy()->startOfDay();
        $end = $hourly ? $to->copy()->endOfHour() : $to->copy()->endOfDay();

        while ($cursor <= $end) {
            $key = $hourly ? $cursor->format('Y-m-d H:00:00') : $cursor->toDateString();
            $filled[$key] = $buckets[$key] ?? [
                'visitors' => 0,
                'clicks' => 0,
                'conversions' => 0,
                'valid' => 0,
                'paid' => 0,
            ];
            $hourly ? $cursor->addHour() : $cursor->addDay();
            if (count($filled) > 48) {
                break;
            }
        }

        return $filled;
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     * @param  array<string, int>  $googleClicksByDay  metric_date => clicks
     * @return list<array<string, mixed>>
     */
    private function buildPerformanceSeries(
        array $buckets,
        Carbon $from,
        Carbon $to,
        bool $hourly,
        array $googleClicksByDay = [],
    ): array {
        $filled = $this->sortedPerformanceBuckets($buckets, $from, $to, $hourly);
        $labels = [];
        $visitors = [];
        $clicks = [];
        $conversions = [];
        $valid = [];
        $paid = [];
        $hasGoogleClicks = $googleClicksByDay !== [];

        foreach ($filled as $key => $row) {
            $dayKey = $hourly
                ? Carbon::parse($key)->toDateString()
                : (strlen($key) >= 10 ? substr($key, 0, 10) : $key);
            $labels[] = $hourly
                ? Carbon::parse($key)->format('g A')
                : Carbon::parse($key)->format('M j');
            $visitors[] = (int) ($row['visitors'] ?? 0);
            // Prefer Google Ads reported clicks per day; fall back to paid visit clicks.
            if ($hasGoogleClicks && ! $hourly) {
                $clicks[] = (int) ($googleClicksByDay[$dayKey] ?? 0);
            } elseif ($hasGoogleClicks && $hourly) {
                // Spread the day's Google clicks across hours proportional to paid activity.
                $dayTotal = (int) ($googleClicksByDay[$dayKey] ?? 0);
                $dayPaid = 0;
                foreach ($filled as $k2 => $r2) {
                    if (Carbon::parse($k2)->toDateString() === $dayKey) {
                        $dayPaid += (int) ($r2['paid'] ?? 0);
                    }
                }
                $hourPaid = (int) ($row['paid'] ?? 0);
                $clicks[] = ($dayPaid > 0 && $dayTotal > 0)
                    ? (int) round($dayTotal * ($hourPaid / $dayPaid))
                    : (int) ($row['clicks'] ?? 0);
            } else {
                $clicks[] = (int) ($row['clicks'] ?? 0);
            }
            $conversions[] = (int) ($row['conversions'] ?? 0);
            $valid[] = (int) ($row['valid'] ?? 0);
            $paid[] = (int) ($row['paid'] ?? 0);
        }

        return [
            ['key' => 'clicks', 'label' => 'Clicks', 'color' => '#4285F4', 'scheme' => 'blue', 'total' => array_sum($clicks), 'points' => $clicks, 'labels' => $labels],
            ['key' => 'visitors', 'label' => 'Visitors', 'color' => '#EA4335', 'scheme' => 'red', 'total' => array_sum($visitors), 'points' => $visitors, 'labels' => $labels],
            ['key' => 'conversions', 'label' => 'Conversions', 'color' => '#FF6600', 'scheme' => 'orange', 'total' => array_sum($conversions), 'points' => $conversions, 'labels' => $labels],
            // White card in UI; chart stroke stays light-gray so it remains visible on dark canvas.
            ['key' => 'valid', 'label' => 'Valid Users', 'color' => '#CBD5E1', 'scheme' => 'white', 'total' => array_sum($valid), 'points' => $valid, 'labels' => $labels],
        ];
    }

    /**
     * @param  list<int>  $domainIds
     * @return array<string, int>
     */
    private function googleClicksByDay(array $domainIds, Carbon $from, Carbon $to): array
    {
        if ($domainIds === [] || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return [];
        }

        $rows = DB::table('google_ads_campaign_daily_metrics')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->when(
                Schema::hasColumn('google_ads_campaign_daily_metrics', 'clicks'),
                fn ($q) => $q->selectRaw('metric_date, SUM(clicks) as clicks'),
                fn ($q) => $q->selectRaw('metric_date, 0 as clicks')
            )
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $date = Carbon::parse((string) $row->metric_date)->toDateString();
            $map[$date] = (int) ($row->clicks ?? 0);
        }

        return $map;
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

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($inner) use ($q): void {
                if (Schema::hasColumn('visits', 'url')) {
                    $inner->orWhere('url', 'like', '%'.$q.'%');
                }
                if (Schema::hasColumn('visits', 'utm_term')) {
                    $inner->orWhere('utm_term', 'like', '%'.$q.'%');
                }
                if (Schema::hasColumn('visits', 'utm_campaign')) {
                    $inner->orWhere('utm_campaign', 'like', '%'.$q.'%');
                }
                if (Schema::hasColumn('visits', 'referrer')) {
                    $inner->orWhere('referrer', 'like', '%'.$q.'%');
                }
            });
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
            'high_value_sessions' => [],
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
        if (Schema::hasColumn('visit_session_recordings', 'session_id')) {
            $cols[] = 'session_id';
        }
        if (Schema::hasColumn('visit_session_recordings', 'device')) {
            $cols[] = 'device';
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
        $highValue = [];

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
            $product = null;
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
                    $product = $product ?: ($ev['product'] ?? $ev['name'] ?? $ev['item'] ?? null);
                }
            }

            if ($dayRevenue > 0 || $dayPurchases > 0) {
                $highValue[] = [
                    'session_id' => $rec->session_id ?? null,
                    'ip' => $rec->ip ?? null,
                    'revenue' => round($dayRevenue, 2),
                    'revenue_label' => '$'.number_format($dayRevenue, 2),
                    'product' => $product ? (string) $product : 'Purchase',
                    'device' => $rec->device ?? '—',
                    'at' => (string) ($rec->created_at ?? ''),
                ];
            }

            $day = Carbon::parse($rec->created_at)->toDateString();
            $trendBuckets[$day] = ($trendBuckets[$day] ?? 0) + ($dayRevenue > 0 ? $dayRevenue : $dayPurchases);
        }

        usort($highValue, fn ($a, $b) => ($b['revenue'] <=> $a['revenue']));
        $defaults['high_value_sessions'] = array_slice($highValue, 0, 5);

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

    /** @param  array<string, array{pages?:list<string>,first_at?:mixed,last_at?:mixed}>  $sessions */
    private function buildEngagementDistribution(array $sessions): array
    {
        $bounced = 0;
        $engaged = 0;
        $high = 0;
        foreach ($sessions as $session) {
            $pages = array_values(array_unique($session['pages'] ?? []));
            $pageCount = count($pages);
            $start = $this->parseInstant($session['first_at'] ?? null);
            $end = $this->parseInstant($session['last_at'] ?? null);
            $secs = ($start && $end) ? max(0, $end->diffInSeconds($start)) : 0;

            if ($pageCount <= 1 && $secs < 30) {
                $bounced++;
            } elseif ($pageCount >= 4 || $secs >= 180) {
                $high++;
            } else {
                $engaged++;
            }
        }
        $total = max(1, $bounced + $engaged + $high);

        return $this->chartRows([
            ['key' => 'bounced', 'label' => 'Bounced', 'value' => $bounced, 'color' => '#94A3B8'],
            ['key' => 'engaged', 'label' => 'Engaged', 'value' => $engaged, 'color' => '#3B82F6'],
            ['key' => 'highly_engaged', 'label' => 'Highly Engaged', 'value' => $high, 'color' => '#FF6600'],
        ], $total);
    }

    /**
     * @param  array<string, array{entry_sessions?:array<string,bool>}>  $paths
     */
    private function buildTopLandingPages(array $paths, int $sessionCount): array
    {
        return collect($paths)
            ->map(fn ($row, $path) => [
                'path' => $path,
                'value' => count($row['entry_sessions'] ?? []),
            ])
            ->filter(fn ($r) => $r['value'] > 0)
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->map(fn ($r) => [
                'key' => $r['path'],
                'path' => $r['path'],
                'label' => $r['path'],
                'value' => $r['value'],
                'pct' => $this->pct($r['value'], max(1, $sessionCount)),
            ])
            ->all();
    }

    /** @param  array<string, array{pages?:list<string>}>  $sessions */
    private function buildJourneyPaths(array $sessions): array
    {
        $counts = [];
        foreach ($sessions as $session) {
            $pages = array_values(array_unique($session['pages'] ?? []));
            if (count($pages) < 2) {
                continue;
            }
            $flow = implode(' → ', array_slice($pages, 0, 5));
            $counts[$flow] = ($counts[$flow] ?? 0) + 1;
        }

        return collect($counts)
            ->sortByDesc(fn ($v) => $v)
            ->take(8)
            ->map(fn ($value, $path) => [
                'key' => md5((string) $path),
                'path' => (string) $path,
                'label' => (string) $path,
                'value' => (int) $value,
            ])
            ->values()
            ->all();
    }

    /** @param  array<string, array{pages?:list<string>,events?:list<array{path:string,at:mixed}>}>  $sessions */
    private function buildTopExitPages(array $sessions, int $sessionCount): array
    {
        $exits = [];
        foreach ($sessions as $session) {
            $events = $session['events'] ?? [];
            usort($events, fn ($a, $b) => strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? '')));
            $exit = $events !== []
                ? (string) ($events[count($events) - 1]['path'] ?? '')
                : (string) (array_values(array_unique($session['pages'] ?? []))[count(array_unique($session['pages'] ?? [])) - 1] ?? '');
            if ($exit === '') {
                continue;
            }
            $exits[$exit] = ($exits[$exit] ?? 0) + 1;
        }

        return collect($exits)
            ->sortByDesc(fn ($v) => $v)
            ->take(8)
            ->map(fn ($value, $path) => [
                'key' => (string) $path,
                'path' => (string) $path,
                'label' => (string) $path,
                'value' => (int) $value,
                'pct' => $this->pct((int) $value, max(1, $sessionCount)),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{bucket?:string,platform?:string}>  $sessions
     * @param  array<string, bool>  $convertingSessions
     * @param  array<string, mixed>  $recordingStats
     */
    private function buildConversionBySource(array $sessions, array $convertingSessions, array $recordingStats): array
    {
        $bySource = [];
        foreach ($sessions as $sid => $session) {
            $label = (string) ($session['platform'] ?? $session['bucket'] ?? 'Direct');
            if ($label === '') {
                $label = 'Direct';
            }
            if (! isset($bySource[$label])) {
                $bySource[$label] = ['visits' => 0, 'conversions' => 0];
            }
            $bySource[$label]['visits']++;
            if (isset($convertingSessions[$sid])) {
                $bySource[$label]['conversions']++;
            }
        }

        $totalRevenue = (float) ($recordingStats['revenue'] ?? 0);
        $totalConv = max(1, (int) array_sum(array_map(fn ($r) => (int) ($r['conversions'] ?? 0), $bySource)));

        return collect($bySource)
            ->sortByDesc('visits')
            ->take(8)
            ->map(function ($row, $label) use ($totalRevenue, $totalConv) {
                $visits = (int) $row['visits'];
                $conv = (int) $row['conversions'];
                $rate = $visits > 0 ? round(($conv / $visits) * 100, 2) : 0.0;
                $share = $conv / $totalConv;
                $revenue = round($totalRevenue * $share, 2);

                return [
                    'key' => md5((string) $label),
                    'source' => (string) $label,
                    'label' => (string) $label,
                    'visits' => $visits,
                    'conversions' => $conv,
                    'conversion_rate' => $rate,
                    'revenue' => $revenue,
                    'revenue_label' => '$'.number_format($revenue, 2),
                ];
            })
            ->values()
            ->all();
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

            // CTA / Form = sessions that actually hit a form/CTA-like page (not "4th page" heuristic).
            $ctaIdx = null;
            foreach ($events as $idx => $ev) {
                $path = (string) ($ev['path'] ?? '');
                if ($this->looksLikeCtaOrFormPath($path) || $this->looksLikeConversionPath($path)) {
                    $ctaIdx = (int) $idx;
                    break;
                }
            }
            if ($ctaIdx !== null) {
                $steps['CTA / Form']['count']++;
                $steps['CTA / Form']['secs'][] = $dwellAt($ctaIdx);
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
                    'visitors' => $row['sessions'],
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

        $tel = (int) ($stats['tel'] ?? 0);
        $steps = [
            ['key' => 'views', 'label' => 'Product Views', 'value' => $views],
            ['key' => 'cart', 'label' => 'Add to Cart', 'value' => $cart],
            ['key' => 'checkout', 'label' => 'Initiated Checkout', 'value' => $checkout],
            ['key' => 'purchase', 'label' => 'Purchases', 'value' => $purchase],
            ['key' => 'form', 'label' => 'Form Fills', 'value' => $forms],
            ['key' => 'cta', 'label' => 'CTA Clicks', 'value' => $cta],
            ['key' => 'tel', 'label' => 'Call Clicks', 'value' => $tel],
        ];
        $max = max(1, $steps[0]['value']);

        return array_map(fn ($s) => [
            ...$s,
            'pct' => $this->pct($s['value'], max(1, $total)),
            'bar' => max(6, (int) round(($s['value'] / $max) * 100)),
        ], $steps);
    }

    /**
     * On-site titles (document.title) and meta keywords from behavior events.
     *
     * @param  list<int>  $domainIds
     * @param  array<string, string>  $filters
     * @return array{site_keywords: list<array<string, mixed>>, site_headlines: list<array<string, mixed>>, site_keyword_headlines: list<array<string, mixed>>}
     */
    private function buildSiteKeywordHeadlineStats(array $domainIds, Carbon $from, Carbon $to, array $filters): array
    {
        $empty = [
            'site_keywords' => [],
            'site_headlines' => [],
            'site_keyword_headlines' => [],
        ];

        if (! Schema::hasTable('visit_behavior_events') || $domainIds === []) {
            return $empty;
        }

        $query = DB::table('visit_behavior_events')
            ->whereIn('domain_id', $domainIds)
            ->whereIn('event_type', ['page_view', 'page_change'])
            ->whereBetween('occurred_at', [$from, $to]);

        $path = trim((string) ($filters['path'] ?? ''));
        if ($path !== '') {
            $query->where(function ($inner) use ($path): void {
                $inner->where('page_path', 'like', '%'.$path.'%')
                    ->orWhere('page_url', 'like', '%'.$path.'%');
            });
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($inner) use ($q): void {
                $inner->where('title', 'like', '%'.$q.'%')
                    ->orWhere('page_path', 'like', '%'.$q.'%')
                    ->orWhere('payload', 'like', '%'.$q.'%');
            });
        }

        $rows = $query->get(['title', 'page_path', 'payload']);
        if ($rows->isEmpty()) {
            return $empty;
        }

        $keywords = [];
        $headlines = [];
        $keywordHeadlines = [];

        foreach ($rows as $row) {
            $title = trim((string) ($row->title ?? ''));
            if ($title !== '') {
                $headlines[$title] = ($headlines[$title] ?? 0) + 1;
            }

            $pageKeywords = $this->extractSiteKeywords($row);
            foreach ($pageKeywords as $keyword) {
                $keywords[$keyword] = ($keywords[$keyword] ?? 0) + 1;
            }

            if ($title !== '' || $pageKeywords !== []) {
                $primaryKeyword = $pageKeywords[0] ?? '(no keyword)';
                $comboKey = $primaryKeyword.' · '.($title !== '' ? $title : '(no title)');
                $keywordHeadlines[$comboKey] = ($keywordHeadlines[$comboKey] ?? 0) + 1;
            }
        }

        $total = max(1, $rows->count());

        return [
            'site_keywords' => $this->rankList($keywords, $total, 'keyword'),
            'site_headlines' => $this->rankList($headlines, $total, 'headline'),
            'site_keyword_headlines' => $this->rankComboList($keywordHeadlines, $total),
        ];
    }

    /** @return list<string> */
    private function extractSiteKeywords(object $row): array
    {
        $keywords = [];

        if (filled($row->payload ?? null)) {
            $payload = json_decode((string) $row->payload, true);
            if (is_array($payload)) {
                $raw = trim((string) ($payload['meta_keywords'] ?? $payload['keywords'] ?? ''));
                if ($raw !== '') {
                    foreach (preg_split('/[,;|]/', $raw) ?: [] as $part) {
                        $part = trim((string) $part);
                        if ($part !== '') {
                            $keywords[] = $part;
                        }
                    }
                }
            }
        }

        if ($keywords === []) {
            $path = trim((string) ($row->page_path ?? ''), '/');
            if ($path !== '') {
                foreach (preg_split('/[-_\/]+/', $path) ?: [] as $token) {
                    $token = trim((string) $token);
                    if (strlen($token) >= 3 && ! is_numeric($token)) {
                        $keywords[] = $token;
                    }
                }
            }
        }

        return array_values(array_unique($keywords));
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

    /** Paths that imply a form / CTA / lead step (used by journey + funnel). */
    private function looksLikeCtaOrFormPath(string $path): bool
    {
        $p = strtolower(trim($path));
        if ($p === '' || $p === '/') {
            return false;
        }

        return (bool) preg_match(
            '#(contact|form|quote|demo|signup|sign-up|register|apply|book|booking|call|lead|enquiry|inquiry|cta|get-started|get_started|request|subscribe|trial|checkout|cart|insurance-quote|auto-insurance)#',
            $p
        );
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
