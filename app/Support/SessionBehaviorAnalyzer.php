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
        return self::analyze($events, $durationMs, $minDurationMs)['signals'];
    }

    /**
     * @param  list<mixed>  $events
     * @return array{
     *   signals: list<string>,
     *   cta_clicks: int,
     *   tel_clicks: int,
     *   page_changes: int,
     *   scroll_count: int,
     *   click_count: int,
     *   first_scroll_ms: ?int,
     *   last_cta_href: ?string
     * }
     */
    public static function analyze(array $events, int $durationMs, int $minDurationMs = self::DEFAULT_MIN_DURATION_MS): array
    {
        $normalized = SessionRecordingNormalizer::normalize($events);
        $hasMovement = false;
        $hasScroll = false;
        $hasClick = false;
        $hasKey = false;
        $scrollCount = 0;
        $clickCount = 0;
        $firstScrollMs = null;

        foreach ($normalized as $event) {
            $type = (string) ($event['type'] ?? '');
            if (in_array($type, ['mousemove', 'move'], true)) {
                $hasMovement = true;
            } elseif ($type === 'scroll') {
                $hasScroll = true;
                $scrollCount++;
                if ($firstScrollMs === null) {
                    $firstScrollMs = (int) ($event['t'] ?? 0);
                }
            } elseif ($type === 'click') {
                $hasClick = true;
                $clickCount++;
            } elseif (in_array($type, ['keydown', 'keypress', 'input'], true)) {
                $hasKey = true;
            }
        }

        $ctaClicks = 0;
        $telClicks = 0;
        $pageUrls = [];
        $lastCtaHref = null;

        // Scan raw events for CTA / tel / page markers the normalizer may trim.
        foreach ($events as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $type = strtolower((string) ($raw['type'] ?? ''));
            if (in_array($type, ['keydown', 'keypress', 'keyup', 'input'], true)) {
                $hasKey = true;
            }
            if ($type === 'click') {
                $href = trim((string) ($raw['href'] ?? ''));
                $hrefLower = strtolower($href);
                $isTel = ! empty($raw['tel']) || ! empty($raw['is_tel']) || str_starts_with($hrefLower, 'tel:');
                $isCta = ! empty($raw['cta']) || ! empty($raw['is_cta']);
                if ($isTel) {
                    $telClicks++;
                    if ($href !== '') {
                        $lastCtaHref = mb_substr($href, 0, 500);
                    }
                }
                if ($isCta) {
                    $ctaClicks++;
                    if ($href !== '') {
                        $lastCtaHref = mb_substr($href, 0, 500);
                    }
                }
            }
            if ($type === 'page') {
                $url = trim((string) ($raw['url'] ?? ''));
                if ($url !== '') {
                    $pageUrls[$url] = true;
                }
            }
        }

        $pageChanges = max(0, count($pageUrls) - (count($pageUrls) > 0 ? 1 : 0));

        $signals = [];
        if ($durationMs >= $minDurationMs && ! $hasMovement && ! $hasScroll && ! $hasClick && ! $hasKey) {
            $signals[] = self::NO_INTERACTION;
        }

        return [
            'signals' => $signals,
            'cta_clicks' => $ctaClicks,
            'tel_clicks' => $telClicks,
            'page_changes' => $pageChanges,
            'scroll_count' => $scrollCount,
            'click_count' => $clickCount,
            'first_scroll_ms' => $firstScrollMs,
            'last_cta_href' => $lastCtaHref,
        ];
    }
}
