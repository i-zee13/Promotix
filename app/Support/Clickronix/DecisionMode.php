<?php

namespace App\Support\Clickronix;

/**
 * Clickronix v2 §5 / §15 — how a rule may enforce.
 */
final class DecisionMode
{
    public const STANDALONE = 'standalone';

    public const CORRELATED = 'correlated';

    public const SUPPORTING = 'supporting';

    public const TRUST = 'trust';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::STANDALONE, self::CORRELATED, self::SUPPORTING, self::TRUST];
    }
}
