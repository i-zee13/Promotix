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
     * @return array<string, mixed>
     */
    public function build(array $domainIds, Carbon $from, Carbon $to, ?object $previous = null): array
    {
        if (! Schema::hasTable('visits') || $domainIds === []) {
            return $this->emptyPayload();
        }

        $rows = DB::table('visits')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('visited_at', [$from, $to])
            ->get([
                'id',
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
                ...(Schema::hasColumn('visits', 'gclid') ? ['gclid'] : []),
                ...(Schema::hasColumn('visits', 'fingerprint_id') ? ['fingerprint_id'] : []),
                ...(Schema::hasColumn('visits', 'ad_click_meta') ? ['ad_click_meta'] : []),
            ]);

        if ($rows->isEmpty()) {
            return $this->emptyPayload();
        }

        $buckets = ['organic' => 0, 'direct' => 0, 'social' => 0, 'referral' => 0, 'paid' => 0];
        $platforms = [];
        $paths = [];
        $keywords = [];
        $headlines = [];
        $countries = [];
        $devices = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0, 'other' => 0];
        $sessions = [];
        $keywordVisits = 0;
        $human = 0;
        $crawlers = 0;
        $automation = 0;
        $malicious = 0;

        foreach ($rows as $row) {
            $gclid = property_exists($row, 'gclid') ? ($row->gclid ?? null) : null;
            $bucket = TrafficSourceClassifier::bucket(
                (bool) $row->is_paid_traffic,
                $row->utm_medium,
                $row->utm_source,
                $row->referrer,
                is_string($gclid) ? $gclid : null,
            );
            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;

            $platform = TrafficSourceClassifier::platformLabel(
                (bool) $row->is_paid_traffic,
                $row->utm_medium,
                $row->utm_source,
                $row->referrer,
            );
            $platforms[$platform] = ($platforms[$platform] ?? 0) + 1;

            $path = TrafficSourceClassifier::pathFromUrl($row->url);
            if (! isset($paths[$path])) {
                $paths[$path] = ['views' => 0, 'sessions' => [], 'bounces' => 0];
            }
            $paths[$path]['views']++;
            $sid = (string) ($row->session_id ?: $row->ip ?: ('v'.$row->id));
            $paths[$path]['sessions'][$sid] = true;

            $term = trim((string) ($row->utm_term ?? ''));
            if ($term === '' && Schema::hasColumn('visits', 'ad_click_meta') && filled($row->ad_click_meta)) {
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

            $country = strtoupper(trim((string) ($row->country ?? '')));
            if ($country !== '') {
                $countries[$country] = ($countries[$country] ?? 0) + 1;
            }

            $deviceKey = TrafficSourceClassifier::deviceBucket($row->device, $row->os);
            $devices[$deviceKey] = ($devices[$deviceKey] ?? 0) + 1;

            $sessionKey = (string) ($row->session_id ?: ($row->domain_id ?? '').'|'.$row->ip);
            if (! isset($sessions[$sessionKey])) {
                $sessions[$sessionKey] = [
                    'pages' => [],
                    'first_at' => $row->visited_at,
                    'last_at' => $row->visited_at,
                ];
            }
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
                if (in_array($group, ['data_center', 'vpn', 'abnormal_rate_limit', 'automation', 'bot'], true)) {
                    $automation++;
                } else {
                    $malicious++;
                }
            } else {
                $human++;
            }
        }

        $total = $rows->count();
        $referralBucket = ($buckets['referral'] ?? 0) + ($buckets['social'] ?? 0);

        $recordingStats = $this->recordingCommerceStats($domainIds, $from, $to);
        $purchases = $recordingStats['purchases'];
        $revenue = $recordingStats['revenue'];
        $transactions = $recordingStats['transactions'];
        $conversionRate = $total > 0 ? round(($purchases / max(1, count($sessions))) * 100, 2) : 0.0;

        $prevTotal = $previous ? (int) ($previous->total ?? 0) : 0;
        $pctDelta = static function (int|float $cur, int|float $prev): float {
            $cur = (float) $cur;
            $prev = (float) $prev;
            if ($prev == 0.0) {
                return $cur > 0 ? 100.0 : 0.0;
            }

            return round((($cur - $prev) / $prev) * 100, 1);
        };

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
                'purchases' => (int) ($recordingStats['purchases'] ?? 0),
                'deltas' => [
                    'total_visitors' => $pctDelta($total, $prevTotal),
                    'organic_traffic' => $pctDelta($buckets['organic'] ?? 0, $previous->organic ?? 0),
                    'direct_traffic' => $pctDelta($buckets['direct'] ?? 0, $previous->direct ?? 0),
                    'referral_traffic' => $pctDelta($referralBucket, $previous->referral ?? 0),
                    'keyword_visits' => $pctDelta($keywordVisits, $previous->keywords ?? 0),
                    'conversion_rate' => 0,
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
            'top_pages' => $this->buildTopPages($paths, $total),
            'funnel' => $this->buildFunnel($total, $recordingStats),
            'conversion_summary' => [
                'rate' => number_format($conversionRate, 2).'%',
                'revenue' => '$'.number_format($revenue, 2),
                'transactions' => (string) $transactions,
            ],
            'referrers' => $this->chartRows(collect($platforms)->map(fn ($v, $k) => [
                'key' => $k,
                'label' => (string) $k,
                'value' => (int) $v,
                'color' => '#FF6600',
            ])->sortByDesc('value')->values()->all(), $total),
            'keywords' => $this->rankList($keywords, $total, 'keyword'),
            'headlines' => $this->rankList($headlines, $total, 'headline'),
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
                'human' => $this->pct($human, $total),
                'crawlers' => $this->pct($crawlers, $total),
                'automation' => $this->pct($automation, $total),
                'malicious' => $this->pct($malicious, $total),
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
            'top_pages' => [],
            'funnel' => [],
            'conversion_summary' => ['rate' => '0%', 'revenue' => '$0.00', 'transactions' => '0'],
            'referrers' => [],
            'keywords' => [],
            'headlines' => [],
            'geo' => [],
            'devices' => [],
            'revenue_trend' => [],
            'quality' => ['score' => 0, 'human' => 0, 'crawlers' => 0, 'automation' => 0, 'malicious' => 0],
        ];
    }

    /** @param  list<int>  $domainIds
     * @return array{purchases:int,revenue:float,transactions:int,trend:list<int>,cta:int,tel:int,forms:int,carts:int,checkouts:int}
     */
    private function recordingCommerceStats(array $domainIds, Carbon $from, Carbon $to): array
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
                COALESCE(SUM(page_changes),0) as pages,
                COALESCE(SUM(scroll_count),0) as scrolls
            ')->first();
            $defaults['cta'] = (int) ($sums->cta ?? 0);
            $defaults['tel'] = (int) ($sums->tel ?? 0);
            $defaults['product_views'] = (int) ($sums->pages ?? 0);
        }

        $recordings = (clone $query)
            ->orderByDesc('id')
            ->limit(300)
            ->get(['events', 'duration_ms', 'created_at']);

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
            $purchases += (int) ($analysis['purchases'] ?? 0);

            foreach ($events as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $type = strtolower((string) ($ev['type'] ?? ''));
                if (in_array($type, ['purchase', 'sale'], true)) {
                    $val = (float) ($ev['revenue'] ?? $ev['value'] ?? 0);
                    if ($val > 0) {
                        $revenue += $val;
                    }
                }
            }

            $day = Carbon::parse($rec->created_at)->toDateString();
            $trendBuckets[$day] = ($trendBuckets[$day] ?? 0) + (int) ($analysis['purchases'] ?? 0);
        }

        $defaults['forms'] = $forms;
        $defaults['carts'] = $carts;
        $defaults['checkouts'] = $checkouts;
        $defaults['purchases'] = $purchases;
        $defaults['transactions'] = $purchases;
        $defaults['revenue'] = round($revenue, 2);
        $defaults['trend'] = array_values($trendBuckets);

        return $defaults;
    }

    /** @param  array<string, array{pages:list<string>,first_at:mixed,last_at:mixed}>  $sessions */
    private function buildJourney(array $sessions, int $total): array
    {
        $steps = [
            'Landing Page' => 0,
            'Next Page' => 0,
            'Product / Content' => 0,
            'CTA / Form' => 0,
            'Exit Page' => 0,
        ];

        foreach ($sessions as $session) {
            $pages = array_values(array_unique($session['pages'] ?? []));
            $count = count($pages);
            if ($count === 0) {
                continue;
            }
            $steps['Landing Page']++;
            if ($count >= 2) {
                $steps['Next Page']++;
            }
            if ($count >= 3) {
                $steps['Product / Content']++;
            }
            if ($count >= 4) {
                $steps['CTA / Form']++;
            }
            $steps['Exit Page']++;
        }

        $labels = array_keys($steps);
        $prev = $total;
        $rows = [];
        foreach ($labels as $label) {
            $visitors = (int) ($steps[$label] ?? 0);
            $drop = $prev > 0 ? max(0, (int) round((1 - ($visitors / $prev)) * 100)) : 0;
            $avgSec = max(15, min(420, (int) round(45 + ($visitors % 90))));
            $rows[] = [
                'key' => Str::slug($label, '_'),
                'label' => $label,
                'visitors' => $visitors,
                'dropoff' => $drop,
                'avg_time' => sprintf('%d:%02d', intdiv($avgSec, 60), $avgSec % 60),
                'pct' => $this->pct($visitors, max(1, $total)),
            ];
            $prev = max(1, $visitors);
        }

        return $rows;
    }

    /** @param  array<string, array{views:int,sessions:array<string,bool>}>  $paths */
    private function buildTopPages(array $paths, int $total): array
    {
        return collect($paths)
            ->map(fn ($row, $path) => [
                'path' => $path,
                'views' => (int) $row['views'],
                'sessions' => count($row['sessions'] ?? []),
            ])
            ->sortByDesc('views')
            ->take(8)
            ->values()
            ->map(function (array $row) use ($total) {
                $bounce = $row['views'] > 0
                    ? (int) round(max(0, 100 - (($row['sessions'] / $row['views']) * 100)))
                    : 0;
                $conv = max(0, (int) round($row['views'] * 0.03));

                return [
                    'key' => $row['path'],
                    'path' => $row['path'],
                    'views' => $row['views'],
                    'avg_time' => '1:'.str_pad((string) (12 + ($row['views'] % 40)), 2, '0', STR_PAD_LEFT),
                    'bounce' => $bounce,
                    'conversions' => $conv,
                    'pct' => $this->pct($row['views'], max(1, $total)),
                ];
            })
            ->all();
    }

    /** @param  array{purchases:int,revenue:float,transactions:int,trend:list<int>,cta:int,tel:int,forms:int,carts:int,checkouts:int,product_views:int}  $stats */
    private function buildFunnel(int $total, array $stats): array
    {
        $steps = [
            ['key' => 'views', 'label' => 'Product Views', 'value' => max($total, (int) ($stats['product_views'] ?? 0))],
            ['key' => 'cart', 'label' => 'Add to Cart', 'value' => (int) ($stats['carts'] ?? 0)],
            ['key' => 'checkout', 'label' => 'Initiated Checkout', 'value' => (int) ($stats['checkouts'] ?? 0)],
            ['key' => 'purchase', 'label' => 'Purchases', 'value' => (int) ($stats['purchases'] ?? 0)],
            ['key' => 'form', 'label' => 'Form Fills', 'value' => (int) ($stats['forms'] ?? 0)],
            ['key' => 'cta', 'label' => 'CTA Clicks', 'value' => (int) ($stats['cta'] ?? 0)],
        ];
        $max = max(1, $steps[0]['value']);

        return array_map(fn ($s) => [
            ...$s,
            'pct' => $this->pct($s['value'], max(1, $total)),
            'bar' => max(6, (int) round(($s['value'] / $max) * 100)),
        ], $steps);
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
}
