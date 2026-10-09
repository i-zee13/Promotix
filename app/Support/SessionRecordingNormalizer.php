<?php

namespace App\Support;

class SessionRecordingNormalizer
{
    /**
     * Normalize stored tag events for the session replay player.
     *
     * @param  list<mixed>  $events
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $events): array
    {
        $normalized = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $mapped = self::mapEvent($event);
            if ($mapped !== null) {
                $normalized[] = $mapped;
            }
        }

        usort($normalized, fn ($a, $b) => ((int) ($a['t'] ?? 0)) <=> ((int) ($b['t'] ?? 0)));

        return $normalized;
    }

    /** @param  array<string, mixed>  $event */
    private static function mapEvent(array $event): ?array
    {
        $type = (string) ($event['type'] ?? '');
        $t = (int) ($event['t'] ?? 0);

        if ($type === 'meta') {
            return [
                't' => $t,
                'type' => 'meta',
                'vw' => (int) ($event['vw'] ?? $event['data']['vw'] ?? 0),
                'vh' => (int) ($event['vh'] ?? $event['data']['vh'] ?? 0),
            ];
        }

        if (in_array($type, ['mousemove', 'move'], true)) {
            $x = $event['x'] ?? ($event['data']['x'] ?? null);
            $y = $event['y'] ?? ($event['data']['y'] ?? null);
            if (! is_numeric($x) || ! is_numeric($y)) {
                return null;
            }

            return [
                't' => $t,
                'type' => 'mousemove',
                'x' => (float) $x,
                'y' => (float) $y,
            ];
        }

        if ($type === 'click') {
            $x = $event['x'] ?? ($event['data']['x'] ?? null);
            $y = $event['y'] ?? ($event['data']['y'] ?? null);
            if (! is_numeric($x) || ! is_numeric($y)) {
                return null;
            }

            return [
                't' => $t,
                'type' => 'click',
                'x' => (float) $x,
                'y' => (float) $y,
                'tag' => (string) ($event['tag'] ?? $event['data']['tag'] ?? ''),
                'href' => (string) ($event['href'] ?? $event['data']['href'] ?? ''),
                'cta' => (bool) ($event['cta'] ?? $event['is_cta'] ?? $event['data']['cta'] ?? false),
                'tel' => (bool) ($event['tel'] ?? $event['is_tel'] ?? $event['data']['tel'] ?? false),
            ];
        }

        if ($type === 'scroll') {
            return [
                't' => $t,
                'type' => 'scroll',
                'x' => (float) ($event['x'] ?? $event['data']['x'] ?? 0),
                'y' => (float) ($event['y'] ?? $event['data']['y'] ?? 0),
            ];
        }

        if ($type === 'page') {
            return [
                't' => $t,
                'type' => 'page',
                'url' => (string) ($event['url'] ?? $event['data']['url'] ?? ''),
            ];
        }

        $behaviorTypes = [
            'cta_click', 'phone_click', 'tel_click', 'call_click',
            'form_start', 'form_submit', 'form_fill',
            'add_to_cart', 'checkout', 'purchase',
            'email_click', 'external_link', 'file_download',
        ];
        if (in_array($type, $behaviorTypes, true)) {
            $normalizedType = match ($type) {
                'tel_click', 'call_click' => 'phone_click',
                'form_fill' => 'form_submit',
                default => $type,
            };
            $label = trim((string) ($event['element_text'] ?? $event['text'] ?? $event['label'] ?? ''));
            if ($label === '') {
                $label = match ($normalizedType) {
                    'cta_click' => 'CTA click',
                    'phone_click' => 'Call click',
                    'form_start' => 'Form start',
                    'form_submit' => 'Form submit',
                    'add_to_cart' => 'Add to cart',
                    'checkout' => 'Checkout',
                    'purchase' => 'Purchase',
                    'email_click' => 'Email click',
                    'external_link' => 'External link',
                    'file_download' => 'Download',
                    default => $normalizedType,
                };
            }
            $x = $event['x'] ?? ($event['data']['x'] ?? null);
            $y = $event['y'] ?? ($event['data']['y'] ?? null);

            $linkType = (string) ($event['link_type'] ?? $event['data']['link_type'] ?? '');
            if ($linkType === '' && $type === 'call_click') {
                $linkType = 'call';
            }
            if ($linkType === '' && in_array($type, ['tel_click', 'phone_click'], true)) {
                $linkType = 'tel';
            }

            return [
                't' => $t,
                'type' => $normalizedType,
                'label' => mb_substr($label, 0, 120),
                'href' => (string) ($event['href'] ?? $event['data']['href'] ?? ''),
                'page_url' => (string) ($event['page_url'] ?? $event['path'] ?? ''),
                'link_type' => $linkType,
                'tel_number' => (string) ($event['tel_number'] ?? $event['data']['tel_number'] ?? ''),
                'element_text' => mb_substr((string) ($event['element_text'] ?? $event['text'] ?? $label), 0, 120),
                'x' => is_numeric($x) ? (float) $x : null,
                'y' => is_numeric($y) ? (float) $y : null,
            ];
        }

        return null;
    }
}
