<?php

namespace App\Support\Clickronix;

final class RuleDefinition
{
    /**
     * @param  list<string>  $requiredFields
     */
    public function __construct(
        public readonly string $code,
        public readonly string $category,
        public readonly string $mode,
        public readonly int $basePoints,
        public readonly int $maxPoints,
        public readonly ?string $legacyGroup,
        public readonly string $defaultAction,
        public readonly array $requiredFields = [],
        public readonly ?string $dedupeGroup = null,
        public readonly string $entityScope = 'ip',
        public readonly string $version = 'clickronix-v2',
    ) {
    }

    public function canEnforceAlone(): bool
    {
        return $this->mode === DecisionMode::STANDALONE;
    }
}
