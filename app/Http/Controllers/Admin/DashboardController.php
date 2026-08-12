<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\GoogleConnection;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use App\Support\DashboardNotifications;
use App\Support\GoogleClickAttribution;
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
            $topCampaign = $this->topVisitCampaignRow(clone $base);

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

            $feedColumns = [
                'id',
                'ip',
                'visited_at',
                'threat_score',
                'threat_group',
                'action_taken',
                'detection_reasons',
                'utm_campaign',
                'is_invalid_traffic',
            ];
            if (Schema::hasColumn('visits', 'campaign_name')) {
                $feedColumns[] = 'campaign_name';
            }

            $feed = $feedQuery->get($feedColumns)->map(function ($row) use ($user) {
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
                $campaignName = Schema::hasColumn('visits', 'campaign_name')
                    ? (string) ($row->campaign_name ?? '')
                    : '';
                $campaign = trim((string) ($campaignName !== '' ? $campaignName : ($row->utm_campaign ?: 'Unknown campaign')));
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

            $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
                ? "COALESCE(NULLIF(TRIM(campaign_name), ''), NULLIF(TRIM(utm_campaign), ''))"
                : "NULLIF(TRIM(utm_campaign), '')";

            $campaignHit = (clone $visitBase)
                ->where(function ($query) use ($q): void {
                    $query->where('utm_campaign', $q)
                        ->orWhere('utm_campaign', 'like', '%'.$q.'%');
                    if (Schema::hasColumn('visits', 'campaign_name')) {
                        $query->orWhere('campaign_name', $q)
                            ->orWhere('campaign_name', 'like', '%'.$q.'%');
                    }
                })
                ->selectRaw("{$campaignExpr} as campaign")
                ->whereRaw("{$campaignExpr} IS NOT NULL")
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
        [$from, $to] = $this->dateRange($request);

        $labels = [];
        $totalSeries = [];
        $validSeries = [];
        $invalidSeries = [];
        $indexed = [];

        if (Schema::hasTable('visits') && $domainIds->isNotEmpty()) {
            $query = $this->applyVisitFilters(
                DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]),
                $request
            );
            GoogleClickAttribution::applyHasClickIdFilter($query);

            $rows = $query
                ->selectRaw('DATE(visited_at) as day, COUNT(*) as total, SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid')
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            foreach ($rows as $row) {
                $day = Carbon::parse((string) $row->day)->toDateString();
                $indexed[$day] = [
                    'total' => (int) $row->total,
                    'invalid' => (int) $row->invalid,
                ];
            }
        }

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $total = (int) ($indexed[$dateKey]['total'] ?? 0);
            $invalid = (int) ($indexed[$dateKey]['invalid'] ?? 0);
            $totalSeries[] = $total;
            $invalidSeries[] = $invalid;
            $validSeries[] = max(0, $total - $invalid);
            $cursor->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'values' => $invalidSeries,
            'datasets' => [
                ['key' => 'total', 'name' => 'Total Clicks', 'color' => '#B893D8', 'values' => $totalSeries],
                ['key' => 'valid', 'name' => 'Valid Clicks', 'color' => '#4ADE80', 'values' => $validSeries],
                ['key' => 'invalid', 'name' => 'Invalid Clicks', 'color' => '#FB7185', 'values' => $invalidSeries],
            ],
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
            $query = $this->applyVisitFilters(
                DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]),
                $request
            );
            GoogleClickAttribution::applyHasClickIdFilter($query);
            $rows = $query
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
            'labels' => $rows->pluck('threat_group')->map(fn ($v) => $this->humanThreatLabel((string) $v))->values(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->values(),
            'rawLabels' => $rows->pluck('threat_group')->map(fn ($v) => (string) $v)->values(),
            'total' => (int) $rows->sum(fn ($row) => (int) ($row->total ?? 0)),
        ]);
    }

    private function humanThreatLabel(?string $group): string
    {
        return match (strtolower((string) $group)) {
            'vpn', 'proxy' => 'VPN / Proxy',
            'data_center', 'datacenter' => 'Data Center',
            'malicious', 'bot' => 'Bot Behavior',
            'abnormal_rate_limit', 'repeated_click', 'repeated_clicks' => 'Repeated Clicks',
            'out_of_geo', 'geo_mismatch' => 'Location Mismatch',
            'invalid_device' => 'Invalid Device',
            default => $group ? ucwords(str_replace(['_', '-'], ' ', $group)) : 'Other',
        };
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(DashboardNotifications::forUser($request->user()->id));
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dark_mode' => ['sometimes', 'boolean'],
            'appearance' => ['sometimes', 'in:dark,light,system'],
            'language' => ['sometimes', 'string', 'max:16'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'default_dashboard' => ['sometimes', 'in:overview,paid,bot'],
            'data_display' => ['sometimes', 'array'],
            'data_display.show_risk_scores' => ['sometimes', 'boolean'],
            'data_display.show_technical_ip' => ['sometimes', 'boolean'],
            'data_display.show_advanced_columns' => ['sometimes', 'boolean'],
            'other_options' => ['sometimes', 'array'],
            'other_options.auto_refresh' => ['sometimes', 'boolean'],
            'other_options.dashboard_tips' => ['sometimes', 'boolean'],
            'other_options.alert_sound' => ['sometimes', 'boolean'],
            'notifications' => ['sometimes', 'array'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.sms' => ['sometimes', 'boolean'],
            'notifications.push' => ['sometimes', 'boolean'],
            'notifications.email_alerts' => ['sometimes', 'boolean'],
            'notifications.product_updates' => ['sometimes', 'boolean'],
            'notifications.weekly_digest' => ['sometimes', 'boolean'],
            'notifications.invalid_clicks_threshold' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'notifications.risk_score_threshold' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'notifications.detection' => ['sometimes', 'array'],
            'notifications.system' => ['sometimes', 'array'],
            'safety' => ['sometimes', 'array'],
            'safety.invalid_traffic' => ['sometimes', 'boolean'],
            'safety.bot_protection' => ['sometimes', 'boolean'],
            'safety.session_recording' => ['sometimes', 'boolean'],
            'safety.captcha' => ['sometimes', 'boolean'],
            'safety.mask_passwords' => ['sometimes', 'boolean'],
            'safety.mask_payment' => ['sometimes', 'boolean'],
            'safety.cookie_consent' => ['sometimes', 'boolean'],
            'safety.retention_days' => ['sometimes', 'integer', 'in:30,60,90,365'],
            'safety.gdpr' => ['sometimes', 'boolean'],
            'safety.ccpa' => ['sometimes', 'boolean'],
            'login_alerts' => ['sometimes', 'boolean'],
            'trusted_contacts' => ['sometimes', 'array', 'max:5'],
            'trusted_contacts.*.name' => ['nullable', 'string', 'max:120'],
            'trusted_contacts.*.email' => ['nullable', 'email', 'max:255'],
            'trusted_contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'trusted_contacts.*.role' => ['nullable', 'in:owner,admin,security,billing'],
            'trusted_contacts.*.permissions' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $prefs = (array) ($user->ui_preferences ?? []);

        if (array_key_exists('appearance', $data)) {
            $prefs['appearance'] = $data['appearance'];
            $prefs['dark_mode'] = $data['appearance'] !== 'light';
            $request->session()->put('preferences.dark_mode', $prefs['dark_mode']);
        } elseif (array_key_exists('dark_mode', $data)) {
            $prefs['dark_mode'] = (bool) $data['dark_mode'];
            $prefs['appearance'] = $prefs['dark_mode'] ? 'dark' : 'light';
            $request->session()->put('preferences.dark_mode', $prefs['dark_mode']);
        }

        foreach (['language', 'default_dashboard'] as $key) {
            if (array_key_exists($key, $data)) {
                $prefs[$key] = $data[$key];
            }
        }

        if (array_key_exists('timezone', $data) && filled($data['timezone'])) {
            $user->timezone = $data['timezone'];
            $user->timezone_source = 'manual';
        }

        if (isset($data['data_display']) && is_array($data['data_display'])) {
            $prefs['data_display'] = array_merge((array) ($prefs['data_display'] ?? []), $data['data_display']);
        }
        if (isset($data['other_options']) && is_array($data['other_options'])) {
            $prefs['other_options'] = array_merge((array) ($prefs['other_options'] ?? []), $data['other_options']);
        }

        if (isset($data['notifications']) && is_array($data['notifications'])) {
            $current = (array) ($prefs['notifications'] ?? []);
            foreach ($data['notifications'] as $key => $value) {
                $current[$key] = $value;
            }
            // Back-compat aliases
            if (array_key_exists('email', $current)) {
                $current['email_alerts'] = (bool) $current['email'];
            }
            $prefs['notifications'] = $current;
        }

        if (isset($data['safety']) && is_array($data['safety'])) {
            $prefs['safety'] = array_merge((array) ($prefs['safety'] ?? []), $data['safety']);
        }

        if (array_key_exists('login_alerts', $data)) {
            $prefs['login_alerts'] = (bool) $data['login_alerts'];
        }

        if (array_key_exists('trusted_contacts', $data)) {
            $contacts = [];
            foreach ((array) $data['trusted_contacts'] as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $phone = trim((string) ($row['phone'] ?? ''));
                if ($name === '' && $email === '' && $phone === '') {
                    continue;
                }
                $contacts[] = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => (string) ($row['role'] ?? 'security'),
                    'permissions' => (array) ($row['permissions'] ?? []),
                ];
            }
            $prefs['trusted_contacts'] = array_slice($contacts, 0, 5);
        }

        $user->ui_preferences = $prefs;
        $user->save();

        return response()->json([
            'ok' => true,
            'ui_preferences' => $prefs,
            'dark_mode' => (bool) ($prefs['dark_mode'] ?? true),
            'timezone' => $user->timezone,
        ]);
    }

    public function domainPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        [$from, $to] = $this->dateRange($request);
        $search = trim((string) $request->query('search', ''));

        if (Schema::hasTable('visits')) {
            $select = [
                'domains.hostname',
                'domains.tag_connected',
                'domains.status',
                DB::raw('COUNT(visits.id) as visits_count'),
                DB::raw('COUNT(DISTINCT visits.ip) as visitors_count'),
                DB::raw('SUM(CASE WHEN visits.is_invalid_traffic = 1 THEN 1 ELSE 0 END) as threat_visits_count'),
            ];
            if (Schema::hasColumn('visits', 'threat_score')) {
                $select[] = DB::raw('AVG(CASE WHEN visits.threat_score IS NOT NULL THEN visits.threat_score END) as avg_risk');
            } else {
                $select[] = DB::raw('NULL as avg_risk');
            }

            $rows = Domain::query()
                ->where('user_id', $user->id)
                ->when($search !== '', fn ($q) => $q->where('hostname', 'like', '%' . $search . '%'))
                ->leftJoin('visits', function ($join) use ($from, $to): void {
                    $join->on('domains.id', '=', 'visits.domain_id')
                        ->whereBetween('visits.visited_at', [$from, $to]);
                })
                ->select($select)
                ->groupBy('domains.id', 'domains.hostname', 'domains.tag_connected', 'domains.status')
                ->orderByDesc('visits_count')
                ->limit(50)
                ->get()
                ->map(function ($d) {
                    $clicks = (int) $d->visits_count;
                    $threats = (int) $d->threat_visits_count;
                    $invalidPct = $clicks > 0 ? round(($threats / $clicks) * 100, 1) : 0.0;
                    $risk = is_numeric($d->avg_risk) ? (int) round((float) $d->avg_risk) : (int) min(100, round($invalidPct * 1.2));
                    $status = (string) ($d->status ?: 'pending');
                    if (! $d->tag_connected) {
                        $status = 'pending';
                    }

                    return [
                        'domain' => $d->hostname,
                        'clicks' => $clicks,
                        'visitors' => (int) $d->visitors_count,
                        'visits' => $clicks,
                        'threats' => $threats,
                        'invalidClicks' => $threats,
                        'invalidPct' => $invalidPct,
                        'risk' => $risk,
                        'riskLevel' => $risk >= 80 ? 'High' : ($risk >= 50 ? 'Medium' : 'Low'),
                        'status' => $status === 'connected' ? 'Active' : ucfirst($status),
                        'pending' => ! $d->tag_connected || ($d->status ?? 'pending') === 'pending',
                    ];
                });
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
                ->map(function ($d) {
                    $clicks = (int) $d->visits_count;
                    $threats = (int) $d->threat_visits_count;
                    $invalidPct = $clicks > 0 ? round(($threats / $clicks) * 100, 1) : 0.0;

                    return [
                        'domain' => $d->hostname,
                        'clicks' => $clicks,
                        'visitors' => $clicks,
                        'visits' => $clicks,
                        'threats' => $threats,
                        'invalidClicks' => $threats,
                        'invalidPct' => $invalidPct,
                        'risk' => (int) min(100, round($invalidPct * 1.2)),
                        'riskLevel' => $invalidPct >= 30 ? 'High' : ($invalidPct >= 10 ? 'Medium' : 'Low'),
                        'status' => ! $d->tag_connected ? 'Pending' : 'Active',
                        'pending' => ! $d->tag_connected || ($d->status ?? 'pending') === 'pending',
                    ];
                });
        }

        return response()->json($rows);
    }

    public function campaignPerformance(Request $request): JsonResponse
    {
        $domainIds = $this->scopedDomainIds($request);
        [$from, $to] = $this->dateRange($request);

        if ($domainIds->isEmpty()) {
            return response()->json([]);
        }

        $avgCpc = $this->avgGoogleCpcForOverview($request, $domainIds, $from, $to);

        if (Schema::hasTable('visits')) {
            $hasCampaignName = Schema::hasColumn('visits', 'campaign_name');
            $groupCols = $hasCampaignName
                ? ['visits.campaign_name', 'visits.utm_campaign']
                : ['visits.utm_campaign'];

            $query = $this->applyVisitFilters(
                DB::table('visits')->whereIn('domain_id', $domainIds)->whereBetween('visited_at', [$from, $to]),
                $request,
                applyTrafficSource: false
            );

            $query->where(function ($q) use ($hasCampaignName): void {
                $q->whereNotNull('utm_campaign')->where('utm_campaign', '!=', '');
                if ($hasCampaignName) {
                    $q->orWhere(function ($inner): void {
                        $inner->whereNotNull('campaign_name')->where('campaign_name', '!=', '');
                    });
                }
            });

            $select = [
                'utm_campaign',
                DB::raw('COUNT(*) as clicks'),
                DB::raw('SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END) as invalid'),
            ];
            if ($hasCampaignName) {
                array_unshift($select, 'campaign_name');
            }
            $groupCols = $hasCampaignName ? ['campaign_name', 'utm_campaign'] : ['utm_campaign'];

            $rows = $query
                ->select($select)
                ->groupBy($groupCols)
                ->orderByDesc('clicks')
                ->limit(100)
                ->get()
                ->map(function ($row) use ($avgCpc) {
                    $campaign = trim((string) (($row->campaign_name ?? '') !== ''
                        ? $row->campaign_name
                        : ($row->utm_campaign ?? '')));
                    if ($campaign === '') {
                        return null;
                    }
                    $clicks = (int) $row->clicks;
                    $invalid = (int) $row->invalid;
                    $valid = max(0, $clicks - $invalid);

                    return [
                        'campaign' => $campaign,
                        'clicks' => $clicks,
                        'valid' => $valid,
                        'invalid' => $invalid,
                        'riskPct' => $clicks > 0 ? round(($invalid / $clicks) * 100, 1) : 0.0,
                        'costSaved' => round($avgCpc * $invalid, 2),
                    ];
                })
                ->filter()
                ->groupBy('campaign')
                ->map(function ($group) {
                    $clicks = (int) $group->sum('clicks');
                    $invalid = (int) $group->sum('invalid');
                    $valid = max(0, $clicks - $invalid);
                    $first = $group->first();

                    return [
                        'campaign' => (string) $first['campaign'],
                        'clicks' => $clicks,
                        'valid' => $valid,
                        'invalid' => $invalid,
                        'riskPct' => $clicks > 0 ? round(($invalid / $clicks) * 100, 1) : 0.0,
                        'costSaved' => round((float) $group->sum('costSaved'), 2),
                    ];
                })
                ->sortByDesc('clicks')
                ->take(5)
                ->values();

            return response()->json($rows);
        }

        $rows = PaidMarketingClick::query()
            ->whereHas('visit', fn ($q) => $q->whereIn('domain_id', $domainIds))
            ->whereBetween('clicked_at', [$from, $to])
            ->whereNotNull('campaign')
            ->select('campaign', DB::raw('COUNT(*) as clicks'), DB::raw("SUM(CASE WHEN threat_group IS NOT NULL AND threat_group != '' THEN 1 ELSE 0 END) as invalid"))
            ->groupBy('campaign')
            ->orderByDesc('clicks')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($avgCpc) {
                $clicks = (int) $row->clicks;
                $invalid = (int) $row->invalid;
                $valid = max(0, $clicks - $invalid);

                return [
                    'campaign' => (string) $row->campaign,
                    'clicks' => $clicks,
                    'valid' => $valid,
                    'invalid' => $invalid,
                    'riskPct' => $clicks > 0 ? round(($invalid / $clicks) * 100, 1) : 0.0,
                    'costSaved' => round($avgCpc * $invalid, 2),
                ];
            })
            ->values();

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
            $hasCampaignName = Schema::hasColumn('visits', 'campaign_name');
            $groupCols = $hasCampaignName
                ? ['visits.campaign_name', 'visits.utm_campaign', 'domains.hostname', 'domains.id']
                : ['visits.utm_campaign', 'domains.hostname', 'domains.id'];

            $rows = DB::table('visits')
                ->join('domains', 'domains.id', '=', 'visits.domain_id')
                ->whereIn('visits.domain_id', $domainIds)
                ->where(function ($q) use ($hasCampaignName): void {
                    $q->whereNotNull('visits.utm_campaign')->where('visits.utm_campaign', '!=', '');
                    if ($hasCampaignName) {
                        $q->orWhere(function ($inner): void {
                            $inner->whereNotNull('visits.campaign_name')->where('visits.campaign_name', '!=', '');
                        });
                    }
                });

            $select = [
                'visits.utm_campaign',
                'domains.hostname as domain',
                'domains.id as domain_id',
                DB::raw('COUNT(*) as total'),
            ];
            if ($hasCampaignName) {
                array_unshift($select, 'visits.campaign_name');
            }

            $rows = $rows
                ->select($select)
                ->groupBy($groupCols)
                ->orderBy('domain')
                ->get()
                ->map(function ($row) use ($request) {
                    $name = trim((string) (($row->campaign_name ?? '') !== ''
                        ? $row->campaign_name
                        : ($row->utm_campaign ?? '')));
                    if ($name === '') {
                        return null;
                    }

                    return [
                        'name' => $name,
                        'domain' => (string) $row->domain,
                        'domain_id' => (int) $row->domain_id,
                        'label' => $request->query('domain_id')
                            ? $name
                            : trim($name.' · '.$row->domain),
                        'total' => (int) $row->total,
                    ];
                })
                ->filter()
                ->groupBy(fn ($row) => $row['name'].'|'.$row['domain_id'])
                ->map(function ($group) {
                    $first = $group->first();
                    $first['total'] = (int) $group->sum('total');

                    return $first;
                })
                ->sortBy('name')
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
                'googleAdsApi' => 'Not connected',
                'detectionEngine' => 'Idle',
                'lastEventAt' => null,
                'lastSyncAt' => null,
                'trackingVersion' => config('promotix.tracking_version', 'v1.0.4'),
                'eventsToday' => 0,
            ],
            'quickStats' => [
                'totalClicks' => 0,
                'invalidClicks' => 0,
                'costSaved' => 0,
                'blockedToday' => 0,
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

        // Paid card: click-ID / is_paid_traffic (same attribution as Paid Dashboard).
        $paidBase = null;
        if ($visitBase) {
            $paidBase = clone $visitBase;
            GoogleClickAttribution::applyHasClickIdFilter($paidBase);
            $paidBase = $this->applyVisitFilters($paidBase, $request, applyTrafficSource: true);
        }

        $paidVisits = $paidBase
            ? (int) (clone $paidBase)->count()
            : (int) PaidMarketingVisit::query()->whereIn('domain_id', $domainIds)->whereBetween('last_click_at', [$from, $to])->sum('visits');

        // Same paid attribution as Paid Dashboard: distinct click IDs for valid/invalid.
        $trackedClicks = $paidBase
            ? GoogleClickAttribution::countDistinctClickIds(clone $paidBase)
            : $paidVisits;
        $invalidEventVisits = $paidBase
            ? (int) (clone $paidBase)->where('is_invalid_traffic', true)->count()
            : (int) PaidMarketingVisit::query()
                ->whereIn('domain_id', $domainIds)
                ->whereNotNull('threat_group')
                ->whereBetween('last_click_at', [$from, $to])
                ->count();
        $uniqueInvalidPaidClicks = $paidBase
            ? GoogleClickAttribution::countDistinctClickIds(
                (clone $paidBase)->where('is_invalid_traffic', true)
            )
            : $invalidEventVisits;
        $uniqueValidPaidClicks = max(0, $trackedClicks - $uniqueInvalidPaidClicks);

        // Total Clicks on Overview must match Paid Dashboard "Total Google Ads Clicks".
        $reportingTz = UserTimezone::reportingTimezoneForUser($user);
        [$metricFrom, $metricTo] = UserTimezone::calendarDateRangeFromRequest($request, $user, 6, $reportingTz);
        $googleAdsClicks = 0;
        if (Schema::hasTable('google_ads_campaign_daily_metrics') && $domainIds->isNotEmpty()) {
            $domains = Domain::query()
                ->whereIn('id', $domainIds)
                ->with('googleAdsAccount')
                ->get();
            $googleAds = app(\App\Services\GoogleAdsDomainMetricsSync::class)
                ->clickTotalsForDomainsReporting($domainIds, $metricFrom, $metricTo, $reportingTz, $domains);
            $googleAdsClicks = (int) ($googleAds['clicks'] ?? 0);
        }

        $paidInvalidVisits = $uniqueInvalidPaidClicks;
        $paidValidClicks = $uniqueValidPaidClicks;

        // Bot card: organic only (exclude paid click IDs / paid flag). Never use global ip_logs.
        $botBlockedHits = 0;
        $botInvalidVisits = 0;
        $botTotalVisits = 0;
        if ($visitBase) {
            $organicBase = clone $visitBase;
            GoogleClickAttribution::excludeClickIds($organicBase);
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
        $protectionRate = $trackedClicks > 0 ? round(($paidInvalidVisits / $trackedClicks) * 100, 2) : 0;
        $detectionRate = $botTotalVisits > 0 ? round(($botInvalidVisits / $botTotalVisits) * 100, 2) : 0;

        $lastEventAt = null;
        $eventsToday = 0;
        $blockedToday = 0;
        $todayLocal = now($user->timezone ?? config('app.timezone'))->toDateString();
        if (Schema::hasTable('visits')) {
            $lastEventAt = DB::table('visits')->whereIn('domain_id', $domainIds)->max('visited_at');
            $eventsToday = (int) DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->whereDate('visited_at', $todayLocal)
                ->count();
            if (Schema::hasColumn('visits', 'action_taken')) {
                $blockedToday = (int) DB::table('visits')
                    ->whereIn('domain_id', $domainIds)
                    ->whereDate('visited_at', $todayLocal)
                    ->where('action_taken', 'block')
                    ->count();
            }
        } else {
            $lastEventAt = Domain::query()->where('user_id', $user->id)->max('last_seen_at');
        }

        $avgCpc = $this->avgGoogleCpcForOverview($request, $domainIds, $from, $to);
        $costSaved = round($avgCpc * $paidInvalidVisits, 2);

        $googleConnection = GoogleConnection::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first();
        $googleAdsApi = 'Not connected';
        $lastSyncAt = null;
        if ($googleConnection) {
            $status = strtolower((string) ($googleConnection->last_sync_status ?? ''));
            $googleAdsApi = in_array($status, ['ok', 'success', 'healthy', 'connected'], true) || $googleConnection->last_sync_at
                ? 'Connected'
                : 'Connected';
            if ($status === 'error' || $status === 'failed') {
                $googleAdsApi = 'Error';
            }
            $lastSyncAt = $googleConnection->last_sync_at
                ? Carbon::parse((string) $googleConnection->last_sync_at)->toIso8601String()
                : null;
        }

        $detectionEngine = ($paidInvalidVisits > 0 || $botInvalidVisits > 0 || $botBlockedHits > 0)
            ? 'Active'
            : ($tagHealthy ? 'Ready' : 'Idle');

        $payload = [
            'paidAdvertising' => [
                'visits' => $paidVisits,
                'trackedClicks' => $trackedClicks,
                'googleAdsClicks' => $googleAdsClicks,
                'validClicks' => $paidValidClicks,
                'campaigns' => (int) $campaignCount,
                'invalidVisits' => $invalidEventVisits,
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
                'googleAdsApi' => $googleAdsApi,
                'detectionEngine' => $detectionEngine,
                'lastEventAt' => $lastEventAt ? Carbon::parse((string) $lastEventAt)->toIso8601String() : null,
                'lastSyncAt' => $lastSyncAt,
                'trackingVersion' => config('promotix.tracking_version', 'v1.0.4'),
                'eventsToday' => $eventsToday,
            ],
            'quickStats' => [
                'totalClicks' => $googleAdsClicks > 0 ? $googleAdsClicks : $trackedClicks,
                'invalidClicks' => $paidInvalidVisits,
                'costSaved' => $costSaved,
                'blockedToday' => $blockedToday > 0 ? $blockedToday : $botBlockedHits,
            ],
            'notifications' => DashboardNotifications::forUser($user->id),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'ts' => now()->toIso8601String(),
        ];

        if ($request->boolean('compare')) {
            $days = max(1, $from->diffInDays($to) + 1);
            $prevTo = $from->copy()->subDay()->endOfDay();
            $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();
            $prevRequest = Request::create($request->url(), 'GET', array_merge(
                $request->query(),
                [
                    'from' => $prevFrom->toDateString(),
                    'to' => $prevTo->toDateString(),
                    'compare' => '0',
                ]
            ));
            $prevRequest->setUserResolver(fn () => $user);
            $previous = $this->snapshot($prevRequest);
            $payload['compareRange'] = [
                'from' => $prevFrom->toDateString(),
                'to' => $prevTo->toDateString(),
            ];
            $payload['compare'] = [
                'paidAdvertising' => [
                    'visits' => $payload['paidAdvertising']['visits'] - ($previous['paidAdvertising']['visits'] ?? 0),
                    'googleAdsClicks' => $payload['paidAdvertising']['googleAdsClicks'] - ($previous['paidAdvertising']['googleAdsClicks'] ?? 0),
                    'validClicks' => $payload['paidAdvertising']['validClicks'] - ($previous['paidAdvertising']['validClicks'] ?? 0),
                    'invalidVisits' => $payload['paidAdvertising']['invalidVisits'] - ($previous['paidAdvertising']['invalidVisits'] ?? 0),
                    'invalidClicks' => $payload['paidAdvertising']['invalidClicks'] - ($previous['paidAdvertising']['invalidClicks'] ?? 0),
                    'protectionRate' => round($payload['paidAdvertising']['protectionRate'] - ($previous['paidAdvertising']['protectionRate'] ?? 0), 2),
                    'invalidRate' => round($payload['paidAdvertising']['invalidRate'] - ($previous['paidAdvertising']['invalidRate'] ?? 0), 2),
                ],
                'botProtection' => [
                    'totalVisitors' => $payload['botProtection']['totalVisitors'] - ($previous['botProtection']['totalVisitors'] ?? 0),
                    'botsDetected' => $payload['botProtection']['botsDetected'] - ($previous['botProtection']['botsDetected'] ?? 0),
                    'blockedHits' => $payload['botProtection']['blockedHits'] - ($previous['botProtection']['blockedHits'] ?? 0),
                    'detectionRate' => round($payload['botProtection']['detectionRate'] - ($previous['botProtection']['detectionRate'] ?? 0), 2),
                    'invalidRate' => round($payload['botProtection']['invalidRate'] - ($previous['botProtection']['invalidRate'] ?? 0), 2),
                ],
            ];
        }

        return $payload;
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
     * ONLY_FULL_GROUP_BY-safe top campaign (avoid grouping by COALESCE/TRIM expressions).
     *
     * @param  \Illuminate\Database\Query\Builder  $base
     */
    private function topVisitCampaignRow($base): ?object
    {
        $hasCampaignName = Schema::hasColumn('visits', 'campaign_name');
        $groupCols = $hasCampaignName ? ['campaign_name', 'utm_campaign'] : ['utm_campaign'];

        $select = [
            'utm_campaign',
            DB::raw('COUNT(*) as total'),
        ];
        if ($hasCampaignName) {
            array_unshift($select, 'campaign_name');
        }

        $rows = $base
            ->where(function ($q) use ($hasCampaignName): void {
                $q->whereNotNull('utm_campaign')->where('utm_campaign', '!=', '');
                if ($hasCampaignName) {
                    $q->orWhere(function ($inner): void {
                        $inner->whereNotNull('campaign_name')->where('campaign_name', '!=', '');
                    });
                }
            })
            ->select($select)
            ->groupBy($groupCols)
            ->get()
            ->map(function ($row) {
                $campaign = trim((string) (($row->campaign_name ?? '') !== ''
                    ? $row->campaign_name
                    : ($row->utm_campaign ?? '')));

                return $campaign === '' ? null : (object) [
                    'campaign' => $campaign,
                    'total' => (int) $row->total,
                ];
            })
            ->filter()
            ->groupBy('campaign')
            ->map(fn ($group) => (object) [
                'campaign' => (string) $group->first()->campaign,
                'total' => (int) $group->sum('total'),
            ])
            ->sortByDesc('total')
            ->first();

        return $rows;
    }

    /**
     * @param  Collection<int, int>  $domainIds
     * @param  \Carbon\Carbon|\Carbon\CarbonInterface|string  $from
     * @param  \Carbon\Carbon|\Carbon\CarbonInterface|string  $to
     */
    private function avgGoogleCpcForOverview(Request $request, Collection $domainIds, $from, $to): float
    {
        if ($domainIds->isEmpty() || ! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return 0.0;
        }

        try {
            $fromDate = $from instanceof Carbon ? $from->toDateString() : Carbon::parse((string) $from)->toDateString();
            $toDate = $to instanceof Carbon ? $to->toDateString() : Carbon::parse((string) $to)->toDateString();
            $reportingTz = UserTimezone::forUser($request->user());
            $domains = Domain::query()
                ->whereIn('id', $domainIds)
                ->with('googleAdsAccount')
                ->get();

            $googleAds = app(\App\Services\GoogleAdsDomainMetricsSync::class)
                ->clickTotalsForDomainsReporting($domainIds, $fromDate, $toDate, $reportingTz, $domains);
            $clicks = (int) ($googleAds['clicks'] ?? 0);
            $cost = (float) ($googleAds['cost'] ?? 0);

            return $clicks > 0 ? ($cost / $clicks) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
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
