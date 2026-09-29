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
     * Click-to-call / phone CTA copy (often a BUTTON without tel: href).
     */
    public static function isCallLabel(string $text): bool
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
        if ($text === '' || mb_strlen($text) > 80) {
            return false;
        }

        return (bool) preg_match(
            '/\b('
            .'call\s*(us|now|today|me|back)?|click\s*to\s*call|tap\s*to\s*call|'
            .'phone\s*(us|now|call)?|dial\s*(us|now)?|'
            .'talk\s*to\s*(an?\s*)?(expert|agent|specialist|rep|us)|'
            .'speak\s*(to|with)\s*(an?\s*)?(expert|agent|specialist|rep|us)|'
            .'request\s*(a\s*)?callback|schedule\s*(a\s*)?call'
            .')\b/i',
            $text,
        );
    }

    /**
     * ISP / lead-gen CTAs often omit btn/cta classes — match label copy.
     * Call / phone intents are handled by isCallLabel (not counted as generic CTA).
     */
    public static function isCtaLabel(string $text): bool
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
        if ($text === '' || mb_strlen($text) > 80) {
            return false;
        }
        if (self::isCallLabel($text)) {
            return false;
        }

        return (bool) preg_match(
            '/\b('
            .'get\s*started|shop\s*now|buy\s*now|order\s*now|order\s*online|sign\s*up|signup|'
            .'subscribe|check\s*availability|check\s*avail|see\s*(plans|pricing|offers)|'
            .'view\s*(plans|pricing|offers)|compare\s*plans|request\s*(a\s*)?quote|get\s*(a\s*)?quote|'
            .'apply\s*now|learn\s*more|contact\s*us|'
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
     * Class / id heuristics for click-to-call widgets.
     */
    public static function isCallElement(string $className = '', string $id = '', array $attributes = []): bool
    {
        if (array_key_exists('data-call', $attributes)
            || array_key_exists('data-phone', $attributes)
            || in_array(strtolower((string) ($attributes['data-action'] ?? '')), ['call', 'phone', 'tel'], true)) {
            return true;
        }

        $haystack = strtolower(trim($className.' '.$id));
        if ($haystack === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(click[_-]?to[_-]?call|call[_-]?now|call[_-]?btn|call[_-]?button|phone[_-]?btn|phone[_-]?button|tel[_-]?btn|tel[_-]?link|calltracker|callrail|whatconverts)\b/',
            $haystack,
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
        if (self::isCallLabel($text) || self::isCallElement($className, $id, $attributes) || self::isTelHref($href)) {
            return false;
        }

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
        if (in_array($type, ['phone_click', 'tel_click', 'call_click'], true)) {
            return ['cta' => false, 'tel' => true];
        }
        if ($type !== 'click') {
            return ['cta' => false, 'tel' => false];
        }

        $href = (string) ($event['href'] ?? '');
        $text = (string) ($event['element_text'] ?? $event['text'] ?? $event['label'] ?? '');
        $className = (string) ($event['class'] ?? $event['element_class'] ?? $event['className'] ?? '');
        $id = (string) ($event['id'] ?? $event['element_id'] ?? '');
        $attrs = is_array($event['attrs'] ?? null) ? $event['attrs'] : [];
        if ($role = $event['role'] ?? null) {
            $attrs['role'] = $role;
        }

        $tel = ! empty($event['tel'])
            || ! empty($event['is_tel'])
            || self::isTelHref($href)
            || self::isCallLabel($text)
            || self::isCallElement($className, $id, $attrs);

        $cta = ! $tel && (
            ! empty($event['cta'])
            || ! empty($event['is_cta'])
            || self::isCtaElement(
                (string) ($event['tag'] ?? ''),
                $className,
                $id,
                $attrs,
                $text,
                $href,
            )
        );

        return ['cta' => $cta, 'tel' => $tel];
    }
}
