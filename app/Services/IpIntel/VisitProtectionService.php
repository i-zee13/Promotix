<?php

namespace App\Services\IpIntel;

use App\Jobs\EnrichIpIntelJob;
use App\Models\Domain;
use App\Models\IpLog;
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
    ): array {
        if ($ipLog->is_blocked) {
            return $this->blockedResult($ipLog, $domain, true);
        }

        $ipLog = $this->intel->enrichIfStale($ipLog);
        EnrichIpIntelJob::dispatch($ipLog->id);

        $sessionHits = $this->sessionHits($domain, $sessionId);
        $ipRecentHits = $this->ipRecentHits($domain, $ipLog->ip);

        $resolvedCountry = $country ?? $ipLog->intel_country_code ?? $ipLog->intel_country_name;
        $detection = $this->evaluator->evaluate(
            $domain,
            $ipLog,
            $resolvedCountry,
            $sessionHits,
            $ipRecentHits,
            $isCrawler,
        );

        if ($detection['action_taken'] === 'block') {
            $ipLog->is_blocked = true;
            $ipLog->save();
        }

        return [
            'ipLog' => $ipLog->fresh(),
            'detection' => $detection,
            'enforce_block' => $this->shouldEnforceBlock($domain, $detection),
            'prior_blocked' => false,
        ];
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
    public function shouldEnforceBlock(Domain $domain, array $detection): bool
    {
        if ($domain->monitoring_only_mode) {
            return false;
        }

        return $detection['action_taken'] === 'block';
    }

    /**
     * @param  array{threat_score: int, threat_group: ?string, action_taken: string, reasons: list<string>}  $detection
     * @return array<string, mixed>
     */
    public function clientPayload(array $detection, bool $enforceBlock): array
    {
        return [
            'ok' => true,
            'blocked' => $enforceBlock,
            'action' => $detection['action_taken'],
            'threat_group' => $detection['threat_group'],
            'reasons' => $detection['reasons'],
        ];
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

    private function ipRecentHits(Domain $domain, string $ip): int
    {
        if (! Schema::hasTable('visits')) {
            return 0;
        }

        return (int) DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where('ip', $ip)
            ->where('visited_at', '>=', now()->subMinutes(5))
            ->count();
    }

    /**
     * @return array{ipLog: IpLog, detection: array, enforce_block: bool, prior_blocked: bool}
     */
    private function blockedResult(IpLog $ipLog, Domain $domain, bool $priorBlocked): array
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
            'enforce_block' => ! $domain->monitoring_only_mode,
            'prior_blocked' => $priorBlocked,
        ];
    }
}
