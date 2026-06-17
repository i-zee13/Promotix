<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Models\PaidMarketingVisit;
use App\Support\UserTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaidMarketingController extends Controller
{
    public function detailedView(Request $request): View
    {
        return view('paid-marketing.detailed-view', [
            'campaigns' => $this->campaignNamesForUser($request),
        ]);
    }

    public function detailedVisits(Request $request): JsonResponse
    {
        $rows = $this->detailedVisitQuery($request)
            ->orderByDesc('last_click_at')
            ->limit(100)
            ->get();

        return response()->json([
            'rows' => $rows->map(fn (PaidMarketingVisit $visit) => $this->formatDetailedVisit($visit, $request->user()))->values(),
            'stats' => $this->computeDetailedStats($rows),
            'total' => $rows->count(),
        ]);
    }

    private function detailedVisitQuery(Request $request): Builder
    {
        $query = PaidMarketingVisit::query()
            ->with(['domain', 'clicks' => fn ($q) => $q->orderBy('clicked_at')])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $request->user()->id)->forPaidMarketing())
            ->select('paid_marketing_visits.*')
            ->selectSub(
                IpLog::query()
                    ->select('is_blocked')
                    ->whereColumn('ip_logs.ip', 'paid_marketing_visits.ip')
                    ->limit(1),
                'ip_is_blocked'
            );

        if ($ip = trim((string) $request->query('ip', ''))) {
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        if ($path = trim((string) $request->query('path', ''))) {
            $query->where('last_path', 'like', '%' . $path . '%');
        }

        if ($campaign = trim((string) $request->query('campaign', ''))) {
            $domainIds = $this->domainIdsForLinkedAccountLabel($request->user()->id, $campaign);

            if ($domainIds->isNotEmpty()) {
                $query->whereIn('domain_id', $domainIds);
            } else {
                $query->where(function ($match) use ($campaign): void {
                    if (Schema::hasColumn('paid_marketing_visits', 'campaign_name')) {
                        $match->where('campaign_name', $campaign)
                            ->orWhere('campaign', $campaign)
                            ->orWhere('campaign_name', 'like', '%' . $campaign . '%')
                            ->orWhere('campaign', 'like', '%' . $campaign . '%');
                    } else {
                        $match->where('campaign', $campaign)
                            ->orWhere('campaign', 'like', '%' . $campaign . '%');
                    }
                });
            }
        }

        if ($request->query('from') || $request->query('to')) {
            [$fromUtc, $toUtc] = UserTimezone::dateRangeFromRequest($request, $request->user());
            $query->whereBetween('last_click_at', [$fromUtc, $toUtc]);
        }

        return $query;
    }

    private function formatDetailedVisit(PaidMarketingVisit $visit, ?\App\Models\User $user = null): array
    {
        $clicks = $visit->clicks;
        $clickCount = max($clicks->count(), (int) ($visit->visits ?? 1));

        $vpnHits = $clicks->filter(
            fn ($c) => strtolower((string) $c->threat_group) === 'vpn'
        )->count();
        if ($vpnHits === 0 && strtolower((string) $visit->threat_group) === 'vpn') {
            $vpnHits = $clickCount;
        }

        $dataCenterHits = $clicks->filter(
            fn ($c) => in_array(strtolower((string) $c->threat_group), ['data_center', 'datacenter'], true)
        )->count();
        if ($dataCenterHits === 0 && in_array(strtolower((string) $visit->threat_group), ['data_center', 'datacenter'], true)) {
            $dataCenterHits = $clickCount;
        }

        $invalidClicks = $clicks->filter(fn ($c) => filled($c->threat_group))->count();
        if ($invalidClicks === 0 && filled($visit->threat_group)) {
            $invalidClicks = $clickCount;
        }

        $validClicks = max($clickCount - $invalidClicks, 0);
        $ipParts = collect(preg_split('/\s*,\s*/', (string) $visit->ip))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $visit->id,
            'ip' => $visit->ip,
            'ip_parts' => $ipParts,
            'ip_count' => max(count($ipParts), 1),
            'visits' => (int) ($visit->visits ?? $clicks->count() ?: 1),
            'campaign' => $visit->campaign_name ?: $visit->campaign,
            'last_click_at' => UserTimezone::isoForUser($visit->last_click_at, $user),
            'last_click_label' => UserTimezone::formatForUser($visit->last_click_at, $user, 'm/d/y') ?? '-',
            'threat_group' => $visit->threat_group,
            'threat_type' => $visit->threat_type,
            'country' => $visit->country,
            'last_path' => $visit->last_path,
            'ip_is_blocked' => (bool) $visit->ip_is_blocked,
            'vpn_hits' => $vpnHits,
            'data_center_hits' => $dataCenterHits,
            'invalid_clicks' => $invalidClicks,
            'valid_clicks' => $validClicks,
            'clicks' => $clicks->map(fn ($c) => [
                'id' => $c->id,
                'clicked_at' => UserTimezone::isoForUser($c->clicked_at, $user),
                'last_click_at' => UserTimezone::isoForUser($c->last_click_at, $user),
                'ip' => $c->ip,
                'country' => $c->country,
                'threat_group' => $c->threat_group,
                'campaign' => $c->campaign_name ?: $c->campaign,
                'paid_id' => $c->paid_id,
                'path' => $c->path,
                'keyword' => $c->keyword,
                'browser_name' => $c->browser_name,
                'browser_version' => $c->browser_version,
                'os' => $c->os,
            ])->values()->all(),
        ];
    }

    /** @param Collection<int, PaidMarketingVisit> $rows */
    private function computeDetailedStats(Collection $rows): array
    {
        $rowCount = max($rows->count(), 1);
        $blockedCount = $rows->filter(fn ($visit) => (bool) ($visit->ip_is_blocked ?? false))->count();
        $threatCount = $rows->filter(fn ($visit) => filled($visit->threat_group) || filled($visit->threat_type))->count();
        $botCount = $rows->filter(fn ($visit) => str_contains(strtolower((string) $visit->threat_type), 'bot')
            || str_contains(strtolower((string) $visit->threat_group), 'bot'))->count();
        $countryCount = $rows->pluck('country')->filter()->unique()->count();
        $paidVisits = max((int) $rows->sum(fn ($visit) => (int) ($visit->visits ?? 1)), 1);

        return [
            'cards' => [
                ['label' => 'VPN Tracking', 'value' => (int) round(($threatCount / $rowCount) * 100), 'fillClass' => 'h-[45%]', 'toneClass' => 'bg-[#9A1AFF]/50'],
                ['label' => 'Threats', 'value' => (int) round(($threatCount / $rowCount) * 100), 'fillClass' => 'h-[32%]', 'toneClass' => 'bg-white/25'],
                ['label' => 'Data Centers', 'value' => min(100, $countryCount * 12), 'fillClass' => 'h-[55%]', 'toneClass' => 'bg-white/25'],
                ['label' => 'Bot Detected', 'value' => (int) round(($botCount / $rowCount) * 100), 'fillClass' => 'h-[40%]', 'toneClass' => 'bg-white/25'],
                ['label' => 'Invalid Clicks', 'value' => (int) round(($threatCount / $rowCount) * 100), 'fillClass' => 'h-[60%]', 'toneClass' => 'bg-[#FF4BC1]/40'],
                ['label' => 'Valid Click', 'value' => max(0, 100 - (int) round(($threatCount / $rowCount) * 100)), 'fillClass' => 'h-[75%]', 'toneClass' => 'bg-emerald-400/25'],
                ['label' => 'Invalid Rate', 'value' => (int) round((($blockedCount + $threatCount) / max($rowCount * 2, 1)) * 100), 'fillClass' => 'h-[68%]', 'toneClass' => 'bg-white/20'],
            ],
        ];
    }

    private function campaignNamesForUser(Request $request): Collection
    {
        $userId = $request->user()->id;
        $names = collect();

        Domain::query()
            ->where('user_id', $userId)
            ->forPaidMarketing()
            ->with('googleAdsAccount')
            ->get()
            ->each(function (Domain $domain) use ($names): void {
                $label = trim($domain->googleAdsAccount?->displayLabel() ?? '');
                if ($label !== '') {
                    $names->push($label);
                }
            });

        if (Schema::hasTable('google_ads_campaign_daily_metrics')) {
            $names = $names->merge(
                DB::table('google_ads_campaign_daily_metrics as m')
                    ->join('domains as d', 'd.id', '=', 'm.domain_id')
                    ->where('d.user_id', $userId)
                    ->whereNotNull('m.campaign_name')
                    ->where('m.campaign_name', '!=', '')
                    ->distinct()
                    ->pluck('m.campaign_name')
            );
        }

        if (Schema::hasTable('visits')) {
            $visitQuery = DB::table('visits')
                ->join('domains', 'domains.id', '=', 'visits.domain_id')
                ->where('domains.user_id', $userId);

            if (Schema::hasColumn('visits', 'campaign_name')) {
                $visitQuery->where(function ($q): void {
                    $q->where(function ($name): void {
                        $name->whereNotNull('visits.campaign_name')->where('visits.campaign_name', '!=', '');
                    })->orWhere(function ($utm): void {
                        $utm->whereNotNull('visits.utm_campaign')->where('visits.utm_campaign', '!=', '');
                    });
                })->selectRaw('COALESCE(NULLIF(visits.campaign_name, ""), visits.utm_campaign) as name');
            } else {
                $visitQuery->whereNotNull('utm_campaign')
                    ->where('utm_campaign', '!=', '')
                    ->select('utm_campaign as name');
            }

            $names = $names->merge($visitQuery->distinct()->pluck('name'));
        }

        if (Schema::hasTable('paid_marketing_visits')) {
            $names = $names->merge(
                PaidMarketingVisit::query()
                    ->whereHas('domain', fn ($q) => $q->where('user_id', $userId))
                    ->get(['campaign', 'campaign_name'])
                    ->map(fn (PaidMarketingVisit $visit) => trim((string) ($visit->campaign_name ?: $visit->campaign)))
                    ->filter()
            );
        }

        return $names->filter()->unique()->sort()->values();
    }

    /** @return Collection<int, int> */
    private function domainIdsForLinkedAccountLabel(int $userId, string $label): Collection
    {
        if ($label === '') {
            return collect();
        }

        return Domain::query()
            ->where('user_id', $userId)
            ->forPaidMarketing()
            ->with('googleAdsAccount')
            ->get()
            ->filter(fn (Domain $domain) => $domain->googleAdsAccount?->displayLabel() === $label)
            ->pluck('id');
    }

    public function detectionSettings(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->orderBy('hostname')
            ->get();

        $selectedDomainId = (int) $request->integer('domain_id');
        $domain = $domains->firstWhere('id', $selectedDomainId) ?? $domains->first();

        $settings = null;
        if ($domain) {
            $settings = DomainDetectionSetting::firstOrCreate(
                ['domain_id' => $domain->id],
                [
                    'invalid_bot_action' => 'block',
                    'invalid_malicious_action' => 'block',
                    'suspicious_enabled' => true,
                    'suspicious_matrix' => [
                        'vpn' => 'allow',
                        'proxy' => 'block',
                        'data_center' => 'block',
                        'abnormal_rate_limit' => 'allow',
                    ],
                    'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
                ]
            );
        }

        $campaigns = collect();
        if ($domain) {
            if (Schema::hasTable('visits')) {
                $campaigns = DB::table('visits')
                    ->where('domain_id', $domain->id)
                    ->whereNotNull('utm_campaign')
                    ->where('utm_campaign', '!=', '')
                    ->select('utm_campaign')
                    ->selectRaw('COUNT(*) as total')
                    ->groupBy('utm_campaign')
                    ->orderByDesc('total')
                    ->limit(50)
                    ->pluck('utm_campaign');
            }
            if ($campaigns->isEmpty()) {
                $campaigns = PaidMarketingVisit::query()
                    ->where('domain_id', $domain->id)
                    ->whereNotNull('campaign')
                    ->where('campaign', '!=', '')
                    ->select('campaign')
                    ->selectRaw('COUNT(*) as total')
                    ->groupBy('campaign')
                    ->orderByDesc('total')
                    ->limit(50)
                    ->pluck('campaign');
            }
        }

        return view('paid-marketing.detection-settings', [
            'domains' => $domains,
            'domain' => $domain,
            'settings' => $settings,
            'campaigns' => $campaigns,
            'selectedCampaign' => $request->string('campaign')->toString(),
        ]);
    }

    public function updateDetectionSettings(Request $request, Domain $domain): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'invalid_bot_action' => ['required', 'in:allow,block,flag'],
            'invalid_malicious_action' => ['required', 'in:allow,block,flag'],
            'suspicious_enabled' => ['nullable', 'boolean'],
            'suspicious_vpn' => ['required', 'in:allow,block,flag'],
            'suspicious_proxy' => ['required', 'in:allow,block,flag'],
            'suspicious_data_center' => ['required', 'in:allow,block,flag'],
            'suspicious_abnormal_rate_limit' => ['required', 'in:allow,block,flag'],
            'session_recordings' => ['nullable', 'boolean'],
            'frequency_capping' => ['nullable', 'boolean'],
            'out_of_geo_enabled' => ['nullable', 'boolean'],
            'out_of_geo_countries' => ['nullable', 'string'],
            'allow_list_enabled' => ['nullable', 'boolean'],
            'allow_list_ips' => ['nullable', 'string'],
            'audience_exclusion_event' => ['required', 'in:exclude_all_threat_groups_auto,exclude_bot_malicious_only,disable_auto_exclusions'],
        ]);

        $countries = collect(explode(',', (string) ($data['out_of_geo_countries'] ?? '')))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();

        DomainDetectionSetting::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => $data['invalid_bot_action'],
                'invalid_malicious_action' => $data['invalid_malicious_action'],
                'suspicious_enabled' => (bool) ($data['suspicious_enabled'] ?? false),
                'suspicious_matrix' => [
                    'vpn' => $data['suspicious_vpn'],
                    'proxy' => $data['suspicious_proxy'],
                    'data_center' => $data['suspicious_data_center'],
                    'abnormal_rate_limit' => $data['suspicious_abnormal_rate_limit'],
                ],
                'session_recordings' => (bool) ($data['session_recordings'] ?? false),
                'frequency_capping' => (bool) ($data['frequency_capping'] ?? false),
                'out_of_geo_enabled' => (bool) ($data['out_of_geo_enabled'] ?? false),
                'out_of_geo_countries' => $countries,
                'allow_list_enabled' => (bool) ($data['allow_list_enabled'] ?? false),
                'allow_list_ips' => $data['allow_list_ips'] ?? null,
                'audience_exclusion_event' => $data['audience_exclusion_event'],
            ]
        );

        return redirect()
            ->route('paid-marketing.detection-settings', ['domain_id' => $domain->id])
            ->with('status', 'Detection settings saved.');
    }

    public function getRulesApi(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $settings = $this->getOrCreateDetectionSettings($domain);

        return response()->json([
            'domain_id' => $domain->id,
            'invalid_bot_action' => $settings->invalid_bot_action,
            'invalid_malicious_action' => $settings->invalid_malicious_action,
            'suspicious_enabled' => (bool) $settings->suspicious_enabled,
            'suspicious_matrix' => (array) ($settings->suspicious_matrix ?? []),
            'session_recordings' => (bool) $settings->session_recordings,
            'frequency_capping' => (bool) $settings->frequency_capping,
            'allow_list_enabled' => (bool) $settings->allow_list_enabled,
            'allow_list_ips' => $settings->allow_list_ips,
            'out_of_geo_enabled' => (bool) $settings->out_of_geo_enabled,
            'out_of_geo_countries' => (array) ($settings->out_of_geo_countries ?? []),
            'audience_exclusion_event' => $settings->audience_exclusion_event,
        ]);
    }

    public function updateRulesApi(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'invalid_bot_action' => ['required', 'in:allow,block,flag'],
            'invalid_malicious_action' => ['required', 'in:allow,block,flag'],
            'suspicious_enabled' => ['required', 'boolean'],
            'suspicious_matrix' => ['required', 'array'],
            'suspicious_matrix.vpn' => ['required', 'in:allow,block,flag'],
            'suspicious_matrix.proxy' => ['required', 'in:allow,block,flag'],
            'suspicious_matrix.data_center' => ['required', 'in:allow,block,flag'],
            'suspicious_matrix.abnormal_rate_limit' => ['required', 'in:allow,block,flag'],
            'session_recordings' => ['required', 'boolean'],
            'frequency_capping' => ['required', 'boolean'],
        ]);

        DomainDetectionSetting::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => $data['invalid_bot_action'],
                'invalid_malicious_action' => $data['invalid_malicious_action'],
                'suspicious_enabled' => (bool) $data['suspicious_enabled'],
                'suspicious_matrix' => $data['suspicious_matrix'],
                'session_recordings' => (bool) $data['session_recordings'],
                'frequency_capping' => (bool) $data['frequency_capping'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function updateExclusionsApi(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'out_of_geo_enabled' => ['required', 'boolean'],
            'out_of_geo_countries' => ['nullable', 'array'],
            'allow_list_enabled' => ['required', 'boolean'],
            'allow_list_ips' => ['nullable', 'string'],
        ]);

        DomainDetectionSetting::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'out_of_geo_enabled' => (bool) $data['out_of_geo_enabled'],
                'out_of_geo_countries' => array_values((array) ($data['out_of_geo_countries'] ?? [])),
                'allow_list_enabled' => (bool) $data['allow_list_enabled'],
                'allow_list_ips' => $data['allow_list_ips'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function updateMarketingRulesApi(Request $request, Domain $domain): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'audience_exclusion_event' => ['required', 'in:exclude_all_threat_groups_auto,exclude_bot_malicious_only,disable_auto_exclusions'],
        ]);

        DomainDetectionSetting::updateOrCreate(
            ['domain_id' => $domain->id],
            ['audience_exclusion_event' => $data['audience_exclusion_event']]
        );

        return response()->json(['ok' => true]);
    }

    private function getOrCreateDetectionSettings(Domain $domain): DomainDetectionSetting
    {
        return DomainDetectionSetting::firstOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => 'block',
                'invalid_malicious_action' => 'block',
                'suspicious_enabled' => true,
                'suspicious_matrix' => [
                    'vpn' => 'allow',
                    'proxy' => 'block',
                    'data_center' => 'block',
                    'abnormal_rate_limit' => 'allow',
                ],
                'session_recordings' => false,
                'frequency_capping' => false,
                'out_of_geo_enabled' => false,
                'out_of_geo_countries' => [],
                'allow_list_enabled' => false,
                'allow_list_ips' => null,
                'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
            ]
        );
    }
}

