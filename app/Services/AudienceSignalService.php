<?php

namespace App\Services;

/**
 * Consent-gated invalid-traffic audience signal for GA4 / GTM / gtag.
 * Membership key is Google Client ID + event — not raw IP/fingerprint.
 */
class AudienceSignalService
{
    public const DEFAULT_EVENT = 'clickronix_invalid_traffic';

    /**
     * @param  array<string, mixed>  $detection
     * @return array{fire: bool, event: string, traffic_status: string}
     */
    public function decisionFromDetection(array $detection): array
    {
        $status = strtolower((string) ($detection['traffic_status'] ?? ''));
        $action = strtolower((string) ($detection['action_taken'] ?? 'allow'));
        $fire = $status === 'invalid' || $action === 'block';

        return [
            'fire' => $fire,
            'event' => self::DEFAULT_EVENT,
            'traffic_status' => $fire ? 'invalid' : ($status !== '' ? $status : 'valid'),
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
            'audience_traffic_status' => $decision['traffic_status'],
        ];
    }
}
