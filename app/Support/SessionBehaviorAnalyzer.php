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
     *   form_starts: int,
     *   form_submits: int,
     *   add_to_cart: int,
     *   checkouts: int,
     *   purchases: int,
     *   first_scroll_ms: ?int,
     *   last_cta_href: ?string,
     *   timeline: list<array{t: int, label: string, detail: string, kind: string}>
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
        $formStarts = 0;
        $formSubmits = 0;
        $addToCart = 0;
        $checkouts = 0;
        $purchases = 0;
        $pageUrls = [];
        $lastCtaHref = null;
        $hasTypedCtaOrTel = false;

        // Scan raw events for CTA / tel / page markers the normalizer may trim.
        foreach ($events as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $type = strtolower((string) ($raw['type'] ?? ''));
            if (in_array($type, ['keydown', 'keypress', 'keyup', 'input', 'form_start'], true)) {
                $hasKey = true;
            }
            if ($type === 'cta_click') {
                $hasTypedCtaOrTel = true;
                $hasClick = true;
                $ctaClicks++;
                $href = trim((string) ($raw['href'] ?? ''));
                if ($href !== '') {
                    $lastCtaHref = mb_substr($href, 0, 500);
                }
            } elseif (in_array($type, ['phone_click', 'tel_click'], true)) {
                $hasTypedCtaOrTel = true;
                $hasClick = true;
                $telClicks++;
                $href = trim((string) ($raw['href'] ?? ''));
                if ($href !== '') {
                    $lastCtaHref = mb_substr($href, 0, 500);
                }
            } elseif ($type === 'click' && ! $hasTypedCtaOrTel) {
                $classified = SessionClickClassifier::classifyClickEvent($raw);
                $href = trim((string) ($raw['href'] ?? ''));
                if ($classified['tel']) {
                    $telClicks++;
                    if ($href !== '') {
                        $lastCtaHref = mb_substr($href, 0, 500);
                    }
                }
                if ($classified['cta']) {
                    $ctaClicks++;
                    if ($href !== '') {
                        $lastCtaHref = mb_substr($href, 0, 500);
                    }
                }
            }
            if (in_array($type, ['page', 'page_view', 'page_change'], true)) {
                $url = trim((string) ($raw['url'] ?? $raw['page_url'] ?? ''));
                if ($url !== '') {
                    $pageUrls[$url] = true;
                }
            }
            if ($type === 'form_start') {
                $formStarts++;
            }
            if (in_array($type, ['form_submit', 'form_fill'], true)) {
                $formSubmits++;
            }
            if ($type === 'add_to_cart') {
                $addToCart++;
            }
            if ($type === 'checkout') {
                $checkouts++;
            }
            if (in_array($type, ['purchase', 'sale'], true)) {
                $purchases++;
            }
        }

        // Second pass: if typed events exist, recount clicks only from typed to avoid double counts with legacy click flags.
        if ($hasTypedCtaOrTel) {
            $ctaClicks = 0;
            $telClicks = 0;
            foreach ($events as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $type = strtolower((string) ($raw['type'] ?? ''));
                if ($type === 'cta_click') {
                    $ctaClicks++;
                } elseif (in_array($type, ['phone_click', 'tel_click'], true)) {
                    $telClicks++;
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
            'form_starts' => $formStarts,
            'form_submits' => $formSubmits,
            'add_to_cart' => $addToCart,
            'checkouts' => $checkouts,
            'purchases' => $purchases,
            'first_scroll_ms' => $firstScrollMs,
            'last_cta_href' => $lastCtaHref,
            'timeline' => SessionBehaviorTimeline::fromEvents($events),
        ];
    }
}
