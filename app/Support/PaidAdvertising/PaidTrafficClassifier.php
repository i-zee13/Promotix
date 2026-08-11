<?php

namespace App\Support\PaidAdvertising;

/**
 * Flowchart final classification for Google paid traffic (Manual VALID / SUSPICIOUS / INVALID).
 */
class PaidTrafficClassifier
{
    /**
     * @param  list<array<string, mixed>>  $adsDetections
     * @return array{
     *   traffic_status: 'valid'|'suspicious'|'invalid',
     *   action: 'allow'|'flag'|'challenge'|'block',
     *   paid_risk_score: int,
     *   block_scope: ?string
     * }
     */
    public function classify(array $detection, array $adsDetections, ResolvedPaidIdentity $identity): array
    {
        $score = (int) ($detection['threat_score'] ?? 0);
        foreach ($adsDetections as $row) {
            $score = max($score, (int) ($row['base_points'] ?? 0));
        }

        $decisive = collect($adsDetections)->first(fn ($r) => (bool) ($r['can_block_alone'] ?? false));
        $recommended = app(AdsRepeatEvaluator::class)->recommendedAction($adsDetections);

        // Decisive path — do not wait for aggregate 85/100.
        if ($decisive && in_array($recommended, ['block_identity', 'block'], true)) {
            return [
                'traffic_status' => 'invalid',
                'action' => 'block',
                'paid_risk_score' => max($score, (int) ($decisive['base_points'] ?? 90)),
                'block_scope' => 'device',
            ];
        }

        if ($score >= 85 || ($detection['action_taken'] ?? '') === 'block') {
            return [
                'traffic_status' => 'invalid',
                'action' => 'block',
                'paid_risk_score' => max($score, 85),
                'block_scope' => $identity->isHigh() ? 'device' : 'session',
            ];
        }

        if ($score >= 40 || $recommended === 'challenge' || ($detection['action_taken'] ?? '') === 'flag') {
            return [
                'traffic_status' => 'suspicious',
                'action' => $score >= 60 ? 'challenge' : 'flag',
                'paid_risk_score' => max($score, 40),
                'block_scope' => null,
            ];
        }

        return [
            'traffic_status' => 'valid',
            'action' => 'allow',
            'paid_risk_score' => $score,
            'block_scope' => null,
        ];
    }
}
