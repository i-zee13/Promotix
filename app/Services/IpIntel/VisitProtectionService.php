<?php

namespace App\Services\IpIntel;

use App\Jobs\EnrichIpIntelJob;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Support\GoogleClickAttribution;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitProtectionService
{
    public function __construct(
        private readonly IpIntelService $intel,
        private readonly IpFraudEvaluator $evaluator,
    ) {
    }

    /**
     * @return array{
     *   ipLog: IpLog,
     *   detection: array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>},
     *   enforce_block: bool,
     *   prior_blocked: bool
     * }
     */
    public function assess(
        Domain $domain,
        IpLog $ipLog,
        ?string $country,
        ?string $sessionId,
        bool $isCrawler = false,
        bool $isPaidTraffic = false,
        ?Carbon $visitedAt = null,
    ): array {
        try {
            return $this->assessInner(
                $domain,
                $ipLog,
                $country,
                $sessionId,
                $isCrawler,
                $isPaidTraffic,
                $visitedAt,
            );
        } catch (\Throwable $e) {
            report($e);
            $settings = \App\Models\DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
            $failClosed = ($settings?->fail_mode ?? 'open') === 'closed';

            return [
                'ipLog' => $ipLog,
                'detection' => [
                    'threat_score' => $failClosed ? 100 : 0,
                    'threat_group' => $failClosed ? 'service_outage' : null,
                    'action_taken' => $failClosed ? 'block' : 'allow',
                    'reasons' => [$failClosed ? 'fail_closed' : 'fail_open'],
                ],
                'enforce_block' => $failClosed,
                'prior_blocked' => false,
            ];
        }
    }

    /**
     * @return array{
     *   ipLog: IpLog,
     *   detection: array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>},
     *   enforce_block: bool,
     *   prior_blocked: bool
     * }
     */
    private function assessInner(
        Domain $domain,
        IpLog $ipLog,
        ?string $country,
        ?string $sessionId,
        bool $isCrawler = false,
        bool $isPaidTraffic = false,
        ?Carbon $visitedAt = null,
    ): array {
        if (AllowListMatcher::isAllowListed($domain, $ipLog->ip)) {
            if ($ipLog->is_blocked) {
                $ipLog->is_blocked = false;
                $ipLog->save();
            }

            return $this->allowListedResult($domain, $ipLog, $isPaidTraffic);
        }

        // Paid traffic always re-evaluates with the current pipeline so a wiped
        // exclusion list / new ADS rules are not short-circuited by sticky ip_logs.is_blocked.
        if ($ipLog->is_blocked && ! $isPaidTraffic) {
            return $this->blockedResult($ipLog, $domain, true, $isPaidTraffic);
        }

        $ipLog = $this->intel->enrichIfStale($ipLog);
        EnrichIpIntelJob::dispatch($ipLog->id);

        $sessionHits = $this->sessionHits($domain, $sessionId);
        $at = $visitedAt ?? UserTimezone::nowUtc();
        $ipRecentHits = $this->ipRecentHits($domain, $ipLog->ip, $at);
        $ipMinuteHits = $this->ipMinuteHits($domain, $ipLog->ip, $at);
        $paidClicksToday = $isPaidTraffic
            ? $this->paidClicksTodayForIp($domain, $ipLog->ip, $visitedAt ?? UserTimezone::nowUtc())
            : 0;

        $settings = \App\Models\DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        $thresholds = \App\Support\DetectionProfiles::thresholdsFor(
            $settings?->detection_profile,
            is_array($settings?->detection_thresholds) ? $settings->detection_thresholds : null,
        );
        $rapidWindow = (int) ($thresholds['rapid_window_seconds'] ?? IpFraudEvaluator::PAID_RAPID_WINDOW_SECONDS);

        $paidClicksInRapidWindow = $isPaidTraffic
            ? $this->paidClicksInWindowForIp($domain, $ipLog->ip, $at, $rapidWindow)
            : 0;

        $resolvedCountry = $country ?? $ipLog->intel_country_code ?? $ipLog->intel_country_name;
        $detection = $this->evaluator->evaluate(
            $domain,
            $ipLog,
            $resolvedCountry,
            $sessionHits,
            $ipRecentHits,
            $isCrawler,
            $isPaidTraffic,
            $paidClicksToday,
            $ipMinuteHits,
            $paidClicksInRapidWindow,
        );

        if ($detection['action_taken'] === 'block' && ! AllowListMatcher::reasonsIndicateAllowList($detection['reasons'])) {
            $ipLog->is_blocked = true;
            $ipLog->save();
        } elseif ($ipLog->is_blocked) {
            // Prior sticky block no longer matches current rules — clear so UI/pipeline stay in sync.
            $ipLog->is_blocked = false;
            $ipLog->save();
        }

        return [
            'ipLog' => $ipLog->fresh(),
            'detection' => $detection,
            'enforce_block' => $this->shouldEnforceBlock($domain, $detection, $isPaidTraffic, $ipLog->ip),
            'prior_blocked' => false,
        ];
    }

    public function isAllowListed(Domain $domain, string $ip): bool
    {
        return AllowListMatcher::isAllowListed($domain, $ip);
    }

    public function touchIpLog(string $ip, string $userAgent, ?string $path, ?string $referrer): IpLog
    {
        $ipLog = IpLog::firstOrNew(['ip' => $ip]);
        if (! $ipLog->exists) {
            $ipLog->hits = 0;
        }

        $ipLog->hits = ($ipLog->hits ?? 0) + 1;
        $ipLog->user_agent = $userAgent;
        $ipLog->last_seen_at = now();
        $ipLog->last_path = $path;
        $ipLog->last_referrer = $referrer;
        $ipLog->save();

        return $ipLog;
    }

    /**
     * @param  array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>}  $detection
     */
    public function shouldEnforceBlock(Domain $domain, array $detection, bool $isPaidTraffic = false, ?string $ip = null): bool
    {
        if ($domain->monitoring_only_mode) {
            return false;
        }

        if (AllowListMatcher::reasonsIndicateAllowList($detection['reasons'] ?? [])) {
            return false;
        }

        if ($ip !== null && AllowListMatcher::isAllowListed($domain, $ip)) {
            return false;
        }

        // Black-screen block is paid marketing only; bot protection still detects and logs.
        if (! $isPaidTraffic) {
            return false;
        }

        return $detection['action_taken'] === 'block';
    }

    public function shouldEnforceCaptcha(Domain $domain, array $detection, ?string $ip = null): bool
    {
        if ($domain->monitoring_only_mode) {
            return false;
        }

        if (AllowListMatcher::reasonsIndicateAllowList($detection['reasons'] ?? [])) {
            return false;
        }

        if ($ip !== null && AllowListMatcher::isAllowListed($domain, $ip)) {
            return false;
        }

        return $detection['action_taken'] === 'flag';
    }

    /**
     * @param  array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>}  $detection
     * @return array<string, mixed>
     */
    public function clientPayload(
        array $detection,
        bool $enforceBlock,
        bool $captchaRequired = false,
        bool $recordSession = false,
        ?int $visitId = null,
        ?Domain $domain = null,
        int $recordingMs = 10000,
    ): array {
        $settings = $domain
            ? DomainDetectionSetting::query()->where('domain_id', $domain->id)->first()
            : null;

        $blockResponse = (string) ($settings?->block_response ?? 'hide');
        if (! in_array($blockResponse, ['hide', 'blank', 'redirect', 'challenge', 'forbid'], true)) {
            $blockResponse = 'hide';
        }

        $payload = [
            'ok' => true,
            'blocked' => $enforceBlock,
            'block_response' => $enforceBlock ? $blockResponse : null,
            'block_redirect_url' => $enforceBlock && $blockResponse === 'redirect'
                ? ($settings?->block_redirect_url ?: null)
                : null,
            'captcha_required' => $captchaRequired || ($enforceBlock && $blockResponse === 'challenge'),
            'action' => $detection['action_taken'],
            'threat_group' => $detection['threat_group'],
            'reasons' => $detection['reasons'],
        ];

        if (! empty($detection['paid_identity']) && is_array($detection['paid_identity'])) {
            $payload['paid_identity'] = $detection['paid_identity'];
            $payload['identity_confidence'] = $detection['paid_identity']['identity_confidence'] ?? null;
        }
        if (! empty($detection['ads_detections']) && is_array($detection['ads_detections'])) {
            $payload['triggered_rules'] = array_values(array_map(
                fn ($row) => (string) ($row['rule_code'] ?? ''),
                $detection['ads_detections']
            ));
            $payload['ads_detections'] = $detection['ads_detections'];
            $payload['ads_ruleset_version'] = $detection['ads_ruleset_version'] ?? null;
            $payload['block_scope'] = $detection['block_scope'] ?? null;
        }
        if (! empty($detection['traffic_status'])) {
            $payload['traffic_status'] = $detection['traffic_status'];
        }
        if (isset($detection['paid_risk_score'])) {
            $payload['paid_risk_score'] = $detection['paid_risk_score'];
        }
        if (isset($detection['ip_exclusion_status'])) {
            $payload['ip_exclusion_status'] = $detection['ip_exclusion_status'];
            $payload['ip_exclusion_eligible'] = (bool) ($detection['ip_exclusion_eligible'] ?? false);
        }

        if ($recordSession) {
            $payload['record_session'] = true;
            $payload['recording_ms'] = max(5000, min(60000, $recordingMs));
            if ($visitId !== null) {
                $payload['visit_id'] = $visitId;
            }
        }

        return $payload;
    }

    /** Bot protection: skip counting organic refresh in same session on same calendar day. */
    public function shouldSkipOrganicRepeatVisit(
        Domain $domain,
        ?string $sessionId,
        bool $isPaidTraffic,
        Carbon $visitedAt,
    ): bool {
        if ($isPaidTraffic || $sessionId === null || ! Schema::hasTable('visits')) {
            return false;
        }

        $domain->loadMissing('user');
        $tz = UserTimezone::forUser($domain->user);
        $day = $visitedAt->copy()->timezone($tz)->toDateString();
        $from = Carbon::parse($day, $tz)->startOfDay()->utc()->toDateTimeString();
        $to = Carbon::parse($day, $tz)->endOfDay()->utc()->toDateTimeString();

        $query = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('session_id', $sessionId)
            ->whereBetween('visited_at', [$from, $to]);

        GoogleClickAttribution::excludeClickIds($query);

        return $query->exists();
    }

    private function sessionHits(Domain $domain, ?string $sessionId): int
    {
        if ($sessionId === null || ! Schema::hasTable('ip_sessions')) {
            return 1;
        }

        return (int) (DB::table('ip_sessions')
            ->where('domain_id', $domain->id)
            ->where('session_id', $sessionId)
            ->value('hits') ?? 0) + 1;
    }

    private function ipRecentHits(Domain $domain, string $ip, Carbon $visitedAt): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        return (int) DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('visited_at', '>=', $visitedAt->copy()->subMinutes(5))
            ->count();
    }

    private function ipMinuteHits(Domain $domain, string $ip, Carbon $visitedAt): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        return (int) DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('visited_at', '>=', $visitedAt->copy()->subMinute())
            ->count();
    }

    /** Paid clicks from this IP today (calendar day, domain owner TZ) — before the current hit is saved. */
    private function paidClicksTodayForIp(Domain $domain, string $ip, Carbon $visitedAt): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        $domain->loadMissing('user');
        $tz = UserTimezone::forUser($domain->user);
        $day = $visitedAt->copy()->timezone($tz)->toDateString();
        $from = Carbon::parse($day, $tz)->startOfDay()->utc()->toDateTimeString();
        $to = Carbon::parse($day, $tz)->endOfDay()->utc()->toDateTimeString();

        $query = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->whereBetween('visited_at', [$from, $to]);

        GoogleClickAttribution::applyHasClickIdFilter($query);

        if (Schema::hasColumn('visits', 'is_invalid_traffic')) {
            $query->where('is_invalid_traffic', 0);
        }

        if (Schema::hasColumn('visits', 'is_duplicate_paid_click')) {
            $query->where('is_duplicate_paid_click', 0);
        }

        return (int) $query->count();
    }

    /** Prior paid clicks from this IP inside the rapid-repeat window (before current hit). */
    private function paidClicksInWindowForIp(Domain $domain, string $ip, Carbon $visitedAt, int $windowSeconds): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        $query = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('visited_at', '>=', $visitedAt->copy()->subSeconds($windowSeconds)->toDateTimeString())
            ->where('visited_at', '<', $visitedAt->toDateTimeString());

        GoogleClickAttribution::applyHasClickIdFilter($query);

        if (Schema::hasColumn('visits', 'is_duplicate_paid_click')) {
            $query->where('is_duplicate_paid_click', 0);
        }

        return (int) $query->count();
    }

    /**
     * @return array{ipLog: IpLog, detection: array, enforce_block: bool, prior_blocked: bool}
     */
    private function allowListedResult(Domain $domain, IpLog $ipLog, bool $isPaidTraffic): array
    {
        $detection = [
            'threat_score' => 0,
            'threat_group' => null,
            'action_taken' => 'allow',
            'reasons' => ['allow_list'],
        ];

        return [
            'ipLog' => $ipLog,
            'detection' => $detection,
            'enforce_block' => false,
            'prior_blocked' => false,
        ];
    }

    /**
     * @return array{ipLog: IpLog, detection: array, enforce_block: bool, prior_blocked: bool}
     */
    private function blockedResult(IpLog $ipLog, Domain $domain, bool $priorBlocked, bool $isPaidTraffic = false): array
    {
        $detection = [
            'threat_score' => 100,
            'threat_group' => 'blocked',
            'action_taken' => 'block',
            'reasons' => [$priorBlocked ? 'previously_blocked' : 'blocked'],
        ];

        return [
            'ipLog' => $ipLog,
            'detection' => $detection,
            'enforce_block' => $this->shouldEnforceBlock($domain, $detection, $isPaidTraffic, $ipLog->ip),
            'prior_blocked' => $priorBlocked,
        ];
    }
}
