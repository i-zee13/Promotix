<?php

namespace App\Support;

/**
 * Builds a human-readable behaviour timeline from session recording events
 * (PDF §6 — event timeline first phase; full DOM replay optional).
 */
class SessionBehaviorTimeline
{
    /**
     * @param  list<mixed>  $events
     * @return list<array{t: int, label: string, detail: string, kind: string}>
     */
    public static function fromEvents(array $events): array
    {
        $rows = [];
        $scrollMarks = [25 => false, 50 => false, 75 => false, 90 => false, 100 => false];

        foreach ($events as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $type = strtolower((string) ($raw['type'] ?? ''));
            $t = (int) ($raw['t'] ?? 0);

            if ($type === 'meta') {
                $rows[] = [
                    't' => $t,
                    'label' => 'Session Start',
                    'detail' => trim((string) ($raw['url'] ?? $raw['page_url'] ?? 'viewport ready')),
                    'kind' => 'start',
                ];

                continue;
            }

            if ($type === 'page') {
                $url = trim((string) ($raw['url'] ?? ''));
                $rows[] = [
                    't' => $t,
                    'label' => 'Page View',
                    'detail' => $url !== '' ? $url : 'page change',
                    'kind' => 'page',
                ];

                continue;
            }

            if ($type === 'scroll') {
                $depth = isset($raw['depth']) ? (int) $raw['depth'] : null;
                if ($depth !== null && isset($scrollMarks[$depth]) && ! $scrollMarks[$depth]) {
                    $scrollMarks[$depth] = true;
                    $rows[] = [
                        't' => $t,
                        'label' => 'Scroll',
                        'detail' => $depth.'%'.(isset($raw['page_url']) ? ' on '.(string) $raw['page_url'] : ''),
                        'kind' => 'scroll',
                    ];
                } elseif ($depth === null && ($raw['y'] ?? null) !== null) {
                    // Keep sparse scroll samples out of the narrative timeline.
                }

                continue;
            }

            if ($type === 'click') {
                $classified = SessionClickClassifier::classifyClickEvent($raw);
                $text = trim((string) ($raw['text'] ?? $raw['element_text'] ?? ''));
                $href = trim((string) ($raw['href'] ?? ''));
                if ($classified['tel']) {
                    $rows[] = [
                        't' => $t,
                        'label' => 'Phone Click',
                        'detail' => ($text !== '' ? $text.' → ' : '').($href !== '' ? $href : 'tel:'),
                        'kind' => 'phone',
                    ];
                } elseif ($classified['cta']) {
                    $rows[] = [
                        't' => $t,
                        'label' => 'CTA Click',
                        'detail' => ($text !== '' ? '"'.$text.'" → ' : '').($href !== '' ? $href : 'CTA'),
                        'kind' => 'cta',
                    ];
                }

                continue;
            }

            if ($type === 'form_start') {
                $rows[] = [
                    't' => $t,
                    'label' => 'Form Start',
                    'detail' => self::formDetail($raw),
                    'kind' => 'form',
                ];

                continue;
            }

            if (in_array($type, ['form_submit', 'form_fill'], true)) {
                $success = array_key_exists('success', $raw) ? ((bool) $raw['success'] ? 'success' : 'failed') : '';
                $rows[] = [
                    't' => $t,
                    'label' => 'Form Submit',
                    'detail' => trim(self::formDetail($raw).($success !== '' ? ' · '.$success : '')),
                    'kind' => 'form',
                ];

                continue;
            }

            if ($type === 'add_to_cart') {
                $rows[] = [
                    't' => $t,
                    'label' => 'Add to Cart',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                ];

                continue;
            }

            if ($type === 'checkout') {
                $rows[] = [
                    't' => $t,
                    'label' => 'Checkout',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                ];

                continue;
            }

            if (in_array($type, ['purchase', 'sale'], true)) {
                $rows[] = [
                    't' => $t,
                    'label' => 'Purchase',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => $a['t'] <=> $b['t']);

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function formDetail(array $raw): string
    {
        $id = trim((string) ($raw['form_id'] ?? $raw['name'] ?? $raw['id'] ?? ''));
        $page = trim((string) ($raw['page_url'] ?? ''));

        return trim(($id !== '' ? $id : 'form').($page !== '' ? ' · '.$page : ''));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function commerceDetail(array $raw): string
    {
        $parts = [];
        foreach (['product_id', 'product_name', 'sku', 'order_id', 'value', 'revenue', 'currency'] as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '' && $raw[$key] !== null) {
                $parts[] = (string) $raw[$key];
            }
        }
        $page = trim((string) ($raw['page_url'] ?? ''));
        if ($page !== '') {
            $parts[] = $page;
        }

        return $parts !== [] ? implode(' · ', $parts) : 'event';
    }
}
