<?php

namespace App\Support\PaidAdvertising;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Support\DetectionProfiles;
use Illuminate\Http\Request;

/**
 * Google-only paid advertising decision pipe (Meta / Microsoft skipped).
 *
 * Flow: identity → windows → detections → decisive/correlated → classify → IP exclusion gate.
 */
class PaidAdvertisingPipeline
{
    public const RULESET_VERSION = 'paid-risk-v4.0-google';

    public function __construct(
        private readonly PaidIdentityResolver $identities,
        private readonly ClickWindowCounter $windows,
        private readonly AdsRepeatEvaluator $repeats,
        private readonly AdsTimingEvaluator $timing,
        private readonly AdsAttributionEvaluator $attribution,
        private readonly PaidTrafficClassifier $classifier,
        private readonly IpExclusionEligibilityGate $exclusionGate,
    ) {
    }

    /**
     * @param  array<string, mixed>  $detection
     * @param  array{
     *   paid_id?: ?string,
     *   click_type?: ?string,
     *   duplicate_paid_click?: bool,
     *   is_paid_traffic?: bool
     * }  $attribution
     * @return array{
     *   identity: ResolvedPaidIdentity,
     *   windows: array<string, array<string,int>>,
     *   detections: list<array<string, mixed>>,
     *   detection: array<string, mixed>,
     *   exclusion: array{eligible: bool, status: string, reasons: list<string>}
     * }
     */
    public function enrichPaidDetection(
        Request $request,
        Domain $domain,
        string $ip,
        ?string $sessionId,
        array $detection,
        ?string $clientFingerprint = null,
        array $attribution = [],
    ): array {
        $identity = $this->identities->resolve(
            $request,
            (int) $domain->id,
            $ip,
            $sessionId,
            $clientFingerprint,
        );

        $snapshot = $this->windows->snapshot(
            (int) $domain->id,
            $ip,
            $identity->browserId,
            $identity->deviceId,
            $identity->publicId,
        );

        $thresholds = $this->thresholdsFor($domain);
        $adsDetections = array_values(array_merge(
            $this->repeats->evaluate($identity, $snapshot, $thresholds),
            $this->timing->evaluate((int) $domain->id, $identity, $snapshot),
            $this->attribution->evaluate(array_merge([
                'is_paid_traffic' => true,
            ], $attribution)),
        ));

        $merged = $this->mergeIntoDetection($detection, $adsDetections, $identity);
        $exclusion = $this->exclusionGate->evaluate((int) $domain->id, $ip, $identity, $merged);
        $merged['ip_exclusion_status'] = $exclusion['status'];
        $merged['ip_exclusion_eligible'] = $exclusion['eligible'];
        $merged['ip_exclusion_reasons'] = $exclusion['reasons'];

        return [
            'identity' => $identity,
            'windows' => $snapshot,
            'detections' => $adsDetections,
            'detection' => $merged,
            'exclusion' => $exclusion,
        ];
    }

    /**
     * @return array{name:string,value:string,minutes:int}[]
     */
    public function cookiesFor(ResolvedPaidIdentity $identity): array
    {
        return $this->identities->cookiesToQueue($identity);
    }

    public function recordClick(
        Domain $domain,
        string $ip,
        ResolvedPaidIdentity $identity,
        ?string $campaign = null,
    ): void {
        $this->windows->recordClick(
            (int) $domain->id,
            $ip,
            $identity->browserId,
            $identity->deviceId,
            $identity->publicId,
            $campaign,
        );
    }

    /**
     * @return array<string, int|bool>
     */
    private function thresholdsFor(Domain $domain): array
    {
        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        $profile = is_string($settings?->detection_profile ?? null)
            ? (string) $settings->detection_profile
            : DetectionProfiles::STANDARD;

        return DetectionProfiles::thresholdsFor(
            $profile,
            is_array($settings?->detection_thresholds ?? null) ? $settings->detection_thresholds : []
        );
    }

    /**
     * @param  array<string, mixed>  $detection
     * @param  list<array<string, mixed>>  $adsDetections
     * @return array<string, mixed>
     */
    private function mergeIntoDetection(array $detection, array $adsDetections, ResolvedPaidIdentity $identity): array
    {
        $detection['paid_identity'] = $identity->toArray();
        $detection['ad_platform'] = 'google_ads';
        $detection['ads_ruleset_version'] = self::RULESET_VERSION;

        if ($adsDetections === []) {
            $classified = $this->classifier->classify($detection, [], $identity);
            $detection['traffic_status'] = $classified['traffic_status'];
            $detection['paid_risk_score'] = $classified['paid_risk_score'];
            if ($classified['action'] !== 'allow' && ($detection['action_taken'] ?? 'allow') === 'allow') {
                $detection['action_taken'] = $classified['action'] === 'challenge' ? 'flag' : $classified['action'];
            }

            return $detection;
        }

        $reasons = array_values(array_unique(array_merge(
            array_map('strval', $detection['reasons'] ?? []),
            array_map(fn ($row) => (string) $row['rule_code'], $adsDetections),
        )));

        $classified = $this->classifier->classify($detection, $adsDetections, $identity);
        $action = $classified['action'];
        // Map challenge → flag for current VisitProtection action enum.
        if ($action === 'challenge') {
            $action = 'flag';
        }

        $existing = (string) ($detection['action_taken'] ?? 'allow');
        $rank = ['allow' => 0, 'flag' => 1, 'block' => 2];
        if (($rank[$action] ?? 0) >= ($rank[$existing] ?? 0)) {
            $detection['action_taken'] = $action;
        }

        $detection['threat_score'] = max((int) ($detection['threat_score'] ?? 0), $classified['paid_risk_score']);
        $detection['paid_risk_score'] = $classified['paid_risk_score'];
        $detection['traffic_status'] = $classified['traffic_status'];
        $detection['block_scope'] = $classified['block_scope'] ?? ($detection['block_scope'] ?? null);
        if ($classified['traffic_status'] === 'invalid') {
            $detection['threat_group'] = $detection['threat_group'] ?: 'automation';
        }

        $detection['reasons'] = $reasons;
        $detection['ads_detections'] = $adsDetections;

        if (isset($detection['clickronix']) && is_array($detection['clickronix'])) {
            $detection['clickronix']['ads_detections'] = $adsDetections;
            $detection['clickronix']['paid_identity'] = $identity->toArray();
            $detection['clickronix']['traffic_status'] = $classified['traffic_status'];
            $detection['clickronix']['paid_risk_score'] = $classified['paid_risk_score'];
        }

        return $detection;
    }
}
