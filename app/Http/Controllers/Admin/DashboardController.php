<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Support\DashboardNotifications;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('hostname')
            ->get(['id', 'hostname']);

        return view('dashboard-figma', compact('domains'));
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (Schema::hasTable('visits')) {
            $base = $this->applyVisitFilters(
                DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]),
                $request
            );
            $totalClicks = (clone $base)->count();
            $suspiciousVisits = (clone $base)->where('is_invalid_traffic', true)->count();
            $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
                ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
                : "NULLIF(TRIM(utm_campaign), '')";
            $topCampaign = (clone $base)
                ->whereRaw("{$campaignExpr} IS NOT NULL")
                ->whereRaw("{$campaignExpr} != ''")
                ->selectRaw("{$campaignExpr} as campaign, COUNT(*) as total")
                ->groupByRaw($campaignExpr)
                ->orderByDesc('total')
                ->first();

            $feedQuery = (clone $base)
                ->where(function ($q): void {
                    $q->where('is_invalid_traffic', true)
                        ->orWhere(function ($inner): void {
                            if (Schema::hasColumn('visits', 'threat_score')) {
                                $inner->where('threat_score', '>=', 70);
                            } else {
                                $inner->whereRaw('0 = 1');
                            }
                        });
                })
                ->orderByDesc('visited_at')
                ->limit(12);

            $feed = $feedQuery->get([
                'id',
                'ip',
                'visited_at',
                'threat_score',
                'threat_group',
                'action_taken',
                'detection_reasons',
                'utm_campaign',
                'campaign_name',
                'is_invalid_traffic',
            ])->map(function ($row) use ($user) {
                $reasons = [];
                if (! empty($row->detection_reasons)) {
                    $decoded = json_decode((string) $row->detection_reasons, true);
                    $reasons = is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
                }
                if ($row->threat_group && ! in_array((string) $row->threat_group, $reasons, true)) {
                    $reasons[] = (string) $row->threat_group;
                }
                $score = (int) ($row->threat_score ?? 0);
                if ($score <= 0 && $row->is_invalid_traffic) {
                    $score = 85;
                }
                $severity = $score >= 90 ? 'high' : ($score >= 75 ? 'medium' : 'low');
                $campaign = trim((string) ($row->campaign_name ?: $row->utm_campaign ?: 'Unknown campaign'));
                $action = $row->action_taken ?: ($row->is_invalid_traffic ? 'Blocked' : 'Monitored');

                return [
                    'id' => $row->id,
                    'title' => $score >= 90 ? 'High Risk Click Detected' : ($row->is_invalid_traffic ? 'Invalid Traffic Detected' : 'Suspicious Session Blocked'),
                    'severity' => $severity,
                    'campaign' => $campaign !== '' ? $campaign : 'Unknown campaign',
                    'ip' => (string) ($row->ip ?? ''),
                    'risk' => $score,
                    'reasons' => array_slice($reasons, 0, 4),
                    'action' => ucfirst((string) $action),
                    'at' => UserTimezone::isoForUser(
                        ! empty($row->visited_at) ? Carbon::parse((string) $row->visited_at, 'UTC') : null,
                        $user
                    ),
                ];
            })->values();
        } else {
            $totalClicks = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->whereBetween('clicked_at', [$from, $to])
                ->count();
            $suspiciousVisits = PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('threat_group')
                ->whereBetween('last_click_at', [$from, $to])
                ->count();
            $topCampaign = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->whereBetween('clicked_at', [$from, $to])
                ->whereNotNull('campaign')
                ->select('campaign', DB::raw('COUNT(*) as total'))
                ->groupBy('campaign')
                ->orderByDesc('total')
                ->first();
            $feed = collect();
        }

        return response()->json([
            'totalClicks' => (int) $totalClicks,
            'suspiciousVisits' => (int) $suspiciousVisits,
            'topCampaign' => $topCampaign?->campaign ?? 'N/A',
            'topCampaignClicks' => (int) ($topCampaign?->total ?? 0),
            'feed' => $feed,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['match' => null]);
        }

        $user = $request->user();
        $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');
        if ($domainIds->isEmpty()) {
            return response()->json(['match' => null, 'message' => 'No domains found']);
        }

        $domainMatch = Domain::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($q): void {
                $query->where('hostname', $q)
                    ->orWhere('hostname', 'like', '%'.$q.'%');
            })
            ->orderByRaw('hostname = ? desc', [$q])
            ->first(['id', 'hostname']);

        if ($domainMatch && (strcasecmp((string) $domainMatch->hostname, $q) === 0 || ! $this->looksLikeIpOrClickId($q))) {
            if (filter_var($q, FILTER_VALIDATE_IP) === false && ! $this->looksLikeClickId($q) && ! ctype_digit($q)) {
                return response()->json([
                    'match' => [
                        'type' => 'domain',
                        'domain_id' => $domainMatch->id,
                        'label' => $domainMatch->hostname,
                    ],
                ]);
            }
        }

        if (Schema::hasTable('visits')) {
            $visitBase = DB::table('visits')->whereIn('domain_id', $domainIds);

            if (filter_var($q, FILTER_VALIDATE_IP)) {
                $ipHit = (clone $visitBase)->where('ip', $q)->orderByDesc('visited_at')->first(['ip', 'domain_id']);
                if ($ipHit) {
                    return response()->json([
                        'match' => [
                            'type' => 'ip',
                            'ip' => $ipHit->ip,
                            'domain_id' => $ipHit->domain_id,
                        ],
                    ]);
                }
            }

            if ($this->looksLikeClickId($q) || strlen($q) >= 16) {
                $gclidHit = (clone $visitBase)
                    ->where(function ($query) use ($q): void {
                        $query->where('gclid', $q);
                        if (Schema::hasColumn('visits', 'gbraid')) {
                            $query->orWhere('gbraid', $q);
                        }
                        if (Schema::hasColumn('visits', 'wbraid')) {
                            $query->orWhere('wbraid', $q);
                        }
                    })
                    ->orderByDesc('visited_at')
                    ->first(['ip', 'domain_id', 'gclid']);
                if ($gclidHit && $gclidHit->ip) {
                    return response()->json([
                        'match' => [
                            'type' => 'gclid',
                            'ip' => $gclidHit->ip,
                            'domain_id' => $gclidHit->domain_id,
                            'gclid' => $gclidHit->gclid ?: $q,
                        ],
                    ]);
                }
            }

            if (Schema::hasColumn('visits', 'session_id')) {
                $sessionHit = (clone $visitBase)
                    ->where('session_id', $q)
                    ->orderByDesc('visited_at')
                    ->first(['ip', 'domain_id', 'session_id']);
                if ($sessionHit && $sessionHit->ip) {
                    return response()->json([
                        'match' => [
                            'type' => 'visitor',
                            'ip' => $sessionHit->ip,
                            'domain_id' => $sessionHit->domain_id,
                            'visitor_id' => $sessionHit->session_id,
                        ],
                    ]);
                }
            }

            if (ctype_digit($q)) {
                $eventHit = (clone $visitBase)->where('id', (int) $q)->first(['id', 'ip', 'domain_id']);
                if ($eventHit && $eventHit->ip) {
                    return response()->json([
                        'match' => [
                            'type' => 'event',
                            'ip' => $eventHit->ip,
                            'domain_id' => $eventHit->domain_id,
                            'event_id' => $eventHit->id,
                        ],
                    ]);
                }
            }

            $campaignHit = (clone $visitBase)
                ->where(function ($query) use ($q): void {
                    $query->where('utm_campaign', $q)
                        ->orWhere('utm_campaign', 'like', '%'.$q.'%');
                    if (Schema::hasColumn('visits', 'campaign_name')) {
                        $query->orWhere('campaign_name', $q)
                            ->orWhere('campaign_name', 'like', '%'.$q.'%');
                    }
                })
                ->selectRaw("COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), '')) as campaign")
                ->whereNotNull('utm_campaign')
                ->orderByDesc('visited_at')
                ->first();

            if ($campaignHit && $campaignHit->campaign) {
                return response()->json([
                    'match' => [
                        'type' => 'campaign',
                        'campaign' => $campaignHit->campaign,
                        'label' => $campaignHit->campaign,
                    ],
                ]);
            }
        }

        if (Schema::hasTable('paid_marketing_clicks') && ($this->looksLikeClickId($q) || strlen($q) >= 16)) {
            $paidHit = DB::table('paid_marketing_clicks as pc')
                ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                ->whereIn('pv.domain_id', $domainIds)
                ->where('pc.paid_id', $q)
                ->orderByDesc('pc.clicked_at')
                ->first(['pc.ip', 'pv.domain_id', 'pc.paid_id']);
            if ($paidHit && $paidHit->ip) {
                return response()->json([
                    'match' => [
                        'type' => 'gclid',
                        'ip' => $paidHit->ip,
                        'domain_id' => $paidHit->domain_id,
                        'gclid' => $paidHit->paid_id,
                    ],
                ]);
            }
        }

        if ($domainMatch) {
            return response()->json([
                'match' => [
                    'type' => 'domain',
                    'domain_id' => $domainMatch->id,
                    'label' => $domainMatch->hostname,
                ],
            ]);
        }

        return response()->json(['match' => null, 'message' => 'No matching click, IP, domain, or campaign']);
    }

    public function trends(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        $path = trim((string) $request->query('path', ''));
        [$from, $to] = $this->dateRange($request);

        if (Schema::hasTable('visits')) {
            $query = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to]);

            if ($path !== '') {
                $query->where('url', 'like', '%' . $path . '%');
            }

            $rows = $query
                ->selectRaw('DATE(visited_at) as day, COUNT(*) as total')
                ->where('is_invalid_traffic', true)
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        } else {
            $query = PaidMarketingClick::query()
                ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
                ->whereBetween('clicked_at', [$from, $to]);

            if ($path !== '') {
                $query->where('path', 'like', '%' . $path . '%');
            }

            $rows = $query
                ->selectRaw('DATE(clicked_at) as day, COUNT(*) as total')
                ->whereNotNull('clicked_at')
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        }

        $indexed = $rows->pluck('total', 'day')->all();
        $labels = [];
        $values = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $values[] = (int) ($indexed[$dateKey] ?? 0);
            $cursor->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'values' => $values,
        ]);
    }

    public function threats(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if (Schema::hasTable('detection_logs')) {
            $rows = DB::table('detection_logs')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('detected_at', [$from, $to])
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->whereNotNull('threat_group')
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        } elseif (Schema::hasTable('visits')) {
            $rows = DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('visited_at', [$from, $to])
                ->where('is_invalid_traffic', true)
                ->whereNotNull('threat_group')
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        } else {
            $rows = PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereBetween('last_click_at', [$from, $to])
                ->select('threat_group', DB::raw('COUNT(*) as total'))
                ->whereNotNull('threat_group')
                ->groupBy('threat_group')
                ->orderByDesc('total')
                ->get();
        }

        return response()->json([
            'labels' => $rows->pluck('threat_group')->map(fn ($v) => (string) $v)->values(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->values(),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(DashboardNotifications::forUser($request->user()->id));
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dark_mode' => ['required', 'boolean'],
        ]);

        $request->session()->put('preferences.dark_mode', $data['dark_mode']);
        $user = $request->user();
        $prefs = (array) ($user->ui_preferences ?? []);
        $prefs['dark_mode'] = (bool) $data['dark_mode'];
        $user->ui_preferences = $prefs;
        $user->save();

        return response()->json([
            'ok' => true,
            'dark_mode' => (bool) $data['dark_mode'],
        ]);
    }

    public function domainPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        [$from, $to] = $this->dateRange($request);
        $search = trim((string) $request->query('search', ''));

        if (Schema::hasTable('visits')) {
            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->when($search !== '', fn ($q) => $q->where('hostname', 'like', '%' . $search . '%'))
                ->leftJoin('visits', function ($join) use ($from, $to): void {
                    $join->on('domains.id', '=', 'visits.domain_id')
                        ->whereBetween('visits.visited_at', [$from, $to]);
                })
                ->select(
                    'domains.hostname',
                    'domains.tag_connected',
                    'domains.status',
                    DB::raw('COUNT(visits.id) as visits_count'),
                    DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as threat_visits_count')
                )
                ->groupBy('domains.id', 'domains.hostname', 'domains.tag_connected', 'domains.status')
                ->orderByDesc('visits_count')
                ->limit(50)
                ->get()
                ->map(fn ($d) => [
                    'domain' => $d->hostname,
                    'visits' => (int) $d->visits_count,
                    'threats' => (int) $d->threat_visits_count,
                    'pending' => ! $d->tag_connected || ($d->status ?? 'pending') === 'pending',
                ]);
        } else {
            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->when($search !== '', fn ($q) => $q->where('hostname', 'like', '%' . $search . '%'))
                ->withCount([
                    'paidMarketingVisits as visits_count' => fn ($q) => $q->whereBetween('last_click_at', [$from, $to]),
                    'paidMarketingVisits as threat_visits_count' => fn ($q) => $q->whereNotNull('threat_group')->whereBetween('last_click_at', [$from, $to]),
                ])
                ->orderByDesc('visits_count')
                ->limit(50)
                ->get()
                ->map(fn ($d) => [
                    'domain' => $d->hostname,
                    'visits' => (int) $d->visits_count,
                    'threats' => (int) $d->threat_visits_count,
                    'pending' => ! $d->tag_connected || ($d->status ?? 'pending') === 'pending',
                ]);
        }

        return response()->json($rows);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $user = $request->user();
        $domainIds = Domain::query()->where('user_id', $user->id)->pluck('id');
        if ($domainId = (int) $request->query('domain_id', 0)) {
            $domainIds = $domainIds->intersect([$domainId])->values();
        }

        if ($domainIds->isEmpty()) {
            return response()->json([]);
        }

        if (Schema::hasTable('visits')) {
            $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
                ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
                : "NULLIF(TRIM(utm_campaign), '')";

            $rows = DB::table('visits')
                ->join('domains', 'domains.id', '=', 'visits.domain_id')
                ->whereIn('visits.domain_id', $domainIds)
                ->whereRaw("{$campaignExpr} IS NOT NULL")
                ->whereRaw("{$campaignExpr} != ''")
                ->selectRaw("{$campaignExpr} as name, domains.hostname as domain, domains.id as domain_id, COUNT(*) as total")
                ->groupByRaw("{$campaignExpr}, domains.hostname, domains.id")
                ->orderBy('name')
                ->get()
                ->map(fn ($row) => [
                    'name' => (string) $row->name,
                    'domain' => (string) $row->domain,
                    'domain_id' => (int) $row->domain_id,
                    'label' => $request->query('domain_id')
                        ? (string) $row->name
                        : trim($row->name.' · '.$row->domain),
                ])
                ->values();

            return response()->json($rows);
        }

        $campaigns = PaidMarketingClick::query()
            ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
            ->whereNotNull('campaign')
            ->distinct()
            ->orderBy('campaign')
            ->pluck('campaign')
            ->map(fn ($name) => [
                'name' => (string) $name,
                'domain' => null,
                'domain_id' => null,
                'label' => (string) $name,
            ])
            ->values();

        return response()->json($campaigns);
    }

    public function liveSnapshot(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function liveStream(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');

            for ($i = 0; $i < 15; $i++) {
                if (connection_aborted()) {
                    break;
                }

                $payload = json_encode($this->snapshot($request));
                echo "event: snapshot\n";
                echo "data: {$payload}\n\n";
                @ob_flush();
                @flush();
                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function snapshot(Request $request): array
    {
        $user = $request->user();
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        $empty = [
            'paidAdvertising' => [
                'visits' => 0,
                'googleAdsClicks' => 0,
                'validClicks' => 0,
                'campaigns' => 0,
                'invalidVisits' => 0,
                'invalidClicks' => 0,
                'invalidRate' => 0,
                'protectionRate' => 0,
            ],
            'botProtection' => [
                'totalVisitors' => 0,
                'botsDetected' => 0,
                'blockedHits' => 0,
                'domainsProtected' => 0,
                'invalidRate' => 0,
                'detectionRate' => 0,
            ],
            'connectionStatus' => [
                'tracking' => 'Pending setup',
                'ingestion' => 'Waiting for traffic',
                'protection' => 'Monitoring',
            ],
            'notifications' => DashboardNotifications::forUser($user->id),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'ts' => now()->toIso8601String(),
        ];

        if ($domainIds->isEmpty()) {
            return $empty;
        }

        $visitBase = Schema::hasTable('visits')
            ? $this->applyVisitFilters(
                DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]),
                $request,
                applyTrafficSource: false
            )
            : null;

        $paidBase = $visitBase
            ? $this->applyVisitFilters((clone $visitBase)->where('is_paid_traffic', true), $request, applyTrafficSource: true)
            : null;

        $paidVisits = $paidBase
            ? (int) (clone $paidBase)->count()
            : (int) PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->whereBetween('last_click_at', [$from, $to])->sum('visits');

        // Paid card: invalid clicks within paid traffic only (user-scoped domains).
        $paidInvalidVisits = $paidBase
            ? (int) (clone $paidBase)->where('is_invalid_traffic', true)->count()
            : (int) PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('threat_group')
                ->whereBetween('last_click_at', [$from, $to])
                ->count();

        $paidValidClicks = max(0, $paidVisits - $paidInvalidVisits);

        // Bot card: blocked / invalid organic traffic for this user's domains only.
        // Never use global ip_logs — that table is shared across all tenants.
        $botBlockedHits = 0;
        $botInvalidVisits = 0;
        $botTotalVisits = 0;
        if ($visitBase) {
            $organicBase = (clone $visitBase)->where(function ($q): void {
                $q->where('is_paid_traffic', false)->orWhereNull('is_paid_traffic');
            });
            $botTotalVisits = (int) (clone $organicBase)->count();
            $botInvalidVisits = (int) (clone $organicBase)->where('is_invalid_traffic', true)->count();
            if (Schema::hasColumn('visits', 'action_taken')) {
                $botBlockedHits = (int) (clone $organicBase)->where('action_taken', 'block')->count();
            } else {
                $botBlockedHits = $botInvalidVisits;
            }
        }

        $connectedDomains = Domain::query()
            ->where('user_id', $user->id)
            ->where('tag_connected', true)
            ->count();
        $campaignCount = $paidBase
            ? (int) (clone $paidBase)->where(function ($q): void {
                $q->whereNotNull('utm_campaign');
                if (Schema::hasColumn('visits', 'campaign_name')) {
                    $q->orWhereNotNull('campaign_name');
                }
            })->distinct()->count(Schema::hasColumn('visits', 'campaign_name') ? 'campaign_name' : 'utm_campaign')
            : PaidMarketingClick::query()
                ->whereHas('visit.domain', fn ($q) => $q->where('user_id', $user->id))
                ->whereBetween('clicked_at', [$from, $to])
                ->whereNotNull('campaign')
                ->distinct()
                ->count('campaign');

        $tagHealthy = $connectedDomains > 0;
        $protectionRate = $paidVisits > 0 ? round(($paidInvalidVisits / $paidVisits) * 100, 2) : 0;
        $detectionRate = $botTotalVisits > 0 ? round(($botInvalidVisits / $botTotalVisits) * 100, 2) : 0;

        return [
            'paidAdvertising' => [
                'visits' => $paidVisits,
                'googleAdsClicks' => $paidVisits,
                'validClicks' => $paidValidClicks,
                'campaigns' => (int) $campaignCount,
                'invalidVisits' => $paidInvalidVisits,
                'invalidClicks' => $paidInvalidVisits,
                'invalidRate' => $protectionRate,
                'protectionRate' => $protectionRate,
            ],
            'botProtection' => [
                'totalVisitors' => $botTotalVisits,
                'botsDetected' => $botInvalidVisits,
                'blockedHits' => $botBlockedHits > 0 ? $botBlockedHits : $botInvalidVisits,
                'domainsProtected' => (int) $connectedDomains,
                'invalidRate' => $detectionRate,
                'detectionRate' => $detectionRate,
            ],
            'connectionStatus' => [
                'tracking' => $tagHealthy ? 'Healthy' : 'Pending setup',
                'ingestion' => ($paidVisits + $botTotalVisits) > 0 ? 'Online' : 'Waiting for traffic',
                'protection' => ($paidInvalidVisits > 0 || $botBlockedHits > 0 || $botInvalidVisits > 0) ? 'Active' : 'Monitoring',
            ],
            'notifications' => DashboardNotifications::forUser($user->id),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'ts' => now()->toIso8601String(),
        ];
    }

    /** @return Collection<int, int> */
    private function scopedDomainIds(Request $request): Collection
    {
        $ids = Domain::query()
            ->where('user_id', $request->user()->id)
            ->pluck('id');

        if ($domainId = (int) $request->query('domain_id', 0)) {
            return $ids->intersect([$domainId])->values();
        }

        return $ids;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyVisitFilters($query, Request $request, bool $applyTrafficSource = true)
    {
        $path = trim((string) $request->query('path', ''));
        if ($path !== '') {
            $query->where('url', 'like', '%'.$path.'%');
        }

        $campaign = trim((string) $request->query('campaign', ''));
        if ($campaign !== '') {
            $query->where(function ($q) use ($campaign): void {
                $q->where('utm_campaign', $campaign);
                if (Schema::hasColumn('visits', 'campaign_name')) {
                    $q->orWhere('campaign_name', $campaign);
                }
            });
        }

        if (! $applyTrafficSource) {
            return $query;
        }

        $trafficSource = strtolower(trim((string) $request->query('traffic_source', '')));
        if ($trafficSource === '' || $trafficSource === 'google_ads') {
            return $query;
        }

        if (in_array($trafficSource, ['meta_ads', 'microsoft_ads'], true)) {
            // Reserved for future sources — return empty until supported.
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function looksLikeIpOrClickId(string $q): bool
    {
        return filter_var($q, FILTER_VALIDATE_IP) !== false || $this->looksLikeClickId($q) || ctype_digit($q);
    }

    private function looksLikeClickId(string $q): bool
    {
        return (bool) preg_match('/^(EAIa|Cj0|gclid|gbraid|wbraid)/i', $q)
            || (strlen($q) >= 20 && (bool) preg_match('/^[A-Za-z0-9_-]+$/', $q));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(Request $request): array
    {
        return UserTimezone::dateRangeFromRequest($request, $request->user());
    }
}
