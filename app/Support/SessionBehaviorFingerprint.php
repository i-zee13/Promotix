<?php

namespace App\Support;

class SessionBehaviorFingerprint
{
    /**
     * Build a coarse fingerprint for low-human / repeated-behavior matching (SR-04).
     *
     * @param  list<mixed>  $events
     */
    public static function fromEvents(array $events, int $durationMs): string
    {
        $normalized = SessionRecordingNormalizer::normalize($events);
        $moves = 0;
        $clicks = 0;
        $scrolls = 0;
        $keys = 0;
        $span = 0;

        foreach ($normalized as $event) {
            $t = (int) ($event['t'] ?? 0);
            $span = max($span, $t);
            $type = (string) ($event['type'] ?? '');
            if (in_array($type, ['mousemove', 'move'], true)) {
                $moves++;
            } elseif ($type === 'click') {
                $clicks++;
            } elseif ($type === 'scroll') {
                $scrolls++;
            } elseif (in_array($type, ['keydown', 'keypress', 'input'], true)) {
                $keys++;
            }
        }

        $bucketDuration = (int) floor(max($durationMs, $span) / 1000);
        $bucketMoves = min(5, (int) floor($moves / 10));
        $bucketClicks = min(5, $clicks);
        $bucketScrolls = min(5, $scrolls);
        $bucketKeys = min(5, $keys);
        $idle = ($moves + $clicks + $scrolls + $keys) === 0 ? 1 : 0;

        return implode(':', [
            'v1',
            'd' . $bucketDuration,
            'm' . $bucketMoves,
            'c' . $bucketClicks,
            's' . $bucketScrolls,
            'k' . $bucketKeys,
            'i' . $idle,
        ]);
    }

    public static function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '' || $a !== $b) {
            return $a !== '' && $a === $b ? 1.0 : 0.0;
        }

        return 1.0;
    }
}
