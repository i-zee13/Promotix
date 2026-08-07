<?php

namespace App\Support;

class SessionBehaviorFingerprint
{
    /**
     * Build a coarse fingerprint for low-human / repeated-behavior matching (SR-04).
     * Includes scroll-timing buckets so repeated scroll scripts match across visits.
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
        $firstScrollMs = null;
        $scrollTs = [];

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
                $scrollTs[] = $t;
                if ($firstScrollMs === null) {
                    $firstScrollMs = $t;
                }
            } elseif (in_array($type, ['keydown', 'keypress', 'input'], true)) {
                $keys++;
            }
        }

        $pageCount = 0;
        foreach ($events as $raw) {
            if (is_array($raw) && strtolower((string) ($raw['type'] ?? '')) === 'page') {
                $pageCount++;
            }
        }

        $bucketDuration = (int) floor(max($durationMs, $span) / 1000);
        $bucketMoves = min(5, (int) floor($moves / 10));
        $bucketClicks = min(5, $clicks);
        $bucketScrolls = min(5, $scrolls);
        $bucketKeys = min(5, $keys);
        $idle = ($moves + $clicks + $scrolls + $keys) === 0 ? 1 : 0;
        $firstScrollBucket = $firstScrollMs === null
            ? 'x'
            : (string) min(20, (int) floor($firstScrollMs / 500));
        $paceBucket = 'x';
        if (count($scrollTs) >= 2) {
            $gaps = [];
            for ($i = 1; $i < count($scrollTs); $i++) {
                $gaps[] = max(0, $scrollTs[$i] - $scrollTs[$i - 1]);
            }
            $avgGap = (int) floor(array_sum($gaps) / max(1, count($gaps)));
            $paceBucket = (string) min(20, (int) floor($avgGap / 250));
        }
        $pageBucket = min(5, max(0, $pageCount));

        return implode(':', [
            'v2',
            'd' . $bucketDuration,
            'm' . $bucketMoves,
            'c' . $bucketClicks,
            's' . $bucketScrolls,
            'k' . $bucketKeys,
            'i' . $idle,
            'sf' . $firstScrollBucket,
            'sp' . $paceBucket,
            'pg' . $pageBucket,
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
