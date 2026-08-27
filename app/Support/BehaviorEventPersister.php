<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persists typed behavior events from session recordings into visit_behavior_events.
 */
class BehaviorEventPersister
{
    /**
     * @param  list<mixed>  $events
     * @return list<array<string, mixed>>
     */
    public static function extractRows(
        array $events,
        int $domainId,
        ?int $recordingId,
        ?int $visitId,
        ?string $sessionId,
        ?string $visitorId,
        ?Carbon $recordingStartedAt = null,
    ): array {
        $started = $recordingStartedAt?->copy() ?? Carbon::now('UTC');
        $rows = [];

        foreach ($events as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $type = strtolower(trim((string) ($raw['type'] ?? '')));
            $mapped = self::mapEventType($type, $raw);
            if ($mapped === null) {
                continue;
            }

            $relativeMs = max(0, (int) ($raw['t'] ?? 0));
            $occurredAt = isset($raw['ts']) && is_numeric($raw['ts'])
                ? Carbon::createFromTimestampMs((int) $raw['ts'], 'UTC')
                : $started->copy()->addMilliseconds($relativeMs);

            $pageUrl = self::str($raw['page_url'] ?? $raw['url'] ?? null, 500);
            $href = self::str($raw['href'] ?? null, 500);
            $telNumber = self::str($raw['tel_number'] ?? null, 64);
            if ($telNumber === null && $href !== null && SessionClickClassifier::isTelHref($href)) {
                $telNumber = self::str(preg_replace('/^(tel|callto|sms):/i', '', $href), 64);
            }

            $rows[] = [
                'domain_id' => $domainId,
                'recording_id' => $recordingId,
                'visit_id' => $visitId,
                'session_id' => self::str($sessionId ?? ($raw['session_id'] ?? null), 128),
                'visitor_id' => self::str($visitorId ?? ($raw['visitor_id'] ?? null), 128),
                'event_type' => $mapped,
                'page_url' => $pageUrl,
                'page_path' => self::str($raw['path'] ?? ($pageUrl ? TrafficSourceClassifier::pathFromUrl($pageUrl) : null), 500),
                'title' => self::str($raw['title'] ?? $raw['headline'] ?? null, 255),
                'referrer' => self::str($raw['referrer'] ?? null, 500),
                'element_text' => self::str($raw['element_text'] ?? $raw['text'] ?? null, 255),
                'href' => $href,
                'element_id' => self::str($raw['element_id'] ?? $raw['id'] ?? null, 120),
                'element_class' => self::str($raw['element_class'] ?? $raw['class'] ?? null, 255),
                'link_type' => self::str($raw['link_type'] ?? self::inferLinkType($mapped, $raw), 40),
                'tel_number' => $telNumber,
                'form_id' => self::str($raw['form_id'] ?? null, 120),
                'form_name' => self::str($raw['form_name'] ?? $raw['name'] ?? null, 120),
                'success' => array_key_exists('success', $raw) ? (bool) $raw['success'] : null,
                'scroll_depth' => isset($raw['depth']) ? min(100, max(0, (int) $raw['depth'])) : null,
                'product_id' => self::str($raw['product_id'] ?? $raw['sku'] ?? null, 120),
                'product_name' => self::str($raw['product_name'] ?? null, 255),
                'order_id' => self::str($raw['order_id'] ?? null, 120),
                'revenue' => self::decimal($raw['revenue'] ?? $raw['value'] ?? null),
                'currency' => self::str($raw['currency'] ?? null, 12),
                'value' => self::str($raw['value'] ?? null, 64),
                'relative_ms' => $relativeMs,
                'occurred_at' => $occurredAt,
                'payload' => json_encode($raw),
                'created_at' => Carbon::now('UTC'),
                'updated_at' => Carbon::now('UTC'),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function insert(array $rows): int
    {
        if ($rows === [] || ! Schema::hasTable('visit_behavior_events')) {
            return 0;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('visit_behavior_events')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function mapEventType(string $type, array $raw): ?string
    {
        return match ($type) {
            'cta_click' => 'cta_click',
            'phone_click', 'tel_click' => 'phone_click',
            'form_start' => 'form_start',
            'form_submit', 'form_fill' => 'form_submit',
            'page_view', 'page' => 'page_view',
            'page_change' => 'page_change',
            'session_exit', 'exit' => 'session_exit',
            'scroll' => isset($raw['depth']) ? 'scroll' : null,
            'add_to_cart' => 'add_to_cart',
            'checkout' => 'checkout',
            'purchase', 'sale' => 'purchase',
            'click' => self::mapLegacyClick($raw),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function mapLegacyClick(array $raw): ?string
    {
        $classified = SessionClickClassifier::classifyClickEvent(array_merge($raw, ['type' => 'click']));
        if ($classified['tel']) {
            return 'phone_click';
        }
        if ($classified['cta']) {
            return 'cta_click';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function inferLinkType(string $mapped, array $raw): ?string
    {
        if ($mapped === 'phone_click') {
            return 'tel';
        }
        if ($mapped === 'cta_click') {
            $tag = strtoupper((string) ($raw['tag'] ?? ''));

            return match ($tag) {
                'A' => 'anchor',
                'BUTTON' => 'button',
                'INPUT' => 'input',
                default => 'cta',
            };
        }
        if (in_array($mapped, ['form_start', 'form_submit'], true)) {
            return 'form';
        }

        return $mapped;
    }

    private static function str(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private static function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
