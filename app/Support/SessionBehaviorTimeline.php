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
        $scrollRawCount = 0;
        $clickRawCount = 0;

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
                $path = trim((string) ($raw['path'] ?? ($url !== '' ? TrafficSourceClassifier::pathFromUrl($url) : '')));
                $name = $path !== '' ? $path : ($title !== '' ? $title : ($url !== '' ? $url : 'Page View'));
                $rows[] = array_merge($base, [
                    'label' => $name,
                    'detail' => trim(($title !== '' && $title !== $name ? $title.' · ' : '').($url !== '' ? $url : $name)),
                    'kind' => 'page',
                    'type' => 'page_view',
                    'page_url' => $url !== '' ? $url : ($base['page_url'] ?? null),
                    'page' => $path !== '' ? $path : $name,
                    'title' => $title !== '' ? $title : null,
                ]);

                continue;
            }

            if ($type === 'page_change') {
                $url = trim((string) ($raw['url'] ?? $raw['page_url'] ?? ''));
                $title = trim((string) ($raw['title'] ?? $raw['headline'] ?? ''));
                $path = trim((string) ($raw['path'] ?? ($url !== '' ? TrafficSourceClassifier::pathFromUrl($url) : '')));
                $name = $path !== '' ? $path : ($title !== '' ? $title : ($url !== '' ? $url : 'Page Change'));
                $rows[] = array_merge($base, [
                    'label' => $name,
                    'detail' => $title !== '' && $title !== $name ? $title : $name,
                    'kind' => 'page',
                    'type' => 'page_change',
                    'page_url' => $url !== '' ? $url : ($base['page_url'] ?? null),
                    'page' => $path !== '' ? $path : $name,
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
                $page = (string) ($raw['page_url'] ?? $raw['path'] ?? '');
                if ($depth !== null && isset($scrollMarks[$depth]) && ! $scrollMarks[$depth]) {
                    $scrollMarks[$depth] = true;
                    $rows[] = array_merge($base, [
                        'label' => 'Scroll',
                        'detail' => $depth.'%'.($page !== '' ? ' on '.$page : ''),
                        'kind' => 'scroll',
                        'type' => 'scroll',
                        'scroll_depth' => $depth,
                    ]);
                } elseif ($depth === null && $scrollRawCount < 3) {
                    // Position-only scroll samples from the tag — keep a few so timeline isn't empty.
                    $scrollRawCount++;
                    $rows[] = array_merge($base, [
                        'label' => 'Scroll',
                        'detail' => $page !== '' ? 'on '.$page : 'scroll',
                        'kind' => 'scroll',
                        'type' => 'scroll',
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
                in_array($type, ['phone_click', 'tel_click', 'call_click'], true)
                || ($type === 'click' && SessionClickClassifier::classifyClickEvent($raw)['tel'])
            ) {
                $text = trim((string) ($raw['element_text'] ?? $raw['text'] ?? ''));
                $href = trim((string) ($raw['href'] ?? ''));
                $tel = trim((string) ($raw['tel_number'] ?? preg_replace('/^(tel|callto|sms):/i', '', $href)));
                $linkType = strtolower(trim((string) ($raw['link_type'] ?? '')));
                $isTelLink = SessionClickClassifier::isTelHref($href)
                    || $type === 'tel_click'
                    || $linkType === 'tel';
                $rows[] = array_merge($base, [
                    'label' => $isTelLink ? 'Tel Link Clicked' : 'Call Button Clicked',
                    'detail' => ($text !== '' ? $text.' → ' : '').($tel !== '' ? $tel : ($href !== '' ? $href : 'call')),
                    'kind' => 'phone',
                    'type' => $isTelLink ? 'tel_click' : 'call_click',
                    'link_type' => $isTelLink ? 'tel' : 'call',
                    'tel_number' => $tel !== '' ? $tel : null,
                    'element_text' => $text !== '' ? $text : null,
                    'href' => $href !== '' ? $href : null,
                ]);

                continue;
            }

            if ($type === 'click' && $clickRawCount < 8) {
                $clickRawCount++;
                $text = trim((string) ($raw['element_text'] ?? $raw['text'] ?? $raw['tag'] ?? ''));
                $href = trim((string) ($raw['href'] ?? ''));
                $rows[] = array_merge($base, [
                    'label' => 'Click',
                    'detail' => ($text !== '' ? $text : 'click').($href !== '' ? ' → '.$href : ''),
                    'kind' => 'cta',
                    'type' => 'cta_click',
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

            if (in_array($type, ['form_view', 'form_viewed'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Form Viewed',
                    'detail' => self::formDetail($raw),
                    'kind' => 'form',
                    'type' => 'form_view',
                    'link_type' => 'form',
                    'form_id' => $raw['form_id'] ?? null,
                    'form_name' => $raw['form_name'] ?? null,
                ]);

                continue;
            }

            if (in_array($type, ['email_click', 'mailto_click'], true)) {
                $email = trim((string) ($raw['email'] ?? preg_replace('/^mailto:/i', '', (string) ($raw['href'] ?? ''))));
                $rows[] = array_merge($base, [
                    'label' => 'Email Click',
                    'detail' => $email !== '' ? $email : 'mailto',
                    'kind' => 'cta',
                    'type' => 'email_click',
                    'href' => $raw['href'] ?? null,
                ]);

                continue;
            }

            if (in_array($type, ['zip_checked', 'zip_check', 'postal_check'], true)) {
                $zip = trim((string) ($raw['zip_code'] ?? $raw['postal_code'] ?? $raw['value'] ?? ''));
                $rows[] = array_merge($base, [
                    'label' => 'ZIP Checked',
                    'detail' => $zip !== '' ? $zip : 'zip',
                    'kind' => 'form',
                    'type' => 'zip_checked',
                ]);

                continue;
            }

            if (in_array($type, ['chat_opened', 'chat_open', 'chat_started'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Chat Opened',
                    'detail' => trim((string) ($raw['element_text'] ?? 'chat')),
                    'kind' => 'cta',
                    'type' => 'chat_opened',
                ]);

                continue;
            }

            if (in_array($type, ['chat_message_sent', 'chat_message', 'chat_sent'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Chat Message Sent',
                    'detail' => trim((string) ($raw['element_text'] ?? $raw['message'] ?? 'message')),
                    'kind' => 'cta',
                    'type' => 'chat_message_sent',
                ]);

                continue;
            }

            if (in_array($type, ['book_click', 'book'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Book Clicked',
                    'detail' => trim((string) ($raw['element_text'] ?? 'book')).' · attempt',
                    'kind' => 'cta',
                    'type' => 'book_click',
                    'success' => false,
                ]);

                continue;
            }

            if (in_array($type, ['appointment_click', 'appointment'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Appointment Clicked',
                    'detail' => trim((string) ($raw['element_text'] ?? 'appointment')).' · attempt',
                    'kind' => 'cta',
                    'type' => 'appointment_click',
                    'success' => false,
                ]);

                continue;
            }

            if (in_array($type, ['booking_confirmed', 'booked'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Booking Confirmed',
                    'detail' => trim((string) ($raw['order_id'] ?? $raw['element_text'] ?? 'confirmed')),
                    'kind' => 'commerce',
                    'type' => 'booking_confirmed',
                    'success' => true,
                ]);

                continue;
            }

            if ($type === 'appointment_confirmed') {
                $rows[] = array_merge($base, [
                    'label' => 'Appointment Confirmed',
                    'detail' => trim((string) ($raw['order_id'] ?? $raw['element_text'] ?? 'confirmed')),
                    'kind' => 'commerce',
                    'type' => 'appointment_confirmed',
                    'success' => true,
                ]);

                continue;
            }

            if (in_array($type, ['form_field_focused', 'form_opened'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Form Field Focused',
                    'detail' => trim((string) ($raw['field_name'] ?? self::formDetail($raw))),
                    'kind' => 'form',
                    'type' => 'form_field_focused',
                ]);

                continue;
            }

            if ($type === 'form_validation_failed') {
                $rows[] = array_merge($base, [
                    'label' => 'Form Validation Failed',
                    'detail' => trim((string) ($raw['field_name'] ?? self::formDetail($raw))),
                    'kind' => 'form',
                    'type' => 'form_validation_failed',
                    'success' => false,
                ]);

                continue;
            }

            if (in_array($type, ['form_submit_failed', 'form_submission_failed'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Form Submission Failed',
                    'detail' => self::formDetail($raw),
                    'kind' => 'form',
                    'type' => 'form_submit_failed',
                    'success' => false,
                ]);

                continue;
            }

            if ($type === 'zip_entered') {
                $rows[] = array_merge($base, [
                    'label' => 'ZIP Entered',
                    'detail' => trim((string) ($raw['zip_code'] ?? 'zip')),
                    'kind' => 'form',
                    'type' => 'zip_entered',
                ]);

                continue;
            }

            if ($type === 'provider_selected') {
                $rows[] = array_merge($base, [
                    'label' => 'Provider Selected',
                    'detail' => trim((string) ($raw['provider'] ?? $raw['value'] ?? 'provider')),
                    'kind' => 'form',
                    'type' => 'provider_selected',
                ]);

                continue;
            }

            if ($type === 'navigation_menu_opened') {
                $rows[] = array_merge($base, [
                    'label' => 'Navigation Menu Opened',
                    'detail' => trim((string) ($raw['element_text'] ?? 'menu')),
                    'kind' => 'page',
                    'type' => 'navigation_menu_opened',
                ]);

                continue;
            }

            if ($type === 'search_used') {
                $rows[] = array_merge($base, [
                    'label' => 'Search Used',
                    'detail' => trim((string) ($raw['element_text'] ?? 'search')),
                    'kind' => 'page',
                    'type' => 'search_used',
                ]);

                continue;
            }

            if (in_array($type, ['pricing_viewed', 'provider_viewed', 'availability_viewed'], true)) {
                $rows[] = array_merge($base, [
                    'label' => match ($type) {
                        'provider_viewed' => 'Provider Viewed',
                        'availability_viewed' => 'Availability Viewed',
                        default => 'Pricing Viewed',
                    },
                    'detail' => trim((string) ($raw['path'] ?? $raw['page_url'] ?? $type)),
                    'kind' => 'page',
                    'type' => $type,
                ]);

                continue;
            }

            if ($type === 'external_link') {
                $rows[] = array_merge($base, [
                    'label' => 'External Link',
                    'detail' => trim((string) ($raw['href'] ?? $raw['element_text'] ?? 'external')),
                    'kind' => 'cta',
                    'type' => 'external_link',
                    'href' => $raw['href'] ?? null,
                ]);

                continue;
            }

            if (in_array($type, ['file_download', 'download'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'File Download',
                    'detail' => trim((string) ($raw['href'] ?? $raw['element_text'] ?? 'download')),
                    'kind' => 'cta',
                    'type' => 'file_download',
                    'href' => $raw['href'] ?? null,
                ]);

                continue;
            }

            if (in_array($type, ['form_submit', 'form_fill'], true)) {
                $success = array_key_exists('success', $raw) ? ((bool) $raw['success'] ? 'success' : 'failed') : '';
                $rows[] = array_merge($base, [
                    'label' => 'Form Submitted',
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

            if (in_array($type, ['checkout', 'begin_checkout', 'initiate_checkout'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Checkout Started',
                    'detail' => self::commerceDetail($raw),
                    'kind' => 'commerce',
                    'type' => 'checkout',
                ]);

                continue;
            }

            if (in_array($type, ['purchase', 'sale', 'order', 'transaction', 'purchase_completed'], true)) {
                $rows[] = array_merge($base, [
                    'label' => 'Purchase Completed',
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
