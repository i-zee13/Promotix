<?php

namespace App\Support\Clickronix;

/**
 * Clickronix §6 enforcement bands → legacy action_taken (allow|flag|block).
 * Challenge band maps to flag so existing client captcha path keeps working.
 */
final class EnforcementMatrix
{
    public const LEVEL_TRUSTED = 'trusted';

    public const LEVEL_LOW = 'low';

    public const LEVEL_SUSPICIOUS = 'suspicious';

    public const LEVEL_HIGH = 'high';

    public const LEVEL_VERY_HIGH = 'very_high';

    public const LEVEL_CRITICAL = 'critical';

    /**
     * @return array{level: string, action: string, duration_hint: string, block_scope: string}
     */
    public static function forScore(int $score, bool $standaloneEnforce = false, string $standaloneAction = 'block'): array
    {
        if ($standaloneEnforce) {
            $action = in_array($standaloneAction, ['allow', 'flag', 'block'], true)
                ? $standaloneAction
                : 'block';

            return [
                'level' => $action === 'allow' ? self::LEVEL_TRUSTED : self::LEVEL_CRITICAL,
                'action' => $action,
                'duration_hint' => $action === 'block' ? 'policy_or_review' : 'none',
                'block_scope' => $action === 'block' ? 'owning_entity' : 'none',
            ];
        }

        return match (true) {
            $score <= 19 => [
                'level' => self::LEVEL_TRUSTED,
                'action' => 'allow',
                'duration_hint' => 'none',
                'block_scope' => 'none',
            ],
            $score <= 39 => [
                'level' => self::LEVEL_LOW,
                'action' => 'allow',
                'duration_hint' => 'none',
                'block_scope' => 'none',
            ],
            $score <= 59 => [
                'level' => self::LEVEL_SUSPICIOUS,
                'action' => 'flag',
                'duration_hint' => 'rate_limit',
                'block_scope' => 'session',
            ],
            $score <= 74 => [
                'level' => self::LEVEL_HIGH,
                'action' => 'flag',
                'duration_hint' => 'challenge_fail_1h',
                'block_scope' => 'session',
            ],
            $score <= 84 => [
                'level' => self::LEVEL_VERY_HIGH,
                'action' => 'block',
                'duration_hint' => 'ip_up_to_24h',
                'block_scope' => 'session_device_ip',
            ],
            default => [
                'level' => self::LEVEL_CRITICAL,
                'action' => 'block',
                'duration_hint' => 'ip_up_to_7d_or_30d',
                'block_scope' => 'owning_entity',
            ],
        };
    }
}
