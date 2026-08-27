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
     * @return list<array<string, mixed>>
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
            $base = self::enrich($raw, $t);

            if ($type === 'meta') {
                $rows[] = array_merge($base, [
                    'label' => 'Session Start',
                    'detail' => trim((string) ($raw['url'] ?? $raw['page_url'] ?? 'viewport ready')),
                    'kind' => 'start',
                    'type' => 'meta',
                ]);

                continue;
            }

            if (in_array($type, ['page', 'page_view'], true)) {
                $url = trim((string) ($raw['url'] ?? $raw['page_url'] ?? ''));
                $title = trim((string) ($raw['title'] ?? $raw['headline'] ?? ''));
                $rows[] = array_merge($base, [
                    'label' => 'Page View',
                    'detail' => trim(($title !== '' ? $title.' · ' : '').($url !== '' ? $url : 'page')),
                    'kind' => 'page',
                    'type' => 'page_view',
                    'page_url' => $url !== '' ? $url : ($base['page_url'] ?? null),
                    'title' => $title !== '' ? $title : null,
                ]);

                continue;
            }

            if ($type === 'page_change') {
                $url = trim((string) ($raw['url'] ?? $raw['page_url'] ?? ''));
                $title = trim((string) ($raw['title'] ?? $raw['headline'] ?? ''));
                $path = trim((string) ($raw['path'] ?? ($url !== '' ? TrafficSourceClassifier::pathFromUrl($url) : '')));
                $rows[] = array_merge($base, [
                    'label' => 'Page Change',
                    'detail' => $path !== '' ? $path : ($url !== '' ? $url : ($title !== '' ? $title : 'page change')),
                    'kind' => 'page',
                    'type' => 'page_change',
                    'page_url' => $url !== '' ? $url : ($base['page_url'] ?? null),
                    'title' => $title !== '' ? $title : null,
                ]);

                continue;
            }

            if (in_array($type, ['session_exit', 'exit'], true)) {
                $url = trim((string) ($raw['url'] ?? $raw['page_url'] ?? ''));
                $path = trim((string) ($raw['path'] ?? ($url !== '' ? TrafficSourceClassifier::pathFromUrl($url) : '')));
                $rows[] = array_merge($base, [
                    'label' => 'Session Exit',
                    'detail' => $path !== '' ? $path : ($url !== '' ? $url : 'exit'),
                    'kind' => 'exit',
                    'type' => 'session_exit',
                    'page_url' => $url !== '' ? $url : ($base['page_url'] ?? null),
                ]);

                continue;
            }

            if ($type === 'scroll') {
                $depth = isset($raw['depth']) ? (int) $raw['depth'] : null;
                if ($depth !== null && isset($scrollMarks[$depth]) && ! $scrollMarks[$depth]) {
                    $scrollMarks[$depth] = true;
                    $page = (string) ($raw['page_url'] ?? '');
                    $rows[] = array_merge($base, [
                        'label' => 'Scroll',
                        'detail' => $depth.'%'.($page !== '' ? ' on '.$page : ''),
                        'kind' => 'scroll',
                        'type' => 'scroll',
                        'scroll_depth' => $depth,
                    ]);
                }

                continue;
            }

            if ($type === 'cta_click' || ($type === 'click' && SessionClickClassifier::classifyClickEvent($raw)['cta'])) {
                $text = trim((string) ($raw['element_text'] ?? $raw['text'] ?? ''));
                $href = trim((string) ($raw['href'] ?? ''));
                $rows[] = array_merge($base, [
                    'label' => 'CTA Click',
                    'detail' => ($text !== '' ? '"'.$text.'" → ' : '').($href !== '' ? $href : 'CTA'),
                    'kind' => 'cta',
                    'type' => 'cta_click',
                    'link_type' => (string) ($raw['link_type'] ?? self::linkTypeFromTag((string) ($raw['tag'] ?? 'cta'))),
                    'element_text' => $text !== '' ? $text : null,
                    'href' => $href !== '' ? $href : null,
                ]);

                continue;
            }

            if (
                in_array($type, ['phone_click', 'tel_click'], true)
                || ($type === 'click' && SessionClickClassifier::classifyClickEvent($raw)['tel'])
            ) {
                $text = trim((string) ($raw['element_text'] ?? $raw['text'] ?? ''));
                $href = trim((string) ($raw['href'] ?? ''));
                $tel = trim((string) ($raw['tel_number'] ?? preg_replace('/^(tel|callto|sms):/i', '', $href)));
                $rows[] = array_merge($base, [
                    'label' => 'Phone Click',
                    'detail' => ($text !== '' ? $text.' → ' : '').($tel !== '' ? $tel : ($href !== '' ? $href : 'tel:')),
                    'kind' => 'phone',
                    'type' => 'phone_click',
                    'link_type' => 'tel',
                    'tel_number' => $tel !== '' ? $tel : null,
                    'element_text' => $text !== '' ? $text : null,
                    'href' => $href !== '' ? $href : null,
                ]);

                continue;
            }

            if ($type === 'form_start') {
                $rows[] = array_merge($base, [
                    'label' => 'Form Start',
                    'detail' => self::formDetail($raw),
                    'kind' => 'form',
                    'type' => 'form_start',
                    'link_type' => 'form',
                    'form_id' => $raw['form_id'] ?? null,
                    'form_name' => $raw['form_name'] ?? null,
                ]);

                continue;
            }

            if (in_array($type, ['form_submit', 'form_fill'], true)) {
                $success = array_key_exists('success', $raw) ? ((bool) $raw['success'] ? 'success' : 'failed') : '';
                $rows[] = array_merge($base, [
                    'label' => 'Form Submit',
                    'detail' => trim(self::formDetail($raw).($success !== '' ? ' · '.$success : '')),
                    'kind' => 'form',
                    'type' => 'form_submit',
                    'link_type' => 'form',
                    'success' => array_key_exists('success', $raw) ? (bool) $raw['success'] : null,
                    'form_id' => $raw['form_id'] ?? null,
                    'form_name' => $raw['form_name'] ?? null,
                ]);

                continue;
            }

            if ($type === 'add_to_cart') {
                $rows[] = array_merge($base, [
                    'label' => 'Add to Cart',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                    'type' => 'add_to_cart',
                ]);

                continue;
            }

            if ($type === 'checkout') {
                $rows[] = array_merge($base, [
                    'label' => 'Checkout',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                    'type' => 'checkout',
                ]);

                continue;
            }

            if (in_array($type, ['purchase', 'sale'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Purchase',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                    'type' => 'purchase',
                ]);
            }
        }

        usort($rows, fn (array $a, array $b) => $a['t'] <=> $b['t']);

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function enrich(array $raw, int $t): array
    {
        $page = trim((string) ($raw['page_url'] ?? $raw['url'] ?? ''));
        $at = null;
        if (isset($raw['ts']) && is_numeric($raw['ts'])) {
            $at = date('c', (int) floor(((int) $raw['ts']) / 1000));
        }

        return [
            't' => $t,
            'at' => $at,
            'page_url' => $page !== '' ? $page : null,
            'path' => isset($raw['path']) ? (string) $raw['path'] : ($page !== '' ? TrafficSourceClassifier::pathFromUrl($page) : null),
            'title' => isset($raw['title']) ? (string) $raw['title'] : null,
            'session_id' => isset($raw['session_id']) ? (string) $raw['session_id'] : null,
            'visitor_id' => isset($raw['visitor_id']) ? (string) $raw['visitor_id'] : null,
        ];
    }

    private static function linkTypeFromTag(string $tag): string
    {
        return match (strtoupper($tag)) {
            'A' => 'anchor',
            'BUTTON' => 'button',
            'INPUT' => 'input',
            default => 'cta',
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function formDetail(array $raw): string
    {
        $id = trim((string) ($raw['form_id'] ?? $raw['name'] ?? $raw['id'] ?? ''));
        $name = trim((string) ($raw['form_name'] ?? ''));
        $page = trim((string) ($raw['page_url'] ?? ''));
        $label = $name !== '' ? $name : ($id !== '' ? $id : 'form');

        return trim($label.($page !== '' ? ' · '.$page : ''));
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
