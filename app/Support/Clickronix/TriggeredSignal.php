<?php

namespace App\Support\Clickronix;

/**
 * One evaluated rule observation entering the scoring engine.
 */
final class TriggeredSignal
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<string>  $rawFieldsPresent
     * @param  list<string>  $rawFieldsMissing
     */
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $state,
        public readonly float $confidence,
        public readonly int $recurrenceCount = 1,
        public readonly array $evidence = [],
        public readonly array $rawFieldsPresent = [],
        public readonly array $rawFieldsMissing = [],
        public readonly ?string $legacyReason = null,
        public readonly ?string $customerPreferredAction = null,
    ) {
    }

    public static function triggered(
        string $ruleCode,
        float $confidence = 1.0,
        int $recurrenceCount = 1,
        array $evidence = [],
        ?string $legacyReason = null,
        ?string $customerPreferredAction = null,
        array $rawFieldsPresent = [],
    ): self {
        return new self(
            ruleCode: $ruleCode,
            state: SignalState::TRIGGERED,
            confidence: max(0.0, min(1.0, $confidence)),
            recurrenceCount: max(1, $recurrenceCount),
            evidence: $evidence,
            rawFieldsPresent: $rawFieldsPresent,
            rawFieldsMissing: [],
            legacyReason: $legacyReason,
            customerPreferredAction: $customerPreferredAction,
        );
    }

    public static function unknown(string $ruleCode, array $missingFields): self
    {
        return new self(
            ruleCode: $ruleCode,
            state: SignalState::UNKNOWN,
            confidence: 0.0,
            rawFieldsMissing: $missingFields,
        );
    }

    public static function trust(string $ruleCode, float $confidence = 1.0, array $evidence = []): self
    {
        return new self(
            ruleCode: $ruleCode,
            state: SignalState::TRIGGERED,
            confidence: max(0.0, min(1.0, $confidence)),
            evidence: $evidence,
        );
    }
}
