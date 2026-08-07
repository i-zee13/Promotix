<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetectionSettingsAudit;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\GoogleAdsAccount;
use App\Models\IpLog;
use App\Models\PaidMarketingVisit;
use App\Services\DetectionSettingsAuditLogger;
use App\Services\IpIntel\AllowListMatcher;
use App\Services\IpIntel\IpIntelService;
use App\Services\GeoCatalogService;
use App\Services\GoogleAdsIpExclusionSyncService;
use App\Services\GoogleAdsLocationExclusionSyncService;
use App\Services\GoogleAudienceExclusionService;
use App\Support\DetectionProfiles;
use App\Support\DetectionReasonLabels;
use App\Support\GoogleClickAttribution;
use App\Support\GoogleIpBlockFormatter;
use App\Support\GoogleVerifiedCampaignLookup;
use App\Support\GoogleVerifiedPaidTraffic;
use App\Support\IpListParser;
use App\Support\RiskLabels;
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

        $googleAdsAccounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->synced()
            ->orderBy('account_name')
            ->get();

        $domainId = (int) $request->query('domain_id', 0);
        $googleTz = UserTimezone::resolveGoogleAccountTimezone(
            $request->user(),
            $domainId > 0 ? $domainId : null,
        );
        $reportingTz = UserTimezone::reportingTimezoneForUser($request->user(), $googleTz);

        return view('paid-marketing.detailed-view', [
            'domains' => $domains,
            'googleAdsAccounts' => $googleAdsAccounts,
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

        $visits = $this->collectDetailedVisitModels($request, 100);

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

        $sortKey = trim((string) $request->query('sort', ''));
        $sortDir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sortKey !== '') {
            $rows = collect(\App\Support\SortableRows::sort(
                $rows,
                $sortKey,
                $sortDir,
                [
                    'visits', 'invalid_clicks', 'valid_clicks', 'vpn_hits', 'data_center_hits',
                    'intel_risk_score', 'intel_confidence', 'intel_latitude', 'intel_longitude', 'ip_count',
                ],
            ));
        }

        $selectedDomain = $request->filled('domain_id') && $domains->count() === 1
            ? $domains->first()
            : null;

        $stats = $this->computeDetailedStatsFromArrays(collect($rows));
        // KPI strip + Unique IPs chart must match Paid Dashboard summary (not table sample).
        $dashboardSummary = $this->dashboardSummaryForAdvanced($request);
        $stats['kpis'] = $this->kpisFromDashboardSummaryArray($dashboardSummary, collect($rows));
        $uniqueIps = (int) ($dashboardSummary['unique_ips'] ?? 0);
        if ($uniqueIps > 0 && isset($stats['charts']['risk'])) {
            $stats['charts']['risk']['total'] = $uniqueIps;
            $stats['charts']['risk']['total_label'] = number_format($uniqueIps);
        }

        return response()->json([
            'rows' => collect($rows)->values(),
            'stats' => $stats,
            'total' => collect($rows)->count(),
            'sort' => ['key' => $sortKey !== '' ? $sortKey : 'visits', 'dir' => $sortKey !== '' ? $sortDir : 'desc'],
            'timezone_context' => UserTimezone::dashboardContext(
                $request->user(),
                $googleTz ?? null,
                $metricFrom,
                $metricTo,
                $selectedDomain,
            ),
        ]);
    }

    public function detailedIpTimeline(Request $request): JsonResponse
    {
        $ip = trim((string) $request->query('ip', ''));
        abort_unless($ip !== '', 422, 'IP is required.');

        [$metricFrom, $metricTo, $googleTz, $reportingTz] = $this->reportingWindow($request);
        $user = $request->user();

        $visits = PaidMarketingVisit::query()
            ->with(['domain', 'clicks' => function ($clickQuery) use ($metricFrom, $metricTo, $reportingTz, $user): void {
                $clickQuery->orderBy('clicked_at');
                UserTimezone::applyCalendarDateRangeFilter($clickQuery, 'clicked_at', $metricFrom, $metricTo, $user, $reportingTz);
            }])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id)->forPaidMarketing())
            ->where(function ($q) use ($ip): void {
                $this->applyIpFilter($q, 'ip', $ip);
            })
            ->when((int) $request->query('domain_id', 0) > 0, fn ($q) => $q->where('domain_id', (int) $request->query('domain_id')))
            ->orderByDesc('last_click_at')
            ->limit(100)
            ->get();

        $ipLog = IpLog::query()->where('ip', $ip)->first()
            ?? IpLog::query()->where('ip', 'like', explode(',', $ip)[0] . '%')->first();

        $events = [];
        foreach ($visits as $visit) {
            $visitIntel = $this->intelFieldsForVisit($visit, $ipLog, $user, $visit->domain);
            $visitAt = UserTimezone::parseUtcInstant($visit->getRawOriginal('last_click_at') ?? $visit->last_click_at);

            $events[] = [
                'type' => 'visit',
                'id' => 'visit-' . $visit->id,
                'visit_id' => $visit->id,
                'at' => UserTimezone::isoForUser($visitAt, $user),
                'ip' => $visit->ip,
                'domain' => $visit->domain?->hostname,
                'campaign' => $visit->campaign_name ?: $visit->campaign,
                'device' => $visit->platform ?: null,
                'behavior' => $visit->last_path,
                'risk_decision' => $visitIntel['status'] ?? 'Valid',
                'action' => $this->timelineActionLabel($visit, $ipLog, $visitIntel),
                'threat_group' => $visit->threat_group,
                'threat_type' => $visit->threat_type,
                'country' => $visit->country,
            ];

            foreach ($visit->clicks as $click) {
                $clickedAt = UserTimezone::parseUtcInstant($click->getRawOriginal('clicked_at') ?? $click->clicked_at);
                $threat = strtolower((string) ($click->threat_group ?: $visit->threat_group));
                $risk = filled($threat) ? 'Invalid' : ($visitIntel['status'] ?? 'Valid');
                if (($visitIntel['is_allowlisted'] ?? false) === true) {
                    $risk = 'Allowed Override';
                } elseif ($ipLog?->is_blocked) {
                    $risk = 'Blocked';
                }

                $events[] = [
                    'type' => 'click',
                    'id' => 'click-' . $click->id,
                    'visit_id' => $visit->id,
                    'click_id' => $click->id,
                    'at' => UserTimezone::isoForUser($clickedAt, $user),
                    'ip' => $click->ip ?: $visit->ip,
                    'domain' => $visit->domain?->hostname,
                    'campaign' => $click->campaign_name ?: $click->campaign ?: ($visit->campaign_name ?: $visit->campaign),
                    'device' => $visit->platform ?: null,
                    'behavior' => $click->path ?: $click->keyword,
                    'risk_decision' => $risk,
                    'action' => $this->timelineActionLabel($visit, $ipLog, $visitIntel, $click->threat_group),
                    'threat_group' => $click->threat_group ?: $visit->threat_group,
                    'threat_type' => $visit->threat_type,
                    'country' => $click->country ?: $visit->country,
                    'browser' => $click->browser_name,
                    'os' => $click->os,
                    'keyword' => $click->keyword,
                    'paid_id' => $click->paid_id,
                    'path' => $click->path,
                ];
            }
        }

        usort($events, function ($a, $b) {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return response()->json([
            'ip' => $ip,
            'events' => array_values($events),
            'total' => count($events),
        ]);
    }

    public function overrideVisitDecision(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visit_id' => ['required', 'integer'],
            'decision' => ['required', 'in:valid,invalid,allowed,blocked'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $visit = PaidMarketingVisit::query()
            ->with('domain')
            ->whereKey($data['visit_id'])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $request->user()->id)->forPaidMarketing())
            ->firstOrFail();

        $this->applyManualDecision($visit, $data['decision'], $data['reason'], (int) $request->user()->id);

        if (in_array($data['decision'], ['blocked', 'allowed'], true) && $visit->ip) {
            $ipLog = IpLog::query()->firstOrCreate(['ip' => explode(',', (string) $visit->ip)[0]]);
            $ipLog->is_blocked = $data['decision'] === 'blocked';
            $ipLog->save();
        }

        return response()->json([
            'ok' => true,
            'visit' => $this->formatDetailedVisit($visit->fresh(['domain', 'clicks']), $request->user()),
        ]);
    }

    public function bulkVisitActions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visit_ids' => ['required', 'array', 'min:1', 'max:200'],
            'visit_ids.*' => ['integer'],
            'action' => ['required', 'in:valid,invalid,allowed,blocked,export_ids'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'export_ids') {
            return response()->json(['ok' => true, 'visit_ids' => $data['visit_ids']]);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Bulk ' . $data['action'];
        }

        $visits = PaidMarketingVisit::query()
            ->with('domain')
            ->whereIn('id', $data['visit_ids'])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $request->user()->id)->forPaidMarketing())
            ->get();

        $results = [];
        foreach ($visits as $visit) {
            try {
                $this->applyManualDecision($visit, $data['action'], $reason, (int) $request->user()->id);
                if (in_array($data['action'], ['blocked', 'allowed'], true) && $visit->ip) {
                    $ipLog = IpLog::query()->firstOrCreate(['ip' => trim(explode(',', (string) $visit->ip)[0])]);
                    $ipLog->is_blocked = $data['action'] === 'blocked';
                    $ipLog->save();
                }
                $results[] = ['id' => $visit->id, 'ok' => true];
            } catch (\Throwable $e) {
                $results[] = ['id' => $visit->id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'ok' => true,
            'results' => $results,
            'updated' => count(array_filter($results, fn ($r) => $r['ok'])),
            'failed' => count(array_filter($results, fn ($r) => ! $r['ok'])),
        ]);
    }

    private function applyManualDecision(PaidMarketingVisit $visit, string $decision, string $reason, int $userId): void
    {
        if ($visit->original_threat_group === null && $visit->manual_decision === null) {
            $visit->original_threat_group = $visit->threat_group;
            $visit->original_threat_type = $visit->threat_type;
        }

        $visit->manual_decision = $decision;
        $visit->manual_decision_reason = $reason;
        $visit->manual_decision_by = $userId;
        $visit->manual_decision_at = now('UTC');

        match ($decision) {
            'valid' => (function () use ($visit): void {
                $visit->threat_group = null;
                $visit->threat_type = null;
            })(),
            'allowed' => (function () use ($visit): void {
                $visit->threat_group = null;
                $visit->threat_type = 'allowed_override';
            })(),
            'invalid' => (function () use ($visit): void {
                $visit->threat_group = $visit->threat_group ?: 'manual_invalid';
                $visit->threat_type = 'invalid';
            })(),
            'blocked' => (function () use ($visit): void {
                $visit->threat_group = 'blocked';
                $visit->threat_type = 'block';
            })(),
            default => null,
        };

        $visit->save();
    }

    /**
     * @param  array<string, mixed>  $intel
     */
    private function timelineActionLabel(
        PaidMarketingVisit $visit,
        ?IpLog $ipLog,
        array $intel,
        ?string $clickThreat = null,
    ): string {
        if ($visit->manual_decision) {
            return 'Manual: ' . $visit->manual_decision;
        }
        if (($intel['is_allowlisted'] ?? false) === true) {
            return 'Allow (allow-list)';
        }
        if ($ipLog?->is_blocked || ($intel['status'] ?? '') === 'Blocked') {
            return 'Block';
        }
        $threat = strtolower((string) ($clickThreat ?: $visit->threat_group));
        if ($threat !== '') {
            return 'Flag / invalid';
        }
        return 'Allow';
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
                'Device',
                'Session ID',
                'Fingerprint',
                'Browser',
                'OS',
                'GCLID',
                'GBRAID',
                'WBRAID',
                'Invalid Clicks',
                'Valid Clicks',
                'Google Verified',
                'Status',
                'Risk Score',
                'Risk Level',
                'Confidence',
                'Evidence',
                'Needs Block',
                'Connection',
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

            // Same visit-range stats pipeline as Advanced table + Dashboard Recent IPs.
            $visits = $this->collectDetailedVisitModels($request, 5000);
            $ipLogs = IpLog::query()
                ->whereIn('ip', $visits->pluck('ip')->unique()->filter()->values())
                ->get()
                ->keyBy('ip');

            $visits->each(function (PaidMarketingVisit $visit) use ($handle, $request, $verificationLookup, $reportingTz, $ipLogs): void {
                $row = $this->formatDetailedVisit(
                    $visit,
                    $request->user(),
                    $ipLogs->get($visit->ip),
                    null,
                    $verificationLookup,
                    $reportingTz,
                );
                fputcsv($handle, [
                    $row['ip'],
                    $row['visits'],
                    $row['domain'],
                    $row['campaign'],
                    $row['last_click_label'],
                    $row['threat_group'],
                    $row['threat_type'],
                    $row['country'],
                    $row['device'] ?? '',
                    $row['session_id'] ?? '',
                    $row['device_fingerprint'] ?? '',
                    $row['browser'] ?? '',
                    $row['os'] ?? '',
                    $row['gclid'] ?? '',
                    $row['gbraid'] ?? '',
                    $row['wbraid'] ?? '',
                    $row['invalid_clicks'],
                    $row['valid_clicks'],
                    $row['google_verified_label'] ?? '',
                    $row['status'] ?? '',
                    $row['intel_risk_score'] ?? '',
                    $row['intel_risk_level'] ?? '',
                    $row['intel_confidence'] ?? '',
                    $row['intel_evidence'] ?? '',
                    $row['intel_ip_need_blockation'] ?? '',
                    $row['intel_connection_type'] ?? '',
                    $row['last_path'],
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function exportDetailedXlsx(Request $request): StreamedResponse
    {
        $filename = 'paid-marketing-advanced-' . now()->format('YmdHis') . '.xlsx';

        return response()->streamDownload(function () use ($request): void {
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

            $visits = $this->collectDetailedVisitModels($request, 5000);
            $ipLogs = IpLog::query()
                ->whereIn('ip', $visits->pluck('ip')->unique()->filter()->values())
                ->get()
                ->keyBy('ip');

            $rows = $visits
                ->map(function (PaidMarketingVisit $visit) use ($request, $verificationLookup, $reportingTz, $ipLogs) {
                    return $this->formatDetailedVisit(
                        $visit,
                        $request->user(),
                        $ipLogs->get($visit->ip),
                        null,
                        $verificationLookup,
                        $reportingTz,
                    );
                })
                ->values();

            $sortKey = trim((string) $request->query('sort', ''));
            $sortDir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            if ($sortKey !== '') {
                $rows = collect(\App\Support\SortableRows::sort($rows, $sortKey, $sortDir, [
                    'visits', 'invalid_clicks', 'valid_clicks',
                ]));
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Advanced View');
            $headers = [
                'IP Address', 'Visits', 'Domain', 'Campaign', 'Last Click', 'Threat Group', 'Threat Type',
                'Country', 'Device', 'Session ID', 'Fingerprint', 'Browser', 'OS', 'GCLID', 'GBRAID', 'WBRAID',
                'Invalid Clicks', 'Valid Clicks', 'Google Verified', 'Status', 'Risk Score', 'Risk Level',
                'Confidence', 'Evidence', 'Needs Block', 'Connection', 'Action', 'Last Path', 'Timestamp',
            ];
            foreach ($headers as $i => $header) {
                $sheet->setCellValue([$i + 1, 1], $header);
            }

            $r = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([[
                    $row['ip'] ?? '',
                    $row['visits'] ?? 0,
                    $row['domain'] ?? '',
                    $row['campaign'] ?? '',
                    $row['last_click_label'] ?? '',
                    $row['threat_group'] ?? '',
                    $row['threat_type'] ?? '',
                    $row['country'] ?? '',
                    $row['device'] ?? '',
                    $row['session_id'] ?? '',
                    $row['device_fingerprint'] ?? '',
                    $row['browser'] ?? '',
                    $row['os'] ?? '',
                    $row['gclid'] ?? '',
                    $row['gbraid'] ?? '',
                    $row['wbraid'] ?? '',
                    $row['invalid_clicks'] ?? 0,
                    $row['valid_clicks'] ?? 0,
                    $row['google_verified_label'] ?? '',
                    $row['status'] ?? '',
                    $row['intel_risk_score'] ?? '',
                    $row['intel_risk_level'] ?? '',
                    $row['intel_confidence'] ?? '',
                    $row['intel_evidence'] ?? '',
                    $row['intel_ip_need_blockation'] ?? '',
                    $row['intel_connection_type'] ?? '',
                    $row['status'] === 'Blocked' ? 'Block' : (($row['status'] === 'Invalid') ? 'Flag' : 'Allow'),
                    $row['last_path'] ?? '',
                    $row['last_click_at'] ?? '',
                ]], null, 'A' . $r);
                $r++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
            'visit_id' => $row->visit_id ? (int) $row->visit_id : null,
            'session_id' => $row->session_id,
            'ip' => $row->ip,
            'page_url' => $row->page_url,
            'duration_ms' => (int) $row->duration_ms,
            'threat_group' => $row->threat_group,
            'behavior_signals' => Schema::hasColumn('visit_session_recordings', 'behavior_signals')
                ? (json_decode((string) ($row->behavior_signals ?? '[]'), true) ?: [])
                : [],
            'events' => SessionRecordingNormalizer::normalize(json_decode((string) $row->events, true) ?: []),
            'created_at' => $row->created_at,
        ]);
    }

    public function destroySessionRecording(Request $request, int $recording): JsonResponse
    {
        abort_unless(Schema::hasTable('visit_session_recordings'), 404);

        $row = DB::table('visit_session_recordings as r')
            ->join('domains as d', 'd.id', '=', 'r.domain_id')
            ->where('d.user_id', $request->user()->id)
            ->where('r.id', $recording)
            ->select('r.id')
            ->first();

        abort_unless($row, 404);

        DB::table('visit_session_recordings')->where('id', $row->id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Pull in paid_marketing_visits that have live `visits` traffic in-range but were
     * missed by click-date filtering (duplicate gclid / empty paid_id click rows).
     *
     * @param  Collection<int, PaidMarketingVisit>  $visits
     * @return Collection<int, PaidMarketingVisit>
     */
    /**
     * Shared Advanced membership + counts source (API table + CSV/XLSX export).
     * Seeds from live `visits` (Dashboard Recent IP parity), merges PM-only activity,
     * attaches in-range visit totals, then ranks by click volume.
     *
     * @return Collection<int, PaidMarketingVisit>
     */
    private function collectDetailedVisitModels(Request $request, int $limit = 100): Collection
    {
        [$metricFrom, $metricTo, , $reportingTz] = $this->reportingWindow($request);
        $seedLimit = max($limit, 200);

        $visits = $this->seedDetailedVisitsFromLiveTraffic(
            $request,
            $metricFrom,
            $metricTo,
            $reportingTz,
            $seedLimit,
        );

        $pmVisits = $this->detailedVisitQuery($request)
            ->orderByDesc('last_click_at')
            ->limit(min(max($limit, 100), 50000))
            ->get();

        $visits = $visits
            ->concat($pmVisits)
            ->unique(fn (PaidMarketingVisit $visit) => (int) $visit->domain_id.'|'.(string) $visit->ip)
            ->values();

        $this->attachLiveVisitRangeStats($visits, $request, $metricFrom, $metricTo, $reportingTz);

        return $visits
            ->sortByDesc(function (PaidMarketingVisit $visit) {
                $range = (int) ($visit->getAttribute('range_visit_count') ?? 0);
                if ($range > 0) {
                    return $range;
                }

                return $visit->relationLoaded('clicks') ? $visit->clicks->count() : 0;
            })
            ->take($limit)
            ->values();
    }

    /**
     * Seed Advanced rows from live `visits` aggregates (Dashboard Recent IP source),
     * including synthetic stubs when no paid_marketing_visits row exists yet.
     *
     * @return Collection<int, PaidMarketingVisit>
     */
    private function seedDetailedVisitsFromLiveTraffic(
        Request $request,
        string $metricFrom,
        string $metricTo,
        string $reportingTz,
        int $limit = 200,
    ): Collection {
        $aggs = $this->livePaidVisitAggregates($request, $metricFrom, $metricTo, $reportingTz, $limit);
        if ($aggs->isEmpty()) {
            return collect();
        }

        $user = $request->user();
        $path = trim((string) $request->query('path', ''));
        $domains = Domain::query()
            ->whereIn('id', $aggs->pluck('domain_id')->unique()->filter()->all())
            ->get()
            ->keyBy('id');

        $pmQuery = PaidMarketingVisit::query()
            ->with([
                'domain',
                'clicks' => function ($clickQuery) use ($metricFrom, $metricTo, $reportingTz, $user, $path): void {
                    $clickQuery->orderBy('clicked_at');
                    UserTimezone::applyCalendarDateRangeFilter($clickQuery, 'clicked_at', $metricFrom, $metricTo, $user, $reportingTz);
                    GoogleClickAttribution::applyPaidClickIdFilter($clickQuery, 'paid_id');
                    if ($path !== '') {
                        $clickQuery->where('path', 'like', '%'.$path.'%');
                    }
                },
            ])
            ->whereHas('domain', fn ($q) => $q->where('user_id', $user->id)->forPaidMarketing())
            ->where(function (Builder $match) use ($aggs): void {
                foreach ($aggs as $row) {
                    $match->orWhere(function (Builder $inner) use ($row): void {
                        $inner->where('domain_id', (int) $row->domain_id)
                            ->where('ip', (string) $row->ip);
                    });
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

        $pmByKey = $pmQuery->get()->keyBy(
            fn (PaidMarketingVisit $visit) => (int) $visit->domain_id.'|'.(string) $visit->ip
        );

        return $aggs->map(function (object $agg) use ($pmByKey, $domains) {
            $key = (int) $agg->domain_id.'|'.(string) $agg->ip;
            $visit = $pmByKey->get($key);
            if (! $visit) {
                $visit = $this->makeSyntheticDetailedVisit($agg, $domains->get((int) $agg->domain_id));
            }

            $visit->setAttribute('range_visit_count', (int) ($agg->total ?? 0));
            $visit->setAttribute('range_invalid_count', (int) ($agg->invalid ?? 0));
            $visit->setAttribute('range_last_seen', $agg->last_seen ?? null);
            $visit->setAttribute('range_campaign', $agg->campaign ?? null);
            $visit->setAttribute('range_threat_group', $agg->threat_group ?? null);
            $visit->setAttribute('range_vpn_hits', (int) ($agg->vpn_hits ?? 0));
            $visit->setAttribute('range_data_center_hits', (int) ($agg->data_center_hits ?? 0));

            return $visit;
        })->values();
    }

    private function makeSyntheticDetailedVisit(object $agg, ?Domain $domain): PaidMarketingVisit
    {
        $visit = new PaidMarketingVisit([
            'domain_id' => (int) $agg->domain_id,
            'ip' => (string) $agg->ip,
            'country' => $agg->country ?? null,
            'campaign_name' => $agg->campaign ?? null,
            'campaign' => $agg->campaign ?? null,
            'threat_group' => $agg->threat_group ?? null,
            'last_click_at' => $agg->last_seen ?? null,
            'visits' => 0,
        ]);
        $visit->setRelation('domain', $domain);
        $visit->setRelation('clicks', collect());
        $visit->setAttribute('ip_is_blocked', false);

        return $visit;
    }

    private function mergeDetailedVisitsFromLiveTraffic(
        Request $request,
        Collection $visits,
        string $metricFrom,
        string $metricTo,
        string $reportingTz,
    ): Collection {
        if (! Schema::hasTable('visits')) {
            return $visits;
        }

        $existingLookup = array_fill_keys(
            $visits->map(
                fn (PaidMarketingVisit $visit) => (int) $visit->domain_id.'|'.(string) $visit->ip
            )->all(),
            true
        );

        $extra = $this->seedDetailedVisitsFromLiveTraffic($request, $metricFrom, $metricTo, $reportingTz)
            ->filter(fn (PaidMarketingVisit $visit) => ! isset($existingLookup[(int) $visit->domain_id.'|'.(string) $visit->ip]))
            ->values();

        if ($extra->isEmpty()) {
            return $visits;
        }

        return $visits
            ->concat($extra)
            ->unique(fn (PaidMarketingVisit $visit) => (int) $visit->domain_id.'|'.(string) $visit->ip)
            ->sortByDesc(fn (PaidMarketingVisit $visit) => (int) ($visit->getAttribute('range_visit_count') ?? 0))
            ->take(100)
            ->values();
    }

    /**
     * @return Collection<int, object{domain_id: int|string, ip: string, total?: int, invalid?: int}>
     */
    private function livePaidVisitKeys(
        Request $request,
        string $metricFrom,
        string $metricTo,
        string $reportingTz,
    ): Collection {
        return $this->livePaidVisitAggregates($request, $metricFrom, $metricTo, $reportingTz)
            ->map(fn (object $row) => (object) [
                'domain_id' => $row->domain_id,
                'ip' => $row->ip,
            ])
            ->values();
    }

    /**
     * In-range paid `visits` grouped by domain + IP (Dashboard Recent IP parity).
     *
     * @return Collection<int, object>
     */
    private function livePaidVisitAggregates(
        Request $request,
        string $metricFrom,
        string $metricTo,
        string $reportingTz,
        int $limit = 200,
    ): Collection {
        if (! Schema::hasTable('visits')) {
            return collect();
        }

        $user = $request->user();
        $domainIds = $this->scopedPaidDomainIds($request);

        if ($domainIds->isEmpty()) {
            return collect();
        }

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds->all());

        UserTimezone::applyCalendarDateRangeFilter($query, 'visited_at', $metricFrom, $metricTo, $user, $reportingTz);
        GoogleClickAttribution::applyHasClickIdFilter($query);
        $this->applyVisitsRequestFilters($query, $request);

        $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
            ? 'MAX(COALESCE(NULLIF(TRIM(campaign_name), ""), NULLIF(TRIM(utm_campaign), "")))'
            : 'MAX(NULLIF(TRIM(utm_campaign), ""))';
        $threatExpr = Schema::hasColumn('visits', 'threat_group')
            ? 'MAX(threat_group)'
            : 'NULL';
        $invalidExpr = Schema::hasColumn('visits', 'is_invalid_traffic')
            ? 'SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END)'
            : '0';
        $vpnExpr = Schema::hasColumn('visits', 'threat_group')
            ? "SUM(CASE WHEN threat_group = 'vpn' THEN 1 ELSE 0 END)"
            : '0';
        $dcExpr = Schema::hasColumn('visits', 'threat_group')
            ? "SUM(CASE WHEN threat_group = 'data_center' THEN 1 ELSE 0 END)"
            : '0';
        $countryExpr = Schema::hasColumn('visits', 'country')
            ? 'MAX(country)'
            : 'NULL';

        return $query
            ->selectRaw("domain_id, ip, COUNT(*) as total, {$invalidExpr} as invalid, MAX(visited_at) as last_seen, {$campaignExpr} as campaign, {$threatExpr} as threat_group, {$vpnExpr} as vpn_hits, {$dcExpr} as data_center_hits, {$countryExpr} as country")
            ->groupBy('domain_id', 'ip')
            ->orderByDesc('total')
            ->limit(max(1, min($limit, 5000)))
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function scopedPaidDomainIds(Request $request): Collection
    {
        return Domain::query()
            ->where('user_id', $request->user()->id)
            ->forPaidMarketing()
            ->when((int) $request->query('domain_id', 0) > 0, fn ($q) => $q->where('id', (int) $request->query('domain_id')))
            ->when((int) $request->query('google_ads_account_id', 0) > 0, fn ($q) => $q->where('google_ads_account_id', (int) $request->query('google_ads_account_id')))
            ->pluck('id');
    }

    /**
     * Mirror Paid Dashboard visits filters (path / campaign / ip).
     */
    private function applyVisitsRequestFilters($query, Request $request): void
    {
        if ($ip = trim((string) $request->query('ip', ''))) {
            $this->applyIpFilter($query, 'ip', $ip);
        }

        if ($path = trim((string) $request->query('path', ''))) {
            $query->where('url', 'like', '%'.$path.'%');
        }

        if ($campaign = trim((string) $request->query('campaign', ''))) {
            if (Schema::hasColumn('visits', 'campaign_name')) {
                $query->where(function ($match) use ($campaign): void {
                    $match->where('campaign_name', $campaign)
                        ->orWhere('campaign_name', 'like', '%'.$campaign.'%')
                        ->orWhere('utm_campaign', $campaign)
                        ->orWhere('utm_campaign', 'like', '%'.$campaign.'%');
                });
            } else {
                $query->where(function ($match) use ($campaign): void {
                    $match->where('utm_campaign', $campaign)
                        ->orWhere('utm_campaign', 'like', '%'.$campaign.'%');
                });
            }
        }
    }

    /**
     * @param  Collection<int, PaidMarketingVisit>  $visits
     */
    private function attachLiveVisitRangeStats(
        Collection $visits,
        Request $request,
        string $metricFrom,
        string $metricTo,
        string $reportingTz,
    ): void {
        if ($visits->isEmpty() || ! Schema::hasTable('visits')) {
            return;
        }

        $user = $request->user();
        $domainIds = $visits->pluck('domain_id')->map(fn ($id) => (int) $id)->unique()->values();
        $ips = $visits->pluck('ip')->map(fn ($ip) => (string) $ip)->unique()->filter()->values();

        $query = DB::table('visits')
            ->whereIn('domain_id', $domainIds->all())
            ->whereIn('ip', $ips->all());

        UserTimezone::applyCalendarDateRangeFilter($query, 'visited_at', $metricFrom, $metricTo, $user, $reportingTz);
        GoogleClickAttribution::applyHasClickIdFilter($query);
        $this->applyVisitsRequestFilters($query, $request);

        $campaignExpr = Schema::hasColumn('visits', 'campaign_name')
            ? 'MAX(COALESCE(NULLIF(TRIM(campaign_name), ""), NULLIF(TRIM(utm_campaign), "")))'
            : 'MAX(NULLIF(TRIM(utm_campaign), ""))';
        $threatExpr = Schema::hasColumn('visits', 'threat_group')
            ? 'MAX(threat_group)'
            : 'NULL';
        $invalidExpr = Schema::hasColumn('visits', 'is_invalid_traffic')
            ? 'SUM(CASE WHEN is_invalid_traffic = 1 THEN 1 ELSE 0 END)'
            : '0';
        $vpnExpr = Schema::hasColumn('visits', 'threat_group')
            ? "SUM(CASE WHEN threat_group = 'vpn' THEN 1 ELSE 0 END)"
            : '0';
        $dcExpr = Schema::hasColumn('visits', 'threat_group')
            ? "SUM(CASE WHEN threat_group = 'data_center' THEN 1 ELSE 0 END)"
            : '0';

        $stats = $query
            ->selectRaw("domain_id, ip, COUNT(*) as total, {$invalidExpr} as invalid, MAX(visited_at) as last_seen, {$campaignExpr} as campaign, {$threatExpr} as threat_group, {$vpnExpr} as vpn_hits, {$dcExpr} as data_center_hits")
            ->groupBy('domain_id', 'ip')
            ->get()
            ->keyBy(fn ($row) => (int) $row->domain_id.'|'.(string) $row->ip);

        foreach ($visits as $visit) {
            $key = (int) $visit->domain_id.'|'.(string) $visit->ip;
            $row = $stats->get($key);
            if (! $row) {
                // Explicitly clear stale seeded stats when campaign/path filters exclude this IP.
                if ($request->filled('campaign') || $request->filled('path')) {
                    $visit->setAttribute('range_visit_count', 0);
                    $visit->setAttribute('range_invalid_count', 0);
                }
                continue;
            }

            $visit->setAttribute('range_visit_count', (int) ($row->total ?? 0));
            $visit->setAttribute('range_invalid_count', (int) ($row->invalid ?? 0));
            $visit->setAttribute('range_last_seen', $row->last_seen ?? null);
            $visit->setAttribute('range_campaign', $row->campaign ?? null);
            $visit->setAttribute('range_threat_group', $row->threat_group ?? null);
            $visit->setAttribute('range_vpn_hits', (int) ($row->vpn_hits ?? 0));
            $visit->setAttribute('range_data_center_hits', (int) ($row->data_center_hits ?? 0));
        }
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
            ->where(function (Builder $activity) use ($metricFrom, $metricTo, $reportingTz, $user, $request): void {
                $path = trim((string) $request->query('path', ''));

                // Legacy: paid_marketing_clicks inside the selected calendar range.
                $activity->whereHas('clicks', function ($clickQuery) use ($metricFrom, $metricTo, $reportingTz, $user, $path): void {
                    UserTimezone::applyCalendarDateRangeFilter($clickQuery, 'clicked_at', $metricFrom, $metricTo, $user, $reportingTz);
                    GoogleClickAttribution::applyPaidClickIdFilter($clickQuery, 'paid_id');

                    if ($path !== '') {
                        $clickQuery->where('path', 'like', '%' . $path . '%');
                    }
                });

                // Same source as Dashboard Recent IP Activity (`visits.visited_at`).
                if (Schema::hasTable('visits')) {
                    $activity->orWhereExists(function ($sq) use ($metricFrom, $metricTo, $reportingTz, $user, $request): void {
                        $sq->selectRaw('1')
                            ->from('visits')
                            ->whereColumn('visits.domain_id', 'paid_marketing_visits.domain_id')
                            ->whereColumn('visits.ip', 'paid_marketing_visits.ip');
                        UserTimezone::applyCalendarDateRangeFilter($sq, 'visits.visited_at', $metricFrom, $metricTo, $user, $reportingTz);
                        GoogleClickAttribution::applyHasClickIdFilter($sq);
                        $this->applyVisitsRequestFilters($sq, $request);
                    });
                }

                // Duplicate paid click: visit metadata updates, but no new click row.
                $activity->orWhere(function (Builder $recent) use ($metricFrom, $metricTo, $reportingTz, $user): void {
                    UserTimezone::applyCalendarDateRangeFilter($recent, 'last_click_at', $metricFrom, $metricTo, $user, $reportingTz);
                    $recent->whereHas('clicks', function ($clickQuery): void {
                        GoogleClickAttribution::applyPaidClickIdFilter($clickQuery, 'paid_id');
                    });
                });
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

        if ($accountId = (int) $request->query('google_ads_account_id', 0)) {
            $query->whereHas('domain', fn ($q) => $q->where('google_ads_account_id', $accountId));
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

        if ($country = trim((string) $request->query('country', ''))) {
            $query->where(function ($match) use ($country): void {
                $match->where('country', $country)
                    ->orWhere('country', 'like', $country . '%')
                    ->orWhereHas('clicks', fn ($cq) => $cq->where('country', $country)->orWhere('country', 'like', $country . '%'));
            });
        }

        if ($keyword = trim((string) $request->query('keyword', ''))) {
            $query->whereHas('clicks', fn ($cq) => $cq->where('keyword', 'like', '%' . $keyword . '%'));
        }

        if ($adGroup = trim((string) $request->query('ad_group', ''))) {
            $query->whereHas('clicks', function ($cq) use ($adGroup): void {
                $cq->where(function ($inner) use ($adGroup): void {
                    if (Schema::hasColumn('paid_marketing_clicks', 'campaignr')) {
                        $inner->where('campaignr', 'like', '%' . $adGroup . '%');
                    }
                    $inner->orWhere('campaign', 'like', '%' . $adGroup . '%');
                    if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                        $inner->orWhere('campaign_name', 'like', '%' . $adGroup . '%');
                    }
                });
            });
        }

        if ($browser = trim((string) $request->query('browser', ''))) {
            $query->whereHas('clicks', fn ($cq) => $cq->where('browser_name', 'like', '%' . $browser . '%'));
        }

        if ($device = trim((string) $request->query('device', ''))) {
            $query->where(function ($match) use ($device): void {
                if (Schema::hasColumn('paid_marketing_visits', 'platform')) {
                    $match->where('platform', 'like', '%' . $device . '%');
                }
                $match->orWhereHas('clicks', fn ($cq) => $cq->where('os', 'like', '%' . $device . '%'));
            });
        }

        if ($source = trim((string) $request->query('source', ''))) {
            if (Schema::hasColumn('paid_marketing_visits', 'platform')) {
                $query->where('platform', 'like', '%' . $source . '%');
            }
        }

        if ($threat = trim((string) $request->query('threat_group', ''))) {
            $query->where(function ($match) use ($threat): void {
                $match->where('threat_group', $threat)
                    ->orWhere('threat_group', 'like', $threat . '%')
                    ->orWhereHas('clicks', fn ($cq) => $cq->where('threat_group', $threat));
            });
        }

        if ($detection = trim((string) $request->query('detection', ''))) {
            $detection = strtolower($detection);
            if ($detection === 'invalid') {
                $query->where(function ($match): void {
                    $match->whereNotNull('threat_group')->where('threat_group', '!=', '')
                        ->orWhereNotNull('threat_type')->where('threat_type', '!=', '');
                });
            } elseif ($detection === 'valid') {
                $query->where(function ($match): void {
                    $match->where(fn ($q) => $q->whereNull('threat_group')->orWhere('threat_group', ''))
                        ->where(fn ($q) => $q->whereNull('threat_type')->orWhere('threat_type', ''));
                });
            } elseif ($detection !== '') {
                $query->where(function ($match) use ($detection): void {
                    $match->where('threat_group', $detection)
                        ->orWhere('threat_type', $detection)
                        ->orWhereHas('clicks', fn ($cq) => $cq->where('threat_group', $detection));
                });
            }
        }

        if ($blockStatus = trim((string) $request->query('block_status', ''))) {
            $blockStatus = strtolower($blockStatus);
            if ($blockStatus === 'blocked') {
                $query->whereExists(function ($sq): void {
                    $sq->selectRaw('1')
                        ->from('ip_logs')
                        ->whereColumn('ip_logs.ip', 'paid_marketing_visits.ip')
                        ->where('ip_logs.is_blocked', true);
                });
            } elseif ($blockStatus === 'allowed') {
                $query->whereNotExists(function ($sq): void {
                    $sq->selectRaw('1')
                        ->from('ip_logs')
                        ->whereColumn('ip_logs.ip', 'paid_marketing_visits.ip')
                        ->where('ip_logs.is_blocked', true);
                });
            }
        }

        if ($riskLevel = trim((string) $request->query('risk_level', ''))) {
            $riskLevel = strtolower($riskLevel);
            $query->whereExists(function ($sq) use ($riskLevel): void {
                $sq->selectRaw('1')
                    ->from('ip_logs')
                    ->whereColumn('ip_logs.ip', 'paid_marketing_visits.ip');

                if ($riskLevel === 'high') {
                    $sq->where(function ($inner): void {
                        $inner->where('ipdetails_abuser_score', '>=', 0.7)
                            ->orWhere('abuse_confidence_score', '>=', 50);
                    });
                } elseif ($riskLevel === 'medium') {
                    $sq->where(function ($inner): void {
                        $inner->whereBetween('ipdetails_abuser_score', [0.2, 0.6999])
                            ->orWhereBetween('abuse_confidence_score', [20, 49]);
                    });
                } elseif ($riskLevel === 'low') {
                    $sq->where(function ($inner): void {
                        $inner->where(function ($safe): void {
                            $safe->whereNotNull('ipdetails_abuser_score')->where('ipdetails_abuser_score', '<', 0.2);
                        })->orWhere(function ($safe): void {
                            $safe->whereNotNull('abuse_confidence_score')->where('abuse_confidence_score', '<', 20);
                        });
                    });
                }
            });
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
        // Prefer live `visits` range stats (same source as Paid Dashboard Recent IPs).
        // Never fall back to lifetime paid_marketing_visits.visits — that inflates
        // Advanced totals vs Dashboard's in-range click counts.
        // Only use already-eager-loaded in-range clicks — never lazy-load all-time clicks.
        $clicks = $visit->relationLoaded('clicks') ? collect($visit->clicks) : collect();
        $rangeVisitCount = (int) ($visit->getAttribute('range_visit_count') ?? 0);
        $rangeInvalidCount = (int) ($visit->getAttribute('range_invalid_count') ?? 0);

        if ($rangeVisitCount > 0) {
            $clickCount = $rangeVisitCount;
            $invalidClicks = min($rangeInvalidCount, $clickCount);
        } else {
            $clickCount = $clicks->count();
            $invalidClicks = $clicks->filter(fn ($c) => filled($c->threat_group))->count();
        }

        $vpnHits = (int) ($visit->getAttribute('range_vpn_hits') ?? 0);
        if ($vpnHits === 0) {
            $vpnHits = $clicks->filter(
                fn ($c) => strtolower((string) $c->threat_group) === 'vpn'
            )->count();
        }
        if ($vpnHits === 0 && strtolower((string) ($visit->threat_group ?: $visit->getAttribute('range_threat_group'))) === 'vpn') {
            $vpnHits = max($vpnHits, $invalidClicks > 0 ? $invalidClicks : 0);
        }

        $dataCenterHits = (int) ($visit->getAttribute('range_data_center_hits') ?? 0);
        if ($dataCenterHits === 0) {
            $dataCenterHits = $clicks->filter(
                fn ($c) => in_array(strtolower((string) $c->threat_group), ['data_center', 'datacenter'], true)
            )->count();
        }
        if ($dataCenterHits === 0 && in_array(strtolower((string) ($visit->threat_group ?: $visit->getAttribute('range_threat_group'))), ['data_center', 'datacenter'], true)) {
            $dataCenterHits = max($dataCenterHits, $invalidClicks > 0 ? $invalidClicks : 0);
        }

        $validClicks = max($clickCount - $invalidClicks, 0);
        $rangeLastSeen = $visit->getAttribute('range_last_seen');
        $lastClickAt = $clicks
            ->map(fn ($c) => UserTimezone::parseUtcInstant($c->getRawOriginal('clicked_at') ?? $c->clicked_at))
            ->filter()
            ->max()
            ?? UserTimezone::parseUtcInstant($rangeLastSeen)
            ?? UserTimezone::parseUtcInstant($visit->getRawOriginal('last_click_at') ?? $visit->last_click_at);
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

        $intel = $this->intelFieldsForVisit($visit, $ipLog, $user, $visit->domain);
        $clickIds = $this->hydrateGoogleClickIds($visit, $clicks);
        $sessionMeta = $this->hydrateVisitSessionMeta($visit);
        $deviceLabel = $this->normalizeDeviceLabel($visit->platform ?: ($sessionMeta['os'] ?? null));
        $reasons = [];
        if ($visit->manual_decision) {
            $reasons[] = 'manual_' . $visit->manual_decision;
        }
        if ($visit->threat_group) {
            $reasons[] = (string) $visit->threat_group;
        }
        if ($visit->original_threat_group && $visit->manual_decision) {
            $reasons[] = 'original:' . $visit->original_threat_group;
        }

        $riskReasons = [];
        if (($intel['intel_vpn'] ?? 'No') === 'Yes') {
            $riskReasons[] = 'VPN detected';
        }
        if (($intel['intel_datacenter'] ?? 'No') === 'Yes') {
            $riskReasons[] = 'Datacenter IP';
        }
        if (($intel['intel_proxy'] ?? 'No') === 'Yes') {
            $riskReasons[] = 'Proxy detected';
        }
        if ($invalidClicks > 1) {
            $riskReasons[] = 'Multiple invalid clicks';
        }
        if ($visit->threat_group) {
            $riskReasons[] = str_replace('_', ' ', (string) $visit->threat_group);
        }

        return [
            'id' => $visit->id,
            'ip' => $visit->ip,
            'ip_parts' => $ipParts,
            'ip_count' => max(count($ipParts), 1),
            'visits' => $clickCount,
            'domain' => $visit->domain?->hostname,
            'campaign' => $visit->campaign_name
                ?: $visit->campaign
                ?: ($visit->getAttribute('range_campaign') ?: null),
            'last_click_at' => UserTimezone::isoForUser($lastClickAt, $user),
            'last_click_label' => UserTimezone::formatForUser($lastClickAt, $user, 'm/d/y') ?? '-',
            'threat_group' => $visit->threat_group ?: $visit->getAttribute('range_threat_group'),
            'threat_type' => $visit->threat_type,
            'manual_decision' => $visit->manual_decision,
            'manual_decision_reason' => $visit->manual_decision_reason,
            'original_threat_group' => $visit->original_threat_group,
            'original_threat_type' => $visit->original_threat_type,
            'device' => $deviceLabel,
            'session_id' => $sessionMeta['session_id'],
            'device_fingerprint' => $sessionMeta['device_fingerprint'],
            'browser' => $sessionMeta['browser'] ?: ($firstClick?->browser_name),
            'os' => $sessionMeta['os'] ?: ($firstClick?->os),
            'screen_resolution' => $sessionMeta['screen'],
            'language' => $sessionMeta['language'],
            'visitor_timezone' => $sessionMeta['timezone'],
            'gclid' => $clickIds['gclid'],
            'gbraid' => $clickIds['gbraid'],
            'wbraid' => $clickIds['wbraid'],
            'google_click_id' => $clickIds['google_click_id'],
            'google_click_type' => $clickIds['google_click_type'],
            'risk_summary' => [
                'score' => $intel['intel_risk_score'] ?? null,
                'level' => $intel['intel_risk_level'] ?? 'Low',
                'status' => $intel['status'] ?? 'Valid',
                'confidence' => $intel['intel_confidence'] ?? null,
                'evidence' => $intel['intel_evidence'] ?? null,
                'reasons' => array_values(array_unique($riskReasons)),
                'needs_block' => ($intel['intel_ip_need_blockation'] ?? 'No') === 'Yes',
                'connection' => $intel['intel_connection_type'] ?? null,
            ],
            'rule_explanation' => [
                'inputs' => [
                    'ip' => $visit->ip,
                    'threat_group' => $visit->threat_group,
                    'threat_type' => $visit->threat_type,
                    'manual_decision' => $visit->manual_decision,
                    'ip_blocked' => (bool) ($visit->ip_is_blocked ?? $ipLog?->is_blocked),
                    'allow_listed' => $intel['is_allowlisted'] ?? false,
                ],
                'decision' => $intel['status'] ?? RiskLabels::VALID,
                'action' => $visit->manual_decision
                    ? ('Manual: ' . $visit->manual_decision)
                    : ($visit->threat_type ?: (($intel['status'] ?? '') === RiskLabels::BLOCKED ? 'block' : 'allow')),
                'reasons' => DetectionReasonLabels::explain(array_values(array_unique($reasons))),
                'original_decision' => $visit->original_threat_group
                    ? [
                        'threat_group' => $visit->original_threat_group,
                        'threat_type' => $visit->original_threat_type,
                    ]
                    : null,
            ],
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
            'clicks' => $clicks->map(function ($c) use ($user, $visit, $ipLog, $deviceLabel, $clickIds) {
                $clickedAt = UserTimezone::parseUtcInstant($c->getRawOriginal('clicked_at') ?? $c->clicked_at);
                $lastClick = UserTimezone::parseUtcInstant($c->getRawOriginal('last_click_at') ?? $c->last_click_at);
                $intel = $this->intelFieldsForVisit($visit, $ipLog, $user, $visit->domain);
                $risk = RiskLabels::fromContext([
                    'is_allowlisted' => $intel['is_allowlisted'] ?? false,
                    'is_blocked' => (bool) ($ipLog?->is_blocked),
                    'manual_decision' => $visit->manual_decision,
                    'threat_group' => $c->threat_group ?: $visit->threat_group,
                    'threat_type' => $visit->threat_type,
                    'action_taken' => $visit->threat_type,
                ]);
                $typed = $this->classifyPaidId((string) ($c->paid_id ?? ''));

                return [
                'id' => $c->id,
                'clicked_at' => UserTimezone::isoForUser($clickedAt, $user),
                'last_click_at' => UserTimezone::isoForUser($lastClick, $user),
                'ip' => $c->ip,
                'country' => $c->country,
                'threat_group' => $c->threat_group,
                'campaign' => $c->campaign_name ?: $c->campaign,
                'paid_id' => $c->paid_id,
                'gclid' => $typed['gclid'] ?: ($clickIds['gclid'] ?? null),
                'gbraid' => $typed['gbraid'] ?: ($clickIds['gbraid'] ?? null),
                'wbraid' => $typed['wbraid'] ?: ($clickIds['wbraid'] ?? null),
                'path' => $c->path,
                'keyword' => $c->keyword,
                'browser_name' => $c->browser_name,
                'browser_version' => $c->browser_version,
                'os' => $c->os,
                'device' => $deviceLabel,
                'risk_decision' => $risk,
                'action' => $this->timelineActionLabel($visit, $ipLog, $intel, $c->threat_group),
            ];
            })->values()->all(),
            ...$this->intelFieldsForVisit($visit, $ipLog, $user, $visit->domain),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $clicks
     * @return array{gclid: ?string, gbraid: ?string, wbraid: ?string, google_click_id: ?string, google_click_type: ?string}
     */
    private function hydrateGoogleClickIds(PaidMarketingVisit $visit, $clicks): array
    {
        $empty = [
            'gclid' => null,
            'gbraid' => null,
            'wbraid' => null,
            'google_click_id' => null,
            'google_click_type' => null,
        ];

        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'gclid')) {
            $query = DB::table('visits')
                ->where('domain_id', $visit->domain_id)
                ->where('ip', $visit->ip)
                ->orderByDesc('visited_at');

            $select = ['gclid'];
            if (Schema::hasColumn('visits', 'gbraid')) {
                $select[] = 'gbraid';
            }
            if (Schema::hasColumn('visits', 'wbraid')) {
                $select[] = 'wbraid';
            }
            if (Schema::hasColumn('visits', 'google_click_type')) {
                $select[] = 'google_click_type';
            }

            $row = $query->where(function ($q): void {
                $q->whereNotNull('gclid')->where('gclid', '!=', '');
                if (Schema::hasColumn('visits', 'gbraid')) {
                    $q->orWhere(function ($inner): void {
                        $inner->whereNotNull('gbraid')->where('gbraid', '!=', '');
                    });
                }
                if (Schema::hasColumn('visits', 'wbraid')) {
                    $q->orWhere(function ($inner): void {
                        $inner->whereNotNull('wbraid')->where('wbraid', '!=', '');
                    });
                }
            })->first($select);

            if ($row) {
                $gclid = filled($row->gclid ?? null) ? (string) $row->gclid : null;
                $gbraid = filled($row->gbraid ?? null) ? (string) $row->gbraid : null;
                $wbraid = filled($row->wbraid ?? null) ? (string) $row->wbraid : null;
                $type = filled($row->google_click_type ?? null)
                    ? (string) $row->google_click_type
                    : ($gclid ? 'gclid' : ($gbraid ? 'gbraid' : ($wbraid ? 'wbraid' : null)));

                return [
                    'gclid' => $gclid,
                    'gbraid' => $gbraid,
                    'wbraid' => $wbraid,
                    'google_click_id' => $gclid ?: $gbraid ?: $wbraid,
                    'google_click_type' => $type,
                ];
            }
        }

        foreach ($clicks as $click) {
            $typed = $this->classifyPaidId((string) ($click->paid_id ?? ''));
            if ($typed['google_click_id']) {
                return $typed;
            }
        }

        return $empty;
    }

    /**
     * @return array{gclid: ?string, gbraid: ?string, wbraid: ?string, google_click_id: ?string, google_click_type: ?string}
     */
    private function classifyPaidId(string $paidId): array
    {
        $paidId = trim($paidId);
        if ($paidId === '') {
            return [
                'gclid' => null,
                'gbraid' => null,
                'wbraid' => null,
                'google_click_id' => null,
                'google_click_type' => null,
            ];
        }

        $lower = strtolower($paidId);
        if (str_starts_with($lower, 'gbraid') || str_contains($lower, 'gbraid')) {
            return [
                'gclid' => null,
                'gbraid' => $paidId,
                'wbraid' => null,
                'google_click_id' => $paidId,
                'google_click_type' => 'gbraid',
            ];
        }
        if (str_starts_with($lower, 'wbraid') || str_contains($lower, 'wbraid')) {
            return [
                'gclid' => null,
                'gbraid' => null,
                'wbraid' => $paidId,
                'google_click_id' => $paidId,
                'google_click_type' => 'wbraid',
            ];
        }

        return [
            'gclid' => $paidId,
            'gbraid' => null,
            'wbraid' => null,
            'google_click_id' => $paidId,
            'google_click_type' => 'gclid',
        ];
    }

    private function normalizeDeviceLabel(?string $platform): string
    {
        $value = strtolower(trim((string) $platform));
        if ($value === '') {
            return '—';
        }
        if (str_contains($value, 'mobile') || in_array($value, ['ios', 'android', 'phone'], true)) {
            return 'Mobile';
        }
        if (str_contains($value, 'tablet') || str_contains($value, 'ipad')) {
            return 'Tablet';
        }
        if (str_contains($value, 'desktop') || str_contains($value, 'windows') || str_contains($value, 'mac') || str_contains($value, 'linux')) {
            return 'Desktop';
        }

        return ucfirst($platform);
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

        $isAllowListed = $domain !== null
            && $ipLog !== null
            && AllowListMatcher::isAllowListed($domain, $ipLog->ip);

        $status = RiskLabels::fromContext([
            'is_allowlisted' => $isAllowListed,
            'is_blocked' => (bool) ($ipLog?->is_blocked),
            'manual_decision' => $visit->manual_decision,
            'threat_group' => $visit->threat_group,
            'threat_type' => $visit->threat_type,
            'action_taken' => $visit->threat_type,
            'google_invalid' => strtolower((string) $visit->threat_group) === 'google_invalid',
        ]);

        return [
            'status' => $status,
            'status_badge_class' => RiskLabels::cssClass($status),
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
            'intel_ip_need_blockation' => $this->needsBlockationLabel(
                $isAllowListed,
                (bool) ($ipLog?->is_blocked),
                $riskLevel,
                $status,
                filled($visit->threat_group)
            ),
            'intel_blockation_type' => is_array($ipLog?->iphub_proxy_type)
                ? implode(', ', $ipLog->iphub_proxy_type)
                : ($ipLog?->iphub_proxy_type ?? null),
            'intel_block_reason' => $ipLog?->iphub_block_reason ?? null,
            'intel_device_action' => $visit->threat_type,
            'intel_provider_type' => $raw['type'] ?? null,
            'intel_matched_provider' => $raw['provider'] ?? $raw['abuse_name'] ?? null,
            'intel_matched_dataset' => $raw['dataset'] ?? null,
            'intel_cloud_provider' => $raw['cloud_provider'] ?? null,
            'intel_connection_type' => $raw['type'] ?? ($isHosting || $isDc ? 'Datacenter' : ($isVpn || $isProxy ? 'Anonymous' : 'Residential')),
        ];
    }

    private function needsBlockationLabel(
        bool $isAllowListed,
        bool $isBlocked,
        ?string $riskLevel,
        mixed $status,
        bool $hasThreat,
    ): string {
        if ($isAllowListed || $isBlocked) {
            return 'No';
        }

        $statusText = strtolower((string) $status);
        $needs = $hasThreat
            || in_array($riskLevel, ['High', 'Medium'], true)
            || in_array($statusText, ['invalid', 'blocked', 'high risk'], true);

        return $needs ? 'Yes' : 'No';
    }

    /**
     * @return array{session_id: ?string, device_fingerprint: ?string, browser: ?string, os: ?string, screen: ?string, language: ?string, timezone: ?string}
     */
    private function hydrateVisitSessionMeta(PaidMarketingVisit $visit): array
    {
        $empty = [
            'session_id' => null,
            'device_fingerprint' => null,
            'browser' => null,
            'os' => null,
            'screen' => null,
            'language' => null,
            'timezone' => null,
        ];

        if (! Schema::hasTable('visits')) {
            return $empty;
        }

        $select = ['id'];
        foreach (['session_id', 'device', 'browser', 'os', 'user_agent', 'language', 'timezone', 'screen_resolution'] as $col) {
            if (Schema::hasColumn('visits', $col)) {
                $select[] = $col;
            }
        }

        $row = DB::table('visits')
            ->where('domain_id', $visit->domain_id)
            ->where('ip', $visit->ip)
            ->orderByDesc('visited_at')
            ->first($select);

        $fingerprint = null;
        if (Schema::hasTable('visit_session_recordings') && Schema::hasColumn('visit_session_recordings', 'behavior_fingerprint')) {
            $fingerprint = DB::table('visit_session_recordings')
                ->where('domain_id', $visit->domain_id)
                ->where('ip', $visit->ip)
                ->orderByDesc('id')
                ->value('behavior_fingerprint');
        }

        if (! $row) {
            $empty['device_fingerprint'] = $fingerprint ? (string) $fingerprint : null;

            return $empty;
        }

        $sessionId = filled($row->session_id ?? null) ? (string) $row->session_id : null;
        if (! $sessionId && Schema::hasTable('visit_session_recordings') && Schema::hasColumn('visit_session_recordings', 'session_id')) {
            $sessionId = DB::table('visit_session_recordings')
                ->where('domain_id', $visit->domain_id)
                ->where('ip', $visit->ip)
                ->orderByDesc('id')
                ->value('session_id');
            $sessionId = $sessionId ? (string) $sessionId : null;
        }

        return [
            'session_id' => $sessionId,
            'device_fingerprint' => $fingerprint
                ? (string) $fingerprint
                : (filled($row->user_agent ?? null) ? substr(hash('sha256', (string) $row->user_agent), 0, 16) : null),
            'browser' => filled($row->browser ?? null) ? (string) $row->browser : null,
            'os' => filled($row->os ?? null) ? (string) $row->os : null,
            'screen' => filled($row->screen_resolution ?? null) ? (string) $row->screen_resolution : null,
            'language' => filled($row->language ?? null) ? (string) $row->language : null,
            'timezone' => filled($row->timezone ?? null) ? (string) $row->timezone : null,
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

    /**
     * Build Advanced View KPI cards from the same Paid Dashboard summary endpoint
     * so totals stay identical for the shared domain/date/campaign filters.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function advancedKpisFromDashboardSummary(Request $request, Collection $rows): array
    {
        return $this->kpisFromDashboardSummaryArray(
            $this->dashboardSummaryForAdvanced($request),
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardSummaryForAdvanced(Request $request): array
    {
        // IP / expert filters are table-only; strip them so KPIs match the dashboard strip.
        $summaryRequest = Request::create(
            $request->url(),
            'GET',
            collect($request->query())->except([
                'ip', 'sort', 'dir', 'detection', 'threat_group', 'risk_level',
                'block_status', 'keyword', 'ad_group', 'browser', 'device', 'source', 'country',
            ])->all()
        );
        $summaryRequest->setUserResolver(fn () => $request->user());

        try {
            return app(PaidAdvertisingDashboardController::class)
                ->summary($summaryRequest)
                ->getData(true);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function kpisFromDashboardSummaryArray(array $summary, Collection $rows): array
    {
        $googleClicks = (int) ($summary['total_click_count'] ?? $summary['google_clicks'] ?? 0);
        $tracked = (int) ($summary['tracked_clicks'] ?? $summary['unique_paid_clicks'] ?? 0);
        $valid = (int) ($summary['unique_valid_paid_clicks'] ?? $summary['valid_paid_visits'] ?? 0);
        $invalid = (int) ($summary['unique_invalid_paid_clicks'] ?? $summary['invalid_paid_visits'] ?? 0);
        $blocked = (int) ($summary['block_enforced'] ?? $summary['block_attempts'] ?? $summary['blocked_paid_visits'] ?? 0);
        $costSaved = (float) ($summary['cost_saved'] ?? 0);
        $trackingAccuracy = (int) ($summary['tracking_accuracy_pct'] ?? $summary['tag_capture_pct'] ?? 0);

        $pctBase = $tracked > 0 ? $tracked : max($valid + $invalid, 0);
        $validPct = $pctBase > 0 ? round(($valid / $pctBase) * 100, 1) : 0.0;
        $invalidPct = $pctBase > 0 ? round(($invalid / $pctBase) * 100, 1) : 0.0;

        $riskScores = $rows
            ->map(fn ($visit) => $visit['intel_risk_score'] ?? $visit['risk_summary']['score'] ?? null)
            ->filter(fn ($score) => is_numeric($score))
            ->map(fn ($score) => (float) $score)
            ->values();
        $avgRisk = $riskScores->isNotEmpty() ? (int) round($riskScores->avg()) : 0;

        $googleNeedsReconnect = (bool) ($summary['google_needs_reconnect'] ?? false);
        $googleReconnectUrl = (string) ($summary['google_reconnect_url'] ?? route('integrations.google.redirect'));

        return [
            [
                'key' => 'total',
                'label' => 'Total Clicks (Google Ads)',
                'value' => number_format($googleClicks),
                'sub' => $googleNeedsReconnect
                    ? 'Google sync blocked — reconnect Ads'
                    : ($tracked > 0 ? ('Tracked '.$tracked) : 'Imported from Google Ads'),
                'tone' => 'purple',
                'show_reconnect' => $googleNeedsReconnect,
                'reconnect_url' => $googleReconnectUrl,
            ],
            [
                'key' => 'valid',
                'label' => 'Valid Clicks',
                'value' => number_format($valid),
                'sub' => number_format($validPct, 1).'% of tracked clicks',
                'tone' => 'green',
            ],
            [
                'key' => 'invalid',
                'label' => 'Invalid Clicks',
                'value' => number_format($invalid),
                'sub' => number_format($invalidPct, 1).'% of tracked clicks',
                'tone' => 'rose',
            ],
            [
                'key' => 'blocked',
                'label' => 'Blocked Clicks',
                'value' => number_format($blocked),
                'sub' => 'Blocked by protection',
                'tone' => 'rose',
            ],
            [
                'key' => 'waste',
                'label' => 'Estimated Waste Prevented',
                'value' => '$'.number_format($costSaved, 2),
                'sub' => 'Saved from invalid traffic',
                'tone' => 'green',
            ],
            [
                'key' => 'risk',
                'label' => 'Tracking Accuracy',
                'value' => (string) $trackingAccuracy.'%',
                'sub' => $avgRisk > 0
                    ? ('Avg risk '.$avgRisk.'/100')
                    : ('Tracked clicks '.$tracked),
                'tone' => 'amber',
            ],
        ];
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

        $totalClicks = (int) $rows->sum(fn ($visit) => (int) ($visit['visits'] ?? 1));
        $validClicks = (int) $rows->sum(fn ($visit) => (int) ($visit['valid_clicks'] ?? 0));
        $invalidClicks = (int) $rows->sum(fn ($visit) => (int) ($visit['invalid_clicks'] ?? 0));
        if ($validClicks === 0 && $invalidClicks === 0 && $totalClicks > 0) {
            $invalidClicks = $threatCount;
            $validClicks = max(0, $totalClicks - $invalidClicks);
        }
        $blockedClicks = (int) $rows->sum(fn ($visit) => (bool) ($visit['ip_is_blocked'] ?? false)
            ? (int) ($visit['visits'] ?? 1)
            : 0);
        if ($blockedClicks === 0) {
            $blockedClicks = $blockedCount;
        }

        $riskScores = $rows
            ->map(fn ($visit) => $visit['intel_risk_score'] ?? $visit['risk_summary']['score'] ?? null)
            ->filter(fn ($score) => is_numeric($score))
            ->map(fn ($score) => (float) $score)
            ->values();
        $avgRisk = $riskScores->isNotEmpty() ? (int) round($riskScores->avg()) : 0;

        // Rough waste estimate when CPC isn't available: $1.50 per invalid click.
        $wastePrevented = round($invalidClicks * 1.5, 2);
        $validPct = $totalClicks > 0 ? round(($validClicks / $totalClicks) * 100, 1) : 0.0;
        $invalidPct = $totalClicks > 0 ? round(($invalidClicks / $totalClicks) * 100, 1) : 0.0;

        $threatBuckets = [
            'vpn' => ['label' => 'VPN / Proxy', 'color' => '#A855F7', 'count' => 0],
            'datacenter' => ['label' => 'Data Center', 'color' => '#3B82F6', 'count' => 0],
            'geo' => ['label' => 'Geo Mismatch', 'color' => '#D6B27C', 'count' => 0],
            'device' => ['label' => 'Invalid Device', 'color' => '#C084FC', 'count' => 0],
            'bot' => ['label' => 'Bot Behavior', 'color' => '#22D3EE', 'count' => 0],
            'repeat' => ['label' => 'Repeated Clicks', 'color' => '#14B8A6', 'count' => 0],
        ];

        $riskBuckets = [
            'high' => ['label' => 'High Risk', 'color' => '#F43F5E', 'count' => 0],
            'medium' => ['label' => 'Medium Risk', 'color' => '#F59E0B', 'count' => 0],
            'low' => ['label' => 'Low Risk', 'color' => '#22C55E', 'count' => 0],
        ];

        $countryInvalid = [];

        foreach ($rows as $visit) {
            $visits = max(1, (int) ($visit['visits'] ?? 1));
            $invalid = (int) ($visit['invalid_clicks'] ?? 0);
            $isInvalid = $invalid > 0
                || filled($visit['threat_group'] ?? null)
                || filled($visit['threat_type'] ?? null)
                || (bool) ($visit['ip_is_blocked'] ?? false)
                || ($visit['intel_vpn'] ?? '') === 'Yes'
                || ($visit['intel_datacenter'] ?? '') === 'Yes'
                || ($visit['intel_proxy'] ?? '') === 'Yes';

            $weight = $invalid > 0 ? $invalid : ($isInvalid ? $visits : 0);
            if ($weight <= 0) {
                continue;
            }

            $group = strtolower(trim((string) ($visit['threat_group'] ?? '')));
            $type = strtolower(trim((string) ($visit['threat_type'] ?? '')));
            $blob = $group.' '.$type;
            $reasonsBlob = strtolower(implode(' ', array_map('strval', data_get($visit, 'risk_summary.reasons', []) ?: [])));
            $blob .= ' '.$reasonsBlob;

            $isVpn = (int) ($visit['vpn_hits'] ?? 0) > 0
                || ($visit['intel_vpn'] ?? '') === 'Yes'
                || ($visit['intel_proxy'] ?? '') === 'Yes'
                || str_contains($blob, 'vpn')
                || str_contains($blob, 'proxy');
            $isDc = (int) ($visit['data_center_hits'] ?? 0) > 0
                || ($visit['intel_datacenter'] ?? '') === 'Yes'
                || str_contains($blob, 'data_center')
                || str_contains($blob, 'datacenter')
                || str_contains($blob, 'hosting');
            $isGeo = str_contains($blob, 'geo')
                || str_contains($blob, 'mismatch')
                || str_contains($blob, 'location')
                || str_contains($blob, 'timezone');
            $isDevice = str_contains($blob, 'device')
                || str_contains($blob, 'fingerprint')
                || str_contains($blob, 'invalid_device')
                || str_contains($type, 'device');
            $isBot = str_contains($blob, 'bot') || str_contains($blob, 'automation');
            $isRepeat = $visits > 1
                || $invalid > 1
                || str_contains($blob, 'repeat')
                || str_contains($blob, 'duplicate')
                || str_contains($blob, 'multiple');

            // Priority: VPN → Data Center → Geo → Invalid Device → Bot → Repeated
            if ($isVpn) {
                $threatBuckets['vpn']['count'] += $weight;
            } elseif ($isDc) {
                $threatBuckets['datacenter']['count'] += $weight;
            } elseif ($isGeo) {
                $threatBuckets['geo']['count'] += $weight;
            } elseif ($isDevice) {
                $threatBuckets['device']['count'] += $weight;
            } elseif ($isBot) {
                $threatBuckets['bot']['count'] += $weight;
            } elseif ($isRepeat) {
                $threatBuckets['repeat']['count'] += $weight;
            } else {
                $threatBuckets['bot']['count'] += $weight;
            }

            $level = strtolower((string) ($visit['intel_risk_level'] ?? data_get($visit, 'risk_summary.level') ?? ''));
            $score = $visit['intel_risk_score'] ?? data_get($visit, 'risk_summary.score');
            if ($level === '' && is_numeric($score)) {
                $score = (float) $score;
                $level = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
            }
            if (! in_array($level, ['high', 'medium', 'low'], true)) {
                $level = 'medium';
            }
            $riskBuckets[$level]['count'] += 1;

            $country = trim((string) ($visit['country'] ?? ''));
            if ($country !== '' && $country !== '—' && $country !== 'Unknown') {
                $countryInvalid[$country] = ($countryInvalid[$country] ?? 0) + $weight;
            }
        }

        $threatSum = (int) array_sum(array_column($threatBuckets, 'count'));
        $threatTotal = max(1, $threatSum);
        $threatItems = [];
        $threatGradientStops = [];
        $cursor = 0.0;
        foreach ($threatBuckets as $bucket) {
            $pct = $threatSum > 0 ? round(($bucket['count'] / $threatTotal) * 100, 1) : 0.0;
            $threatItems[] = [
                'label' => $bucket['label'],
                'color' => $bucket['color'],
                'pct' => $pct,
                'count' => $bucket['count'],
                'count_label' => number_format($bucket['count']),
            ];
            if ($bucket['count'] <= 0) {
                continue;
            }
            $next = $cursor + $pct;
            $threatGradientStops[] = $bucket['color'].' '.$cursor.'% '.$next.'%';
            $cursor = $next;
        }
        if ($threatGradientStops === []) {
            $threatGradientStops[] = 'rgba(100,0,178,0.25) 0% 100%';
        }

        $uniqueIps = $rows->pluck('ip')->filter()->unique()->count();
        $riskTotal = max(1, array_sum(array_column($riskBuckets, 'count')));
        $riskItems = [];
        $riskGradientStops = [];
        $cursor = 0.0;
        foreach ($riskBuckets as $bucket) {
            $pct = round(($bucket['count'] / $riskTotal) * 100, 1);
            $riskItems[] = [
                'label' => $bucket['label'],
                'color' => $bucket['color'],
                'pct' => $pct,
                'count' => $bucket['count'],
                'count_label' => number_format($bucket['count']),
            ];
            $next = $cursor + max($pct, 0);
            $riskGradientStops[] = $bucket['color'].' '.$cursor.'% '.$next.'%';
            $cursor = $next;
        }

        arsort($countryInvalid);
        $topCountriesRaw = array_slice($countryInvalid, 0, 5, true);
        $countryMax = max(1, (int) (reset($topCountriesRaw) ?: 1));
        $countryInvalidTotal = max(1, array_sum($countryInvalid));
        $topCountries = [];
        foreach ($topCountriesRaw as $name => $count) {
            $topCountries[] = [
                'name' => $name,
                'count' => $count,
                'count_label' => number_format($count),
                'pct' => round(($count / $countryInvalidTotal) * 100, 1),
                'bar' => round(($count / $countryMax) * 100, 1),
                'flag' => $this->countryFlagEmoji((string) $name),
            ];
        }

        return [
            'kpis' => [
                [
                    'key' => 'total',
                    'label' => 'Total Clicks (Google Ads)',
                    'value' => number_format($totalClicks),
                    'sub' => 'Imported from Google Ads',
                    'tone' => 'purple',
                ],
                [
                    'key' => 'valid',
                    'label' => 'Valid Clicks',
                    'value' => number_format($validClicks),
                    'sub' => number_format($validPct, 1).'% of total clicks',
                    'tone' => 'green',
                ],
                [
                    'key' => 'invalid',
                    'label' => 'Invalid Clicks',
                    'value' => number_format($invalidClicks),
                    'sub' => number_format($invalidPct, 1).'% of total clicks',
                    'tone' => 'rose',
                ],
                [
                    'key' => 'blocked',
                    'label' => 'Blocked Clicks',
                    'value' => number_format($blockedClicks),
                    'sub' => 'Blocked by protection',
                    'tone' => 'rose',
                ],
                [
                    'key' => 'waste',
                    'label' => 'Estimated Waste Prevented',
                    'value' => '$'.number_format($wastePrevented, 2),
                    'sub' => 'Saved from invalid traffic',
                    'tone' => 'green',
                ],
                [
                    'key' => 'risk',
                    'label' => 'Avg. Risk Score',
                    'value' => (string) $avgRisk,
                    'sub' => 'Out of 100',
                    'tone' => 'amber',
                ],
            ],
            'charts' => [
                'updated_at' => now()->toIso8601String(),
                'threat' => [
                    'total' => array_sum(array_column($threatBuckets, 'count')),
                    'total_label' => number_format(array_sum(array_column($threatBuckets, 'count'))),
                    'center_label' => 'Invalid Clicks',
                    'gradient' => 'conic-gradient('.implode(', ', $threatGradientStops).')',
                    'items' => $threatItems,
                ],
                'risk' => [
                    'total' => $uniqueIps,
                    'total_label' => number_format($uniqueIps),
                    'center_label' => 'Unique IPs',
                    'gradient' => 'conic-gradient('.implode(', ', $riskGradientStops).')',
                    'items' => $riskItems,
                ],
                'countries' => $topCountries,
                'high_risk_ips' => $this->buildHighRiskIpCards($rows),
            ],
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

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildHighRiskIpCards(Collection $rows): array
    {
        return $rows
            ->map(function (array $visit): ?array {
                $score = $visit['intel_risk_score'] ?? data_get($visit, 'risk_summary.score');
                $level = strtolower((string) ($visit['intel_risk_level'] ?? data_get($visit, 'risk_summary.level') ?? ''));
                if (! is_numeric($score) && $level === '') {
                    return null;
                }
                $score = is_numeric($score) ? (int) round((float) $score) : match ($level) {
                    'high' => 85,
                    'medium' => 55,
                    default => 25,
                };
                if ($score < 55 && $level !== 'high') {
                    return null;
                }

                $group = strtolower(trim((string) ($visit['threat_group'] ?? '')));
                $type = strtolower(trim((string) ($visit['threat_type'] ?? '')));
                $blob = $group.' '.$type;
                $category = 'High Risk';
                $dot = '#F43F5E';
                $isDc = (int) ($visit['data_center_hits'] ?? 0) > 0
                    || ($visit['intel_datacenter'] ?? '') === 'Yes'
                    || str_contains($blob, 'data_center')
                    || str_contains($blob, 'datacenter');
                $isVpn = (int) ($visit['vpn_hits'] ?? 0) > 0
                    || ($visit['intel_vpn'] ?? '') === 'Yes'
                    || ($visit['intel_proxy'] ?? '') === 'Yes'
                    || str_contains($blob, 'vpn')
                    || str_contains($blob, 'proxy');
                if ($isDc) {
                    $category = 'Datacenter';
                    $dot = '#F43F5E';
                } elseif ($isVpn) {
                    $category = 'VPN / Proxy';
                    $dot = '#F43F5E';
                } elseif (str_contains($blob, 'bot')) {
                    $category = 'Bot Behavior';
                    $dot = '#F59E0B';
                } elseif ((int) ($visit['invalid_clicks'] ?? 0) > 1 || (int) ($visit['visits'] ?? 0) > 1 || str_contains($blob, 'repeat')) {
                    $category = 'Repeated Clicks';
                    $dot = '#F43F5E';
                } elseif (str_contains($blob, 'geo') || str_contains($blob, 'mismatch')) {
                    $category = 'Geo Mismatch';
                    $dot = '#D6B27C';
                }

                $invalid = (int) ($visit['invalid_clicks'] ?? 0);
                if ($invalid <= 0) {
                    $invalid = max(1, (int) ($visit['visits'] ?? 1));
                }

                $atRaw = $visit['last_click_at'] ?? null;
                $sortAt = 0;
                $ago = (string) ($visit['last_click_label'] ?? '—');
                if (filled($atRaw)) {
                    try {
                        $parsed = \Illuminate\Support\Carbon::parse((string) $atRaw);
                        $sortAt = $parsed->getTimestamp();
                        $ago = $parsed->diffForHumans(null, true);
                        $ago = str_replace(
                            [' seconds', ' second', ' minutes', ' minute', ' hours', ' hour', ' days', ' day'],
                            ['s', 's', ' mins', ' min', 'h', 'h', 'd', 'd'],
                            $ago
                        );
                        $ago = $ago === '0s' ? 'Just now' : ($ago.' ago');
                    } catch (\Throwable) {
                        // keep last_click_label fallback
                    }
                }

                return [
                    'id' => $visit['id'] ?? null,
                    'ip' => (string) ($visit['ip'] ?? '—'),
                    'risk' => $score,
                    'risk_tone' => $score >= 75 ? 'high' : 'medium',
                    'category' => $category,
                    'dot' => $dot,
                    'invalid_clicks' => $invalid,
                    'invalid_label' => number_format($invalid).' invalid click'.($invalid === 1 ? '' : 's'),
                    'ago' => $ago,
                    '_sort_at' => $sortAt,
                ];
            })
            ->filter()
            ->sortByDesc('_sort_at')
            ->take(12)
            ->values()
            ->map(function (array $card) {
                unset($card['_sort_at']);

                return $card;
            })
            ->all();
    }

    private function countryFlagEmoji(string $country): string
    {
        $map = [
            'united states' => '🇺🇸', 'usa' => '🇺🇸', 'us' => '🇺🇸',
            'germany' => '🇩🇪', 'de' => '🇩🇪',
            'india' => '🇮🇳', 'in' => '🇮🇳',
            'singapore' => '🇸🇬', 'sg' => '🇸🇬',
            'russia' => '🇷🇺', 'ru' => '🇷🇺',
            'united kingdom' => '🇬🇧', 'uk' => '🇬🇧', 'gb' => '🇬🇧',
            'canada' => '🇨🇦', 'ca' => '🇨🇦',
            'france' => '🇫🇷', 'fr' => '🇫🇷',
            'brazil' => '🇧🇷', 'br' => '🇧🇷',
            'pakistan' => '🇵🇰', 'pk' => '🇵🇰',
            'china' => '🇨🇳', 'cn' => '🇨🇳',
            'australia' => '🇦🇺', 'au' => '🇦🇺',
            'netherlands' => '🇳🇱', 'nl' => '🇳🇱',
            'japan' => '🇯🇵', 'jp' => '🇯🇵',
        ];

        $key = strtolower(trim($country));
        if (isset($map[$key])) {
            return $map[$key];
        }

        if (strlen($country) === 2 && ctype_alpha($country)) {
            $code = strtoupper($country);
            return implode(
                '',
                array_map(
                    fn ($char) => mb_chr(0x1F1E6 + ord($char) - ord('A')),
                    str_split($code)
                )
            );
        }

        return '🌐';
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

        $detectionAudits = collect();
        if ($domain && Schema::hasTable('detection_settings_audits')) {
            $detectionAudits = DetectionSettingsAudit::query()
                ->with('user:id,name,email')
                ->where('domain_id', $domain->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        $googleAdsAccounts = GoogleAdsAccount::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $request->user()->id))
            ->synced()
            ->orderBy('account_name')
            ->get();

        return view('paid-marketing.detection-settings', [
            'domains' => $domains,
            'domain' => $domain,
            'settings' => $settings,
            'ipExclusions' => $domain ? $this->googleExclusionRowsForDomain($domain->id) : [],
            'geoCountries' => $geoCatalog->countries(null, 300),
            'geoEndpoints' => [
                'countries' => route('paid-marketing.geo.countries'),
                'states' => route('paid-marketing.geo.states'),
                'cities' => route('paid-marketing.geo.cities'),
            ],
            'countryAudits' => $detectionAudits,
            'detectionAudits' => $detectionAudits,
            'detectionProfiles' => \App\Support\DetectionProfiles::catalog(),
            'googleAdsAccounts' => $googleAdsAccounts,
        ]);
    }

    public function updateDetectionSettings(Request $request, Domain $domain): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'invalid_bot_action' => ['required', 'in:allow,block,flag'],
            'invalid_malicious_action' => ['required', 'in:allow,block,flag'],
            'suspicious_enabled' => ['nullable', 'boolean'],
            'detection_profile' => ['nullable', 'in:standard,advanced,extreme,marketing'],
            'rapid_window_seconds' => ['nullable', 'integer', 'min:10', 'max:600'],
            'rapid_flag_at' => ['nullable', 'integer', 'min:1', 'max:10'],
            'rapid_block_at' => ['nullable', 'integer', 'min:1', 'max:20'],
            'hourly_valid_click_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'daily_valid_click_limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'weekly_valid_click_limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'monthly_valid_click_limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'fail_mode' => ['nullable', 'in:open,closed'],
            'block_response' => ['nullable', 'in:hide,blank,redirect,challenge,forbid'],
            'block_redirect_url' => ['nullable', 'string', 'max:2048'],
            'recording_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'suspicious_vpn' => ['required', 'in:allow,block,flag'],
            'suspicious_proxy' => ['required', 'in:allow,block,flag'],
            'suspicious_data_center' => ['required', 'in:allow,block,flag'],
            'suspicious_abnormal_rate_limit' => ['required', 'in:allow,block,flag'],
            'session_recordings' => ['nullable', 'boolean'],
            'frequency_capping' => ['nullable', 'boolean'],
            'out_of_geo_enabled' => ['nullable', 'boolean'],
            'out_of_geo_countries' => ['nullable', 'string'],
            'out_of_geo_audience' => ['nullable', 'string'],
            'google_geo_block_enabled' => ['nullable', 'boolean'],
            'google_geo_block_audience' => ['nullable', 'string'],
            'control_mode' => ['nullable', 'in:allow_countries,block_countries,allow_ips,block_ips,mixed'],
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
            'geo_rule_scope' => ['nullable', 'in:domain,workspace'],
            'consent_required' => ['nullable', 'boolean'],
            'consent_regions' => ['nullable', 'string'],
            'recording_mask_passwords' => ['nullable', 'boolean'],
            'save_workspace_geo' => ['nullable', 'boolean'],
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

        $geoBlockAudience = null;
        if (! empty($data['google_geo_block_audience'])) {
            $decodedBlock = json_decode((string) $data['google_geo_block_audience'], true);
            if (is_array($decodedBlock)) {
                $geoBlockAudience = $decodedBlock;
            }
        }

        $controlMode = (string) ($data['control_mode'] ?? 'mixed');
        $outOfGeoEnabled = (bool) ($data['out_of_geo_enabled'] ?? false);
        $geoBlockEnabled = (bool) ($data['google_geo_block_enabled'] ?? false);
        $allowListEnabled = (bool) ($data['allow_list_enabled'] ?? false);
        $blockListEnabled = (bool) ($data['block_list_enabled'] ?? false);

        // Mode selector drives the primary enable flags for clarity (CT-01).
        if ($controlMode === 'allow_countries') {
            $outOfGeoEnabled = true;
        } elseif ($controlMode === 'block_countries') {
            $geoBlockEnabled = true;
        } elseif ($controlMode === 'allow_ips') {
            $allowListEnabled = true;
        } elseif ($controlMode === 'block_ips') {
            $blockListEnabled = true;
        }

        $before = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();

        $settings = DomainDetectionSetting::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => $data['invalid_bot_action'],
                'invalid_malicious_action' => $data['invalid_malicious_action'],
                'suspicious_enabled' => (bool) ($data['suspicious_enabled'] ?? false),
                'detection_profile' => (string) ($data['detection_profile'] ?? 'standard'),
                'detection_thresholds' => [
                    'rapid_window_seconds' => (int) ($data['rapid_window_seconds'] ?? 120),
                    'rapid_flag_at' => (int) ($data['rapid_flag_at'] ?? 1),
                    'rapid_block_at' => (int) ($data['rapid_block_at'] ?? 2),
                    'hourly_valid_click_limit' => (int) ($data['hourly_valid_click_limit'] ?? 3),
                    'daily_valid_click_limit' => (int) ($data['daily_valid_click_limit'] ?? 2),
                    'weekly_valid_click_limit' => (int) ($data['weekly_valid_click_limit'] ?? 100),
                    'monthly_valid_click_limit' => (int) ($data['monthly_valid_click_limit'] ?? 300),
                ],
                'fail_mode' => (string) ($data['fail_mode'] ?? 'open'),
                'block_response' => (string) ($data['block_response'] ?? 'hide'),
                'block_redirect_url' => ($redir = trim((string) ($data['block_redirect_url'] ?? ''))) !== '' ? $redir : null,
                'recording_retention_days' => (int) ($data['recording_retention_days'] ?? 30),
                'geo_rule_scope' => (string) ($data['geo_rule_scope'] ?? 'domain'),
                'consent_required' => (bool) ($data['consent_required'] ?? false),
                'consent_regions' => collect(explode(',', (string) ($data['consent_regions'] ?? '')))
                    ->map(fn ($v) => strtoupper(trim($v)))
                    ->filter()
                    ->values()
                    ->all(),
                'recording_mask_passwords' => $request->boolean('recording_mask_passwords', true),
                'suspicious_matrix' => [
                    'vpn' => $data['suspicious_vpn'],
                    'proxy' => $data['suspicious_proxy'],
                    'data_center' => $data['suspicious_data_center'],
                    'abnormal_rate_limit' => $data['suspicious_abnormal_rate_limit'],
                ],
                'session_recordings' => (bool) ($data['session_recordings'] ?? false),
                'frequency_capping' => (bool) ($data['frequency_capping'] ?? false),
                'control_mode' => $controlMode,
                'out_of_geo_enabled' => $outOfGeoEnabled,
                'out_of_geo_countries' => $countries,
                'out_of_geo_audience' => $audience,
                'google_geo_block_enabled' => $geoBlockEnabled,
                'google_geo_block_audience' => $geoBlockAudience,
                'allow_list_enabled' => $allowListEnabled,
                'allow_list_ips' => ($allowRaw = trim((string) ($data['allow_list_ips'] ?? ''))) === ''
                    ? null
                    : implode("\n", IpListParser::normalizeLines($allowRaw)),
                'block_list_enabled' => $blockListEnabled,
                'block_list_ips' => ($blockRaw = trim((string) ($data['block_list_ips'] ?? ''))) === ''
                    ? null
                    : implode("\n", IpListParser::normalizeLines($blockRaw)),
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

        if ($request->boolean('save_workspace_geo')) {
            $request->user()->update([
                'workspace_geo_settings' => [
                    'out_of_geo_enabled' => $settings->out_of_geo_enabled,
                    'out_of_geo_audience' => $settings->out_of_geo_audience,
                    'out_of_geo_countries' => $settings->out_of_geo_countries,
                    'google_geo_block_enabled' => $settings->google_geo_block_enabled,
                    'google_geo_block_audience' => $settings->google_geo_block_audience,
                ],
            ]);
        }

        if ($before) {
            $logger = app(DetectionSettingsAuditLogger::class);
            $fresh = $settings->fresh();
            $logger->logCountryRuleChanges($before, $fresh, $request->user()->id, 'domain');
            $logger->logConfigChanges($before, $fresh, $request->user()->id, 'domain');
        }

        $geoSync = app(GoogleAdsLocationExclusionSyncService::class)
            ->syncSettingsForDomain($domain->fresh(['googleAdsAccount.connection']), $settings, true);

        $status = 'Detection settings saved.';
        if (($geoSync['queued'] ?? 0) > 0 || ($geoSync['synced'] ?? 0) > 0) {
            $status .= sprintf(
                ' Google Ads location exclusions: %d queued, %d synced.',
                (int) ($geoSync['queued'] ?? 0),
                (int) ($geoSync['synced'] ?? 0)
            );
        }

        return redirect()
            ->route('paid-marketing.detection-settings', ['domain_id' => $domain->id])
            ->with('status', $status);
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
        $domainIds = $this->scopedPaidDomainIds($request);
        $googleTz = UserTimezone::resolveGoogleAccountTimezone(
            $user,
            $domainId > 0 ? $domainId : null,
            $domainIds,
        );
        $reportingTz = UserTimezone::reportingTimezoneForUser($user, $googleTz);
        [$metricFrom, $metricTo] = UserTimezone::calendarDateRangeFromRequest($request, $user, 6, $reportingTz);

        return [$metricFrom, $metricTo, $googleTz, $reportingTz];
    }
}

