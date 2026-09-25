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
     * ISP / lead-gen CTAs often omit btn/cta classes — match label copy.
     */
    public static function isCtaLabel(string $text): bool
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
        if ($text === '' || mb_strlen($text) > 80) {
            return false;
        }

        return (bool) preg_match(
            '/\b('
            .'get\s*started|shop\s*now|buy\s*now|order\s*now|order\s*online|sign\s*up|signup|'
            .'subscribe|check\s*availability|check\s*avail|see\s*(plans|pricing|offers)|'
            .'view\s*(plans|pricing|offers)|compare\s*plans|request\s*(a\s*)?quote|get\s*(a\s*)?quote|'
            .'apply\s*now|learn\s*more|contact\s*us|call\s*now|talk\s*to\s*(an?\s*)?(expert|agent|us)|'
            .'continue|next\s*step|submit|send|book\s*now|schedule|claim\s*(offer|deal)|'
            .'start\s*(your\s*)?(order|application)|find\s*(a\s*)?plan|choose\s*(a\s*)?plan|'
            .'zip\s*check|enter\s*(your\s*)?zip'
            .')\b/i',
            $text,
        );
    }

    public static function isCtaHref(string $href): bool
    {
        $path = strtolower(parse_url(trim($href), PHP_URL_PATH) ?: trim($href));
        if ($path === '' || $path === '/') {
            return false;
        }

        return (bool) preg_match(
            '#(order|checkout|signup|sign-up|subscribe|quote|apply|contact|pricing|'
            .'plans?|cart|buy|shop|get-started|availability|offer|promo|convert)#i',
            $path,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function isCtaElement(
        string $tag,
        string $className = '',
        string $id = '',
        array $attributes = [],
        string $text = '',
        string $href = '',
    ): bool {
        $tag = strtoupper(trim($tag));
        if (array_key_exists('data-cta', $attributes) || ($attributes['data-action'] ?? null) === 'cta') {
            return true;
        }

        $role = strtolower((string) ($attributes['role'] ?? ''));
        if ($role === 'button') {
            return true;
        }

        $haystack = strtolower(trim($className.' '.$id));
        if ($haystack !== '' && preg_match(
            '/\b(cta|call-to-action|btn-primary|button-primary|btn-cta|convert|signup|sign-up|buy-now|get-started|btn\b|button\b|wp-block-button|elementor-button|submit|hero-action|action-btn|primary-action)\b/',
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

        if (self::isCtaLabel($text)) {
            return true;
        }

        if ($href !== '' && self::isCtaHref($href)) {
            return in_array($tag, ['A', 'BUTTON', 'INPUT', ''], true);
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

        $text = (string) ($event['element_text'] ?? $event['text'] ?? $event['label'] ?? '');
        $attrs = is_array($event['attrs'] ?? null) ? $event['attrs'] : [];
        if ($role = $event['role'] ?? null) {
            $attrs['role'] = $role;
        }

        $cta = ! $tel && (
            ! empty($event['cta'])
            || ! empty($event['is_cta'])
            || self::isCtaElement(
                (string) ($event['tag'] ?? ''),
                (string) ($event['class'] ?? $event['element_class'] ?? $event['className'] ?? ''),
                (string) ($event['id'] ?? $event['element_id'] ?? ''),
                $attrs,
                $text,
                $href,
            )
        );

        return ['cta' => $cta, 'tel' => $tel];
    }
}
