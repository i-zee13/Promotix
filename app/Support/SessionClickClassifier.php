<?php

namespace App\Support;

/**
 * Shared CTA / tel click heuristics for session recordings (tag + analyzer).
 */
class SessionClickClassifier
{
    public static function isTelHref(string $href): bool
    {
        $href = strtolower(trim($href));

        return str_starts_with($href, 'tel:')
            || str_starts_with($href, 'callto:')
            || str_starts_with($href, 'sms:');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function isCtaElement(string $tag, string $className = '', string $id = '', array $attributes = []): bool
    {
        $tag = strtoupper(trim($tag));
        if (array_key_exists('data-cta', $attributes) || ($attributes['data-action'] ?? null) === 'cta') {
            return true;
        }

        $haystack = strtolower(trim($className.' '.$id));
        if ($haystack !== '' && preg_match(
            '/\b(cta|call-to-action|btn-primary|button-primary|btn-cta|convert|signup|sign-up|buy-now|get-started|btn\b|button\b|wp-block-button|elementor-button|submit)\b/',
            $haystack,
        )) {
            return true;
        }

        if ($tag === 'BUTTON') {
            return true;
        }

        $inputType = strtolower((string) ($attributes['type'] ?? ''));
        if ($tag === 'INPUT' && in_array($inputType, ['submit', 'button'], true)) {
            return true;
        }

        return $tag === 'A' && $haystack !== '' && preg_match('/\b(btn|button|cta)\b/', $haystack);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{cta: bool, tel: bool}
     */
    public static function classifyClickEvent(array $event): array
    {
        $type = strtolower((string) ($event['type'] ?? ''));

        if (in_array($type, ['cta_click'], true)) {
            return ['cta' => true, 'tel' => false];
        }
        if (in_array($type, ['phone_click', 'tel_click'], true)) {
            return ['cta' => false, 'tel' => true];
        }
        if ($type !== 'click') {
            return ['cta' => false, 'tel' => false];
        }

        $href = (string) ($event['href'] ?? '');
        $tel = ! empty($event['tel'])
            || ! empty($event['is_tel'])
            || self::isTelHref($href);

        $cta = ! $tel && (
            ! empty($event['cta'])
            || ! empty($event['is_cta'])
            || self::isCtaElement(
                (string) ($event['tag'] ?? ''),
                (string) ($event['class'] ?? $event['element_class'] ?? $event['className'] ?? ''),
                (string) ($event['id'] ?? $event['element_id'] ?? ''),
                is_array($event['attrs'] ?? null) ? $event['attrs'] : [],
            )
        );

        return ['cta' => $cta, 'tel' => $tel];
    }
}
