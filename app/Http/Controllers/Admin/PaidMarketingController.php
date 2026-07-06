<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Models\PaidMarketingVisit;
use App\Services\IpIntel\AllowListMatcher;
use App\Services\IpIntel\IpIntelService;
use App\Services\GeoCatalogService;
use App\Services\GoogleAdsIpExclusionSyncService;
use App\Services\GoogleAudienceExclusionService;
use App\Support\GoogleClickAttribution;
use App\Support\GoogleIpBlockFormatter;
use App\Support\GoogleVerifiedCampaignLookup;
use App\Support\GoogleVerifiedPaidTraffic;
use App\Support\SessionRecordingNormalizer;
use App\Support\UserTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaidMarketingController extends Controller
{
    public function detailedView(Request $request): View
    {
        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->with('googleAdsAccount')
            ->orderBy('hostname')
            ->get(['id', 'hostname', 'google_ads_account_id']);

        $domainId = (int) $request->query('domain_id', 0);
        $googleTz = UserTimezone::resolveGoogleAccountTimezone(
            $request->user(),
            $domainId > 0 ? $domainId : null,
        );
        $reportingTz = UserTimezone::reportingTimezoneForUser($request->user(), $googleTz);

        return view('paid-marketing.detailed-view', [
            'domains' => $domains,
            'domainCatalog' => UserTimezone::domainCatalog($domains),
            'reportingTimezone' => $reportingTz,
            'googleAccountTimezone' => $googleTz,
            'reportingMode' => UserTimezone::reportingMode($request->user()),
            'profileTimezone' => UserTimezone::forUser($request->user()),
        ]);
    }

    public function detailedVisits(Request $request): JsonResponse
    {
        [$metricFrom, $metricTo, $googleTz, $reportingTz] = $this->reportingWindow($request);

        $visits = $this->detailedVisitQuery($request)
            ->orderByDesc('last_click_at')
            ->limit(100)
            ->get();

        $ipLogs = IpLog::query()
            ->whereIn('ip', $visits->pluck('ip')->unique()->filter()->values())
            ->get()
            ->keyBy('ip');

        $recordings = $this->latestRecordingsForIps($request, $visits->pluck('ip')->unique()->filter()->values());

        $domains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->when($request->query('domain_id'), fn ($q, $id) => $q->where('id', (int) $id))
            ->with('googleAdsAccount')
            ->get();

        $verificationLookup = app(GoogleVerifiedPaidTraffic::class)->buildLookup(
            $domains->pluck('id'),
            $metricFrom,
            $metricTo,
            $request->user(),
            $reportingTz,
            $domains,
        );

        $rows = $visits->map(fn (PaidMarketingVisit $visit) => $this->formatDetailedVisit(
            $visit,
            $request->user(),
            $ipLogs->get($visit->ip),
            $recordings->get($visit->ip),
            $verificationLookup,
            $reportingTz,
        ));

        $selectedDomain = $request->filled('domain_id') && $domains->count() === 1
            ? $domains->first()
            : null;

        return response()->json([
            'rows' => $rows->values(),
            'stats' => $this->computeDetailedStatsFromArrays($rows),
            'total' => $rows->count(),
            'timezone_context' => UserTimezone::dashboardContext(
                $request->user(),
                $googleTz ?? null,
                $metricFrom,
                $metricTo,
                $selectedDomain,
            ),
        ]);
    }

    public function exportDetailedCsv(Request $request): StreamedResponse
    {
        $filename = 'paid-marketing-advanced-' . now()->format('YmdHis') . '.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'IP Address',
                'Visits',
                'Domain',
                'Campaign',
                'Last Click',
                'Threat Group',
                'Threat Type',
                'Country',
                'Invalid Clicks',
                'Valid Clicks',
                'Google Verified',
                'Status',
                'Last Path',
            ]);

            [$metricFrom, $metricTo, $googleTz, $reportingTz] = $this->reportingWindow($request);

            $domains = Domain::query()
                ->where('user_id', $request->user()->id)
                ->forPaidMarketing()
                ->when($request->query('domain_id'), fn ($q, $id) => $q->where('id', (int) $id))
                ->with('googleAdsAccount')
                ->get();

            $verificationLookup = app(GoogleVerifiedPaidTraffic::class)->buildLookup(
                $domains->pluck('id'),
                $metricFrom,
                $metricTo,
                $request->user(),
                $reportingTz,
                $domains,
            );

            $this->detailedVisitQuery($request)
                ->orderByDesc('last_click_at')
                ->limit(50000)
                ->get()
                ->each(function (PaidMarketingVisit $visit) use ($handle, $request, $verificationLookup, $reportingTz): void {
                    $visit->loadMissing(['domain', 'clicks']);
                    $ipLog = IpLog::query()->where('ip', $visit->ip)->first();
                    $row = $this->formatDetailedVisit($visit, $request->user(), $ipLog, null, $verificationLookup, $reportingTz);
                    fputcsv($handle, [
                        $row['ip'],
                        $row['visits'],
                        $row['domain'],
                        $row['campaign'],
                        $row['last_click_label'],
                        $row['threat_group'],
                        $row['threat_type'],
                        $row['country'],
                        $row['invalid_clicks'],
                        $row['valid_clicks'],
                        $row['google_verified_label'] ?? '',
                        $row['status'] ?? '',
                        $row['last_path'],
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function showSessionRecording(Request $request, int $recording): JsonResponse
    {
        abort_unless(Schema::hasTable('visit_session_recordings'), 404);

        $row = DB::table('visit_session_recordings as r')
            ->join('domains as d', 'd.id', '=', 'r.domain_id')
            ->where('d.user_id', $request->user()->id)
            ->where('r.id', $recording)
            ->select('r.*')
            ->first();

        abort_unless($row, 404);

        return response()->json([
            'id' => (int) $row->id,
            'ip' => $row->ip,
            'page_url' => $row->page_url,
            'duration_ms' => (int) $row->duration_ms,
            'threat_group' => $row->threat_group,
            'events' => SessionRecordingNormalizer::normalize(json_decode((string) $row->events, true) ?: []),
            'created_at' => $row->created_at,
        ]);
    }

    private function detailedVisitQuery(Request $request): Builder
    {
        [$metricFrom, $metricTo, $googleTz, $reportingTz] = $this->reportingWindow($request);
        $user = $request->user();

        $query = PaidMarketingVisit::query()
            ->with([
                'domain',
                'clicks' => function ($clickQuery) use ($metricFrom, $metricTo, $reportingTz, $user, $request): void {
                    $clickQuery->orderBy('clicked_at');
                    UserTimezone::applyCalendarDateRangeFilter($clickQuery, 'clicked_at', $metricFrom, $metricTo, $user, $reportingTz);
                    GoogleClickAttribution::applyPaidClickIdFilter($clickQuery, 'paid_id');

                    if ($path = trim((string) $request->query('path', ''))) {
                        $clickQuery->where('path', 'like', '%' . $path . '%');
                    }
                },
            ])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id)->forPaidMarketing())
            ->whereHas('clicks', function ($clickQuery) use ($metricFrom, $metricTo, $reportingTz, $user, $request): void {
                UserTimezone::applyCalendarDateRangeFilter($clickQuery, 'clicked_at', $metricFrom, $metricTo, $user, $reportingTz);
                GoogleClickAttribution::applyPaidClickIdFilter($clickQuery, 'paid_id');

                if ($path = trim((string) $request->query('path', ''))) {
                    $clickQuery->where('path', 'like', '%' . $path . '%');
                }
            })
            ->select('paid_marketing_visits.*')
            ->selectSub(
                IpLog::query()
                    ->select('is_blocked')
                    ->whereColumn('ip_logs.ip', 'paid_marketing_visits.ip')
                    ->limit(1),
                'ip_is_blocked'
            );

        if ($domainId = (int) $request->query('domain_id', 0)) {
            $query->where('domain_id', $domainId);
        }

        if ($ip = trim((string) $request->query('ip', ''))) {
            $this->applyIpFilter($query, 'ip', $ip);
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

        return $query;
    }

    private function formatDetailedVisit(
        PaidMarketingVisit $visit,
        ?\App\Models\User $user = null,
        ?IpLog $ipLog = null,
        ?object $recording = null,
        ?GoogleVerifiedCampaignLookup $verificationLookup = null,
        ?string $reportingTz = null,
    ): array {
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
        $lastClickAt = $clicks
            ->map(fn ($c) => UserTimezone::parseUtcInstant($c->getRawOriginal('clicked_at') ?? $c->clicked_at))
            ->filter()
            ->max() ?? UserTimezone::parseUtcInstant($visit->getRawOriginal('last_click_at') ?? $visit->last_click_at);
        $ipParts = collect(preg_split('/\s*,\s*/', (string) $visit->ip))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->values()
            ->all();

        $firstClick = $clicks->first();
        $campaignId = GoogleVerifiedPaidTraffic::resolveCampaignId((object) [
            'url' => $firstClick?->path,
            'google_campaign_id' => $visit->google_campaign_id ?? $firstClick?->google_campaign_id,
        ]);
        $verificationInstant = $lastClickAt ?? $firstClick?->getRawOriginal('clicked_at') ?? $firstClick?->clicked_at;
        $googleVerified = $verificationLookup && $reportingTz
            ? $verificationLookup->isVerified((int) $visit->domain_id, $campaignId, $verificationInstant, $reportingTz)
            : null;
        $googleVerifiedLabel = $verificationLookup && $reportingTz
            ? $verificationLookup->statusLabel((int) $visit->domain_id, $campaignId, $verificationInstant, $reportingTz)
            : '—';

        return [
            'id' => $visit->id,
            'ip' => $visit->ip,
            'ip_parts' => $ipParts,
            'ip_count' => max(count($ipParts), 1),
            'visits' => (int) ($visit->visits ?? $clicks->count() ?: 1),
            'domain' => $visit->domain?->hostname,
            'campaign' => $visit->campaign_name ?: $visit->campaign,
            'last_click_at' => UserTimezone::isoForUser($lastClickAt, $user),
            'last_click_label' => UserTimezone::formatForUser($lastClickAt, $user, 'm/d/y') ?? '-',
            'threat_group' => $visit->threat_group,
            'threat_type' => $visit->threat_type,
            'country' => $visit->country,
            'last_path' => $visit->last_path,
            'ip_is_blocked' => ($visit->domain && $ipLog && AllowListMatcher::isAllowListed($visit->domain, $ipLog->ip))
                ? false
                : (bool) $visit->ip_is_blocked,
            'vpn_hits' => $vpnHits,
            'data_center_hits' => $dataCenterHits,
            'invalid_clicks' => $invalidClicks,
            'valid_clicks' => $validClicks,
            'google_verified' => $googleVerified,
            'google_verified_label' => $googleVerifiedLabel,
            'has_session_recording' => $recording !== null,
            'session_recording_id' => $recording ? (int) $recording->id : null,
            'clicks' => $clicks->map(function ($c) use ($user) {
                $clickedAt = UserTimezone::parseUtcInstant($c->getRawOriginal('clicked_at') ?? $c->clicked_at);
                $lastClick = UserTimezone::parseUtcInstant($c->getRawOriginal('last_click_at') ?? $c->last_click_at);

                return [
                'id' => $c->id,
                'clicked_at' => UserTimezone::isoForUser($clickedAt, $user),
                'last_click_at' => UserTimezone::isoForUser($lastClick, $user),
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
            ];
            })->values()->all(),
            ...$this->intelFieldsForVisit($visit, $ipLog, $user, $visit->domain),
        ];
    }

    /** @return array<string, mixed> */
    private function intelFieldsForVisit(PaidMarketingVisit $visit, ?IpLog $ipLog, ?\App\Models\User $user = null, ?Domain $domain = null): array
    {
        $domain ??= $visit->domain;
        $raw = (array) ($ipLog?->ipdetails_raw ?? []);
        $abuser = $ipLog?->ipdetails_abuser_score;
        $riskLevel = null;

        if (is_numeric($abuser)) {
            $score = (float) $abuser;
            $riskLevel = $score >= 0.7 ? 'High' : ($score >= 0.2 ? 'Medium' : 'Low');
        } elseif (is_int($ipLog?->abuse_confidence_score)) {
            $riskLevel = $ipLog->abuse_confidence_score >= 50 ? 'High' : 'Low';
        }

        $threatGroup = strtolower((string) $visit->threat_group);
        $intel = $ipLog ? app(IpIntelService::class) : null;
        $isVpn = $threatGroup === 'vpn' || ($intel && $intel->isVpnSuspect($ipLog));
        $isDc = in_array($threatGroup, ['data_center', 'datacenter'], true);
        $isTor = (bool) ($ipLog?->abuse_is_tor ?? false);
        $isHosting = $intel ? $intel->isHostingType($ipLog) : false;
        $isProxy = $intel ? $intel->isProxySuspect($ipLog) : false;

        $status = 'Valid';
        $isAllowListed = $domain !== null
            && $ipLog !== null
            && AllowListMatcher::isAllowListed($domain, $ipLog->ip);

        if ($isAllowListed) {
            $status = 'Valid';
        } elseif ($ipLog?->is_blocked) {
            $status = 'Blocked';
        } elseif (filled($visit->threat_group) || filled($visit->threat_type)) {
            $status = 'Invalid';
        }

        return [
            'status' => $status,
            'is_allowlisted' => $isAllowListed,
            'intel_region' => $raw['region'] ?? $raw['state'] ?? null,
            'intel_city' => $raw['city'] ?? null,
            'intel_latitude' => $raw['latitude'] ?? null,
            'intel_longitude' => $raw['longitude'] ?? null,
            'intel_asn' => $raw['asn'] ?? null,
            'intel_asn_org' => $raw['company'] ?? $raw['org'] ?? $ipLog?->intel_isp,
            'intel_isp' => $ipLog?->intel_isp ?? null,
            'intel_network_range' => $raw['network'] ?? $raw['network_range'] ?? null,
            'intel_routed_prefix' => $raw['prefix'] ?? $raw['routed_prefix'] ?? null,
            'intel_allocated_range' => $raw['allocated'] ?? $raw['allocated_range'] ?? null,
            'intel_range_note' => $raw['range_note'] ?? null,
            'intel_vpn' => $isVpn ? 'Yes' : 'No',
            'intel_proxy' => $isProxy ? 'Yes' : 'No',
            'intel_tor' => $isTor ? 'Yes' : 'No',
            'intel_datacenter' => ($isDc || $isHosting) ? 'Yes' : 'No',
            'intel_risk_score' => $abuser ?? $ipLog?->abuse_confidence_score,
            'intel_risk_level' => $riskLevel,
            'intel_confidence' => $ipLog?->abuse_confidence_score,
            'intel_evidence' => $ipLog?->abuse_total_reports ? ($ipLog->abuse_total_reports . ' reports') : null,
            'intel_checked_at' => UserTimezone::formatForUser($ipLog?->intel_checked_at, $user, 'm/d/y H:i'),
            'intel_error' => $ipLog?->intel_status === 'error' ? 'Yes' : null,
            'intel_ip_need_blockation' => ($isAllowListed || ! $ipLog?->is_blocked) ? 'No' : 'Yes',
            'intel_blockation_type' => is_array($ipLog?->iphub_proxy_type)
                ? implode(', ', $ipLog->iphub_proxy_type)
                : ($ipLog?->iphub_proxy_type ?? null),
            'intel_block_reason' => $ipLog?->iphub_block_reason ?? null,
            'intel_device_action' => $visit->threat_type,
            'intel_provider_type' => $raw['type'] ?? null,
            'intel_matched_provider' => $raw['provider'] ?? $raw['abuse_name'] ?? null,
            'intel_matched_dataset' => $raw['dataset'] ?? null,
            'intel_cloud_provider' => $raw['cloud_provider'] ?? null,
        ];
    }

    /** @param Collection<int, string> $ips */
    private function latestRecordingsForIps(Request $request, Collection $ips): Collection
    {
        if (! Schema::hasTable('visit_session_recordings') || $ips->isEmpty()) {
            return collect();
        }

        $query = DB::table('visit_session_recordings')
            ->whereIn('ip', $ips)
            ->orderByDesc('id');

        $domainId = (int) $request->query('domain_id', 0);
        if ($domainId > 0) {
            $query->where('domain_id', $domainId);
        } else {
            $query->whereIn('domain_id', Domain::query()
                ->where('user_id', $request->user()->id)
                ->pluck('id'));
        }

        return $query->get()->groupBy('ip')->map->first();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function computeDetailedStatsFromArrays(Collection $rows): array
    {
        $rowCount = max($rows->count(), 1);
        $blockedCount = $rows->filter(fn ($visit) => (bool) ($visit['ip_is_blocked'] ?? false))->count();
        $threatCount = $rows->filter(fn ($visit) => filled($visit['threat_group'] ?? null) || filled($visit['threat_type'] ?? null))->count();
        $botCount = $rows->filter(fn ($visit) => str_contains(strtolower((string) ($visit['threat_type'] ?? '')), 'bot')
            || str_contains(strtolower((string) ($visit['threat_group'] ?? '')), 'bot'))->count();
        $countryCount = $rows->pluck('country')->filter()->unique()->count();

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

    private function applyIpFilter($query, string $column, string $ip): void
    {
        $ip = trim($ip);
        if ($ip === '') {
            return;
        }

        $query->where(function ($match) use ($column, $ip): void {
            $match->where($column, 'like', '%' . $ip . '%');

            foreach (preg_split('/\s*,\s*/', $ip) as $part) {
                $part = trim($part);
                if ($part !== '' && $part !== $ip) {
                    $match->orWhere($column, 'like', '%' . $part . '%');
                }
            }
        });
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

        $geoCatalog = app(GeoCatalogService::class);

        return view('paid-marketing.detection-settings', [
            'domains' => $domains,
            'domain' => $domain,
            'settings' => $settings,
            'ipExclusions' => $domain ? $this->googleExclusionRowsForDomain($domain->id) : [],
            'geoCountries' => $geoCatalog->countries(null, 100),
            'geoEndpoints' => [
                'countries' => route('paid-marketing.geo.countries'),
                'states' => route('paid-marketing.geo.states'),
                'cities' => route('paid-marketing.geo.cities'),
            ],
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
            'out_of_geo_audience' => ['nullable', 'string'],
            'allow_list_enabled' => ['nullable', 'boolean'],
            'allow_list_ips' => ['nullable', 'string'],
            'block_list_enabled' => ['nullable', 'boolean'],
            'block_list_ips' => ['nullable', 'string'],
            'audience_exclusion_event' => ['required', 'in:exclude_all_threat_groups_auto,exclude_bot_malicious_only,disable_auto_exclusions'],
            'google_exclusion_enabled' => ['nullable', 'boolean'],
            'google_exclude_invalid' => ['nullable', 'boolean'],
            'google_exclude_malicious' => ['nullable', 'boolean'],
            'google_exclude_vpn' => ['nullable', 'boolean'],
            'google_exclude_data_center' => ['nullable', 'boolean'],
            'google_exclude_proxy' => ['nullable', 'boolean'],
            'google_exclude_rate_limit' => ['nullable', 'boolean'],
            'google_exclude_out_of_geo' => ['nullable', 'boolean'],
        ]);

        $countries = collect(explode(',', (string) ($data['out_of_geo_countries'] ?? '')))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();

        $audience = null;
        if (! empty($data['out_of_geo_audience'])) {
            $decoded = json_decode((string) $data['out_of_geo_audience'], true);
            if (is_array($decoded)) {
                $audience = $decoded;
                $countries = collect($decoded['rules'] ?? [])
                    ->pluck('country')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

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
                'out_of_geo_audience' => $audience,
                'allow_list_enabled' => (bool) ($data['allow_list_enabled'] ?? false),
                'allow_list_ips' => $data['allow_list_ips'] ?? null,
                'block_list_enabled' => (bool) ($data['block_list_enabled'] ?? false),
                'block_list_ips' => $data['block_list_ips'] ?? null,
                'audience_exclusion_event' => $data['audience_exclusion_event'],
                'google_exclusion_rules' => [
                    'enabled' => $request->boolean('google_exclusion_enabled'),
                    'exclude_invalid' => $request->boolean('google_exclude_invalid'),
                    'exclude_malicious' => $request->boolean('google_exclude_malicious'),
                    'exclude_vpn' => $request->boolean('google_exclude_vpn'),
                    'exclude_data_center' => $request->boolean('google_exclude_data_center'),
                    'exclude_proxy' => $request->boolean('google_exclude_proxy'),
                    'exclude_rate_limit' => $request->boolean('google_exclude_rate_limit'),
                    'exclude_out_of_geo' => $request->boolean('google_exclude_out_of_geo'),
                ],
            ]
        );

        return redirect()
            ->route('paid-marketing.detection-settings', ['domain_id' => $domain->id])
            ->with('status', 'Detection settings saved.');
    }

    public function pushGoogleExclusionIp(Request $request, Domain $domain, GoogleAdsIpExclusionSyncService $sync): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:128'],
        ]);

        $ip = trim($data['ip']);
        if (! GoogleIpBlockFormatter::isSupported($ip)) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid IP, CIDR range, or wildcard (e.g. 216.67.176.*).'], 422);
        }

        return $this->pushGoogleExclusionIpsResponse($domain, $sync, [$ip], '');
    }

    public function pushGoogleExclusionRow(Request $request, Domain $domain, GoogleAdsIpExclusionSyncService $sync): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return response()->json(['ok' => false, 'message' => 'Exclusion table not available.'], 503);
        }

        $ip = trim($request->validate(['ip' => ['required', 'string', 'max:128']])['ip']);
        $normalized = GoogleIpBlockFormatter::normalize($ip);
        if ($normalized === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid IP or range.'], 422);
        }

        $row = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domain->id)
            ->get()
            ->first(fn ($candidate) => GoogleIpBlockFormatter::matches((string) $candidate->ip, $normalized));

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'IP not found in exclusion list. Use Add to campaigns first.'], 404);
        }

        DB::table('google_ads_ip_exclusions')
            ->where('id', $row->id)
            ->update([
                'sync_status' => 'pending',
                'sync_error' => null,
                'updated_at' => now(),
            ]);

        $synced = $sync->syncRow($domain, (string) $row->ip, (int) $row->id);
        $row = DB::table('google_ads_ip_exclusions')->where('id', $row->id)->first();
        $detail = $row?->sync_error ? (string) $row->sync_error : null;

        return response()->json([
            'ok' => $synced,
            'message' => $synced
                ? ($detail ?: 'IP ' . $row->ip . " confirmed in Google Ads exclusions for {$domain->hostname}.")
                : ($detail ?: 'Could not push IP to Google Ads.'),
            'row' => $row ? $this->formatGoogleExclusionRow($row) : null,
            'rows' => $this->googleExclusionRowsForDomain($domain->id),
        ], $synced ? 200 : 422);
    }

    public function toggleGoogleExclusionRow(Request $request, Domain $domain, GoogleAdsIpExclusionSyncService $sync): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return response()->json(['ok' => false, 'message' => 'Exclusion table not available.'], 503);
        }

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:128'],
            'active' => ['required', 'boolean'],
        ]);

        $ip = trim($data['ip']);
        $normalized = GoogleIpBlockFormatter::normalize($ip);
        if ($normalized === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid IP or range.'], 422);
        }

        $row = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domain->id)
            ->get()
            ->first(fn ($candidate) => GoogleIpBlockFormatter::matches((string) $candidate->ip, $normalized));

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'IP not found in exclusion list.'], 404);
        }

        if ($data['active']) {
            $update = [
                'sync_status' => 'pending',
                'sync_error' => null,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
                $update['is_active'] = true;
            }
            DB::table('google_ads_ip_exclusions')->where('id', $row->id)->update($update);

            $synced = $sync->syncRow($domain, (string) $row->ip, (int) $row->id);
            $row = DB::table('google_ads_ip_exclusions')->where('id', $row->id)->first();
            $detail = $row?->sync_error ? (string) $row->sync_error : null;

            return response()->json([
                'ok' => $synced,
                'message' => $synced
                    ? ($detail ?: 'IP block enabled in Google Ads.')
                    : ($detail ?: 'Could not enable IP block in Google Ads.'),
                'row' => $row ? $this->formatGoogleExclusionRow($row) : null,
                'rows' => $this->googleExclusionRowsForDomain($domain->id),
            ], $synced ? 200 : 422);
        }

        $removed = $sync->removeRow($domain, (string) $row->ip, (int) $row->id);
        $row = DB::table('google_ads_ip_exclusions')->where('id', $row->id)->first();
        $detail = $row?->sync_error ? (string) $row->sync_error : null;

        return response()->json([
            'ok' => $removed,
            'message' => $removed
                ? ($detail ?: 'IP block removed from Google Ads.')
                : ($detail ?: 'Could not fully remove IP block from Google Ads.'),
            'row' => $row ? $this->formatGoogleExclusionRow($row) : null,
            'rows' => $this->googleExclusionRowsForDomain($domain->id),
        ], $removed ? 200 : 422);
    }

    public function pushGoogleExclusionBulk(Request $request, Domain $domain, GoogleAdsIpExclusionSyncService $sync): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'ips' => ['nullable', 'string', 'max:100000'],
            'file' => ['nullable', 'file', 'mimes:txt,csv', 'max:5120'],
        ]);

        $raw = trim((string) ($data['ips'] ?? ''));
        if ($request->hasFile('file')) {
            $raw .= ($raw !== '' ? "\n" : '') . (string) $request->file('file')->get();
        }

        $ips = GoogleIpBlockFormatter::parseList($raw);
        if ($ips === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No valid IPs found. Enter one IP per line, or upload a .txt / .csv file.',
            ], 422);
        }

        if (count($ips) > 200) {
            return response()->json([
                'ok' => false,
                'message' => 'Maximum 200 IPs per upload. Split your list and try again.',
            ], 422);
        }

        return $this->pushGoogleExclusionIpsResponse($domain, $sync, $ips, '', isBulk: true);
    }

    /** @param  list<string>  $ips */
    private function pushGoogleExclusionIpsResponse(
        Domain $domain,
        GoogleAdsIpExclusionSyncService $sync,
        array $ips,
        string $successMessage,
        bool $isBulk = false,
    ): JsonResponse {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return response()->json(['ok' => false, 'message' => 'Exclusion table not available. Run migrations first.'], 503);
        }

        foreach ($ips as $ip) {
            $normalized = GoogleIpBlockFormatter::normalize($ip);
            if ($normalized === null) {
                continue;
            }
            $payload = [
                'threat_group' => 'manual',
                'exclusion_mode' => 'manual_bulk',
                'sync_status' => 'pending',
                'sync_error' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
            if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
                $payload['is_active'] = true;
            }
            DB::table('google_ads_ip_exclusions')->updateOrInsert(
                ['domain_id' => $domain->id, 'ip' => $normalized],
                $payload
            );
        }

        if ($isBulk) {
            $result = $sync->syncManyIps($domain, $ips, 200);
            $message = sprintf(
                'Bulk upload: %d synced, %d failed, %d invalid skipped.',
                $result['synced'],
                $result['failed'],
                count($result['invalid']),
            );
            if ($result['errors'] !== []) {
                $message .= ' First error: ' . Str::limit((string) $result['errors'][0], 180);
            }

            return response()->json([
                'ok' => $result['synced'] > 0,
                'message' => $message,
                'summary' => $result,
                'rows' => $this->googleExclusionRowsForDomain($domain->id),
            ], $result['synced'] > 0 ? 200 : 422);
        }

        $ip = $ips[0];
        $normalized = GoogleIpBlockFormatter::normalize($ip) ?? $ip;
        $synced = $sync->syncRow($domain, $ip);
        $row = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domain->id)
            ->where('ip', $normalized)
            ->first();

        $detail = $row?->sync_error ? (string) $row->sync_error : null;

        return response()->json([
            'ok' => $synced,
            'message' => $synced
                ? ($detail ?: "IP {$ip} confirmed in Google Ads exclusions for {$domain->hostname}.")
                : ($detail ?: 'Could not push IP to Google Ads. Check Google Ads link and campaign sync.'),
            'row' => $row ? $this->formatGoogleExclusionRow($row) : null,
            'rows' => $this->googleExclusionRowsForDomain($domain->id),
        ], $synced ? 200 : 422);
    }

    /** @return list<string> */
    private function parseIpList(string $raw): array
    {
        return GoogleIpBlockFormatter::parseList($raw);
    }

    public function syncGoogleExclusionIps(Request $request, Domain $domain, GoogleAdsIpExclusionSyncService $sync): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return response()->json(['ok' => false, 'message' => 'Exclusion table not available.'], 503);
        }

        $limit = min(200, max(1, (int) $request->input('limit', 100)));
        $synced = $sync->syncPendingForDomain($domain, $limit);

        return response()->json([
            'ok' => true,
            'message' => $synced > 0
                ? "Pushed {$synced} IP(s) to Google Ads campaign exclusions."
                : 'No pending IPs to push (or all pushes failed — see list below).',
            'synced' => $synced,
            'rows' => $this->googleExclusionRowsForDomain($domain->id),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function googleExclusionRowsForDomain(int $domainId): array
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return [];
        }

        return DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domainId)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($row) => $this->formatGoogleExclusionRow($row))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function formatGoogleExclusionRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'ip' => (string) $row->ip,
            'threat_group' => (string) ($row->threat_group ?? ''),
            'sync_status' => (string) ($row->sync_status ?? 'pending'),
            'is_active' => Schema::hasColumn('google_ads_ip_exclusions', 'is_active')
                ? (bool) ($row->is_active ?? true)
                : ($row->sync_status ?? '') !== 'disabled',
            'sync_error' => $row->sync_error ? (string) $row->sync_error : null,
            'synced_at' => $row->synced_at ? (string) $row->synced_at : null,
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
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

    public function geoCountries(Request $request, GeoCatalogService $geoCatalog): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json($geoCatalog->countries($q !== '' ? $q : null));
    }

    public function geoStates(Request $request, GeoCatalogService $geoCatalog): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));
        $q = trim((string) $request->query('q', ''));

        return response()->json($geoCatalog->states($country, $q !== '' ? $q : null));
    }

    public function geoCities(Request $request, GeoCatalogService $geoCatalog): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));
        $state = strtoupper(trim((string) $request->query('state', '')));
        $q = trim((string) $request->query('q', ''));

        return response()->json($geoCatalog->cities($country, $state, $q !== '' ? $q : null));
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

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: string}
     */
    private function reportingWindow(Request $request): array
    {
        $user = $request->user();
        $domainId = (int) $request->query('domain_id', 0);
        $googleTz = UserTimezone::resolveGoogleAccountTimezone(
            $user,
            $domainId > 0 ? $domainId : null,
        );
        $reportingTz = UserTimezone::reportingTimezoneForUser($user, $googleTz);
        [$metricFrom, $metricTo] = UserTimezone::calendarDateRangeFromRequest($request, $user, 6, $reportingTz);

        return [$metricFrom, $metricTo, $googleTz, $reportingTz];
    }
}

