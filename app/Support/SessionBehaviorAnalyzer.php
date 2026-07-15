<?php

namespace App\Support;

class SessionBehaviorAnalyzer
{
    public const NO_INTERACTION = 'NO_INTERACTION';

    public const DEFAULT_MIN_DURATION_MS = 3000;

    /**
     * @param  list<mixed>  $events
     * @return list<string>
     */
    public static function signals(array $events, int $durationMs, int $minDurationMs = self::DEFAULT_MIN_DURATION_MS): array
    {
        $normalized = SessionRecordingNormalizer::normalize($events);
        $hasMovement = false;
        $hasScroll = false;
        $hasClick = false;
        $hasKey = false;

        foreach ($normalized as $event) {
            $type = (string) ($event['type'] ?? '');
            if (in_array($type, ['mousemove', 'move'], true)) {
                $hasMovement = true;
            } elseif ($type === 'scroll') {
                $hasScroll = true;
            } elseif ($type === 'click') {
                $hasClick = true;
            } elseif (in_array($type, ['keydown', 'keypress', 'input'], true)) {
                $hasKey = true;
            }
        }

        // Also scan raw events for keyboard types the normalizer may drop.
        foreach ($events as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $type = strtolower((string) ($raw['type'] ?? ''));
            if (in_array($type, ['keydown', 'keypress', 'keyup', 'input'], true)) {
                $hasKey = true;
            }
        }

        $signals = [];
        if ($durationMs >= $minDurationMs && ! $hasMovement && ! $hasScroll && ! $hasClick && ! $hasKey) {
            $signals[] = self::NO_INTERACTION;
        }

        return $signals;
    }
}
