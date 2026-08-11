<?php

namespace App\Support\PaidAdvertising;

/**
 * Resolved paid-click identity for Manual v3 §2.
 */
final class ResolvedPaidIdentity
{
    public function __construct(
        public readonly string $publicId,
        public readonly ?string $visitorId,
        public readonly ?string $browserId,
        public readonly ?string $deviceId,
        public readonly ?string $fingerprintId,
        public readonly float $confidence,
        public readonly string $confidenceBand,
        public readonly bool $knownFraud = false,
        public readonly ?int $rowId = null,
        public readonly float $fpSimilarity = 1.0,
        public readonly bool $rematchedViaFingerprint = false,
    ) {
    }

    public function isVeryHigh(): bool
    {
        return $this->confidence >= 0.95;
    }

    public function isHigh(): bool
    {
        return $this->confidence >= 0.85;
    }

    public function isMediumOrBetter(): bool
    {
        return $this->confidence >= 0.70;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'paid_identity_id' => $this->publicId,
            'visitor_id' => $this->visitorId,
            'browser_id' => $this->browserId,
            'device_id' => $this->deviceId,
            'fingerprint_id' => $this->fingerprintId,
            'identity_confidence' => $this->confidence,
            'confidence_band' => $this->confidenceBand,
            'known_fraud' => $this->knownFraud,
            'fp_similarity' => $this->fpSimilarity,
            'rematched_via_fingerprint' => $this->rematchedViaFingerprint,
        ];
    }
}
