<?php

namespace App\Services;

/**
 * Consent-gated invalid-traffic audience signal for GA4 / GTM / Google Ads website lists.
 *
 * Spec: same-browser Google tag event — never upload Clickronix Device ID / IP / fingerprint
 * as a Google audience member. Membership is Google's browser identity + this event.
 *
 * Canonical contract:
 *   event: cr_invalid_traffic
 *   cr_traffic_verdict: invalid
 * Fire only on final invalid (not valid / suspicious / pending). Once per decision_id.
 */
class AudienceSignalService
{
    public const DEFAULT_EVENT = 'cr_invalid_traffic';

    public const VERDICT_PARAM = 'cr_traffic_verdict';

    public const VERDICT_INVALID = 'invalid';

    public const EVENT_VERSION = '1.0';

    /**
     * @param  array<string, mixed>  $detection
     * @return array{
     *   fire: bool,
     *   event: string,
     *   traffic_verdict: string,
     *   decision_id: ?string,
     *   event_id: ?string,
     *   detection_type: ?string,
     *   risk_score: ?int,
     *   protection_action: ?string
     * }
     */
    public function decisionFromDetection(array $detection): array
    {
        $status = strtolower(trim((string) ($detection['traffic_status'] ?? '')));
        $action = strtolower(trim((string) ($detection['action_taken'] ?? 'allow')));

        // Derive a final verdict when paid classifier did not set traffic_status.
        if ($status === '') {
            $status = match (true) {
                $action === 'block' => self::VERDICT_INVALID,
                in_array($action, ['flag', 'challenge'], true) => 'suspicious',
                default => 'valid',
            };
        }

        // Never contaminate the audience with suspicious / pending / valid.
        $fire = $status === self::VERDICT_INVALID;

        $decisionId = $fire ? $this->decisionId($detection, $status) : null;
        $eventId = $fire ? $this->eventId($decisionId) : null;

        $threat = strtolower(trim((string) ($detection['threat_group'] ?? '')));
        $detectionType = $threat !== '' ? $threat : null;
        if ($detectionType === null && ! empty($detection['reasons'][0])) {
            $detectionType = strtolower((string) $detection['reasons'][0]);
        }

        $score = isset($detection['threat_score']) ? (int) $detection['threat_score'] : null;
        if ($score === null && isset($detection['paid_risk_score'])) {
            $score = (int) $detection['paid_risk_score'];
        }

        return [
            'fire' => $fire,
            'event' => self::DEFAULT_EVENT,
            'traffic_verdict' => $fire ? self::VERDICT_INVALID : $status,
            'decision_id' => $decisionId,
            'event_id' => $eventId,
            'detection_type' => $detectionType,
            'risk_score' => $score,
            'protection_action' => $action !== '' ? $action : null,
        ];
    }

    /**
     * Fields merged into the browser tracking JSON response.
     *
     * @param  array<string, mixed>  $detection
     * @return array<string, mixed>
     */
    public function clientFlags(array $detection): array
    {
        $decision = $this->decisionFromDetection($detection);
        if (! $decision['fire']) {
            return [];
        }

        return [
            'fire_audience_event' => true,
            'audience_event' => $decision['event'],
            'audience_traffic_verdict' => $decision['traffic_verdict'],
            // Back-compat for older GTM containers still reading traffic_status.
            'audience_traffic_status' => $decision['traffic_verdict'],
            'audience_decision_id' => $decision['decision_id'],
            'audience_event_id' => $decision['event_id'],
            'audience_event_version' => self::EVENT_VERSION,
            'audience_detection_type' => $decision['detection_type'],
            'audience_risk_score' => $decision['risk_score'],
            'audience_protection_action' => $decision['protection_action'],
            'audience_occurred_at' => now('UTC')->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $detection
     */
    private function decisionId(array $detection, string $status): string
    {
        if (! empty($detection['decision_id']) && is_string($detection['decision_id'])) {
            return (string) $detection['decision_id'];
        }

        $basis = implode('|', [
            (string) ($detection['visit_id'] ?? ''),
            (string) ($detection['ip'] ?? ''),
            (string) ($detection['session_id'] ?? ''),
            $status,
            (string) ($detection['threat_group'] ?? ''),
            (string) ($detection['action_taken'] ?? ''),
            (string) ($detection['threat_score'] ?? ''),
        ]);

        return 'DEC-'.strtoupper(substr(hash('sha256', $basis !== '|||||' ? $basis : uniqid('dec', true)), 0, 10));
    }

    private function eventId(?string $decisionId): string
    {
        $seed = ($decisionId ?: uniqid('evt', true)).'|'.microtime(true);

        return 'EVT-'.strtoupper(substr(hash('sha256', $seed), 0, 8));
    }
}
