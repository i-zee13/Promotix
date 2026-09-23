<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainGoogleAdsMapping;
use App\Support\AudienceRuleEvaluator;
use App\Support\AudienceRuleSchema;

/**
 * Consent-gated invalid-traffic audience signal for GA4 / GTM / Google Ads website lists.
 *
 * Guide contract: same-browser Google tag event — never upload Device ID / IP / fingerprint.
 * Event: cr_invalid_traffic. Fire when customer rule matches final decision.
 * Idempotency key: audience_id:decision_id.
 */
class AudienceSignalService
{
    public const DEFAULT_EVENT = 'cr_invalid_traffic';

    public const VERDICT_PARAM = 'cr_traffic_verdict';

    public const VERDICT_INVALID = 'invalid';

    public const EVENT_VERSION = '1.0';

    /**
     * Build canonical §5 parameter map from a detection payload (no PII / IP / fingerprint).
     *
     * @param  array<string, mixed>  $detection
     * @return array<string, mixed>
     */
    public function buildDecisionParams(array $detection): array
    {
        $status = strtolower(trim((string) ($detection['traffic_status'] ?? '')));
        $action = strtolower(trim((string) ($detection['action_taken'] ?? 'allow')));
        if ($action === 'block') {
            $action = 'blocked';
        }

        if ($status === '') {
            $status = match (true) {
                $action === 'blocked' => self::VERDICT_INVALID,
                in_array($action, ['flag', 'challenge'], true) => 'suspicious',
                default => 'valid',
            };
        }

        $threat = strtolower(trim((string) ($detection['threat_group'] ?? '')));
        $reason = strtolower(trim((string) ($detection['invalid_reason'] ?? $detection['reasons'][0] ?? $threat)));
        $category = strtolower(trim((string) ($detection['invalid_category'] ?? '')));
        if ($category === '') {
            $category = match (true) {
                str_contains($reason, 'repeat') || str_contains($threat, 'rapid') => 'click_fraud',
                str_contains($reason, 'bot') || str_contains($threat, 'crawler') => 'bot',
                str_contains($reason, 'form') || str_contains($reason, 'spam') => 'form_spam',
                default => ($status === self::VERDICT_INVALID ? 'other' : null),
            };
        }

        $score = isset($detection['threat_score']) ? (int) $detection['threat_score'] : null;
        if ($score === null && isset($detection['paid_risk_score'])) {
            $score = (int) $detection['paid_risk_score'];
        }

        $decisionId = $this->decisionId($detection, $status);

        $params = [
            'cr_event_version' => self::EVENT_VERSION,
            'cr_decision_id' => $decisionId,
            'cr_event_id' => $this->eventId($decisionId),
            'cr_traffic_verdict' => $status,
            'traffic_verdict' => $status,
            'traffic_status' => $status,
            'cr_protection_action' => $action !== '' ? $action : null,
            'protection_action' => $action !== '' ? $action : null,
            'cr_invalid_category' => $category,
            'cr_invalid_reason' => $reason !== '' ? $reason : null,
            'cr_risk_score' => $score,
            'cr_action' => $this->normalizeJourneyAction(
                $detection['cr_action'] ?? $detection['journey_action'] ?? null
            ),
            'cr_actions' => $this->normalizeJourneyActions($detection['cr_actions'] ?? $detection['journey_actions'] ?? null),
            'cr_outcome' => $detection['cr_outcome'] ?? $detection['outcome'] ?? null,
            'cr_lead_status' => $detection['cr_lead_status'] ?? $detection['lead_status'] ?? null,
            'cr_form_status' => $detection['cr_form_status'] ?? $detection['form_status'] ?? null,
            'cr_keyword_class' => $detection['cr_keyword_class'] ?? $detection['keyword_class'] ?? null,
            'cr_repeat_click_count' => isset($detection['cr_repeat_click_count'])
                ? (int) $detection['cr_repeat_click_count']
                : (isset($detection['paid_clicks_today'])
                    ? (int) $detection['paid_clicks_today']
                    : (isset($detection['click_count']) ? (int) $detection['click_count'] : null)),
            'cr_challenge_result' => $detection['cr_challenge_result'] ?? $detection['challenge_result'] ?? null,
            'cr_zip_status' => $detection['cr_zip_status'] ?? $detection['zip_status'] ?? null,
            'cr_campaign_id' => $detection['cr_campaign_id'] ?? $detection['campaign_id'] ?? null,
            'cr_occurred_at' => now('UTC')->toIso8601String(),
            'threat_group' => $threat !== '' ? $threat : null,
            'action_taken' => $action,
        ];

        return array_filter(
            $params,
            static fn ($v) => $v !== null && $v !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $detection
     * @return array{fire: bool, event: string, traffic_verdict: string, decision_id: ?string, matched_audience_ids: list<string>}
     */
    public function decisionFromDetection(array $detection, ?Domain $domain = null): array
    {
        $params = $this->buildDecisionParams($detection);
        $status = (string) ($params['cr_traffic_verdict'] ?? 'valid');

        // Audience exclusion is ads-only — never fire for organic traffic.
        $isPaid = filter_var($detection['is_paid_traffic'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ! empty($detection['gclid'])
            || ! empty($detection['gbraid'])
            || ! empty($detection['wbraid']);
        if (! $isPaid) {
            return [
                'fire' => false,
                'event' => self::DEFAULT_EVENT,
                'traffic_verdict' => $status,
                'decision_id' => $params['cr_decision_id'] ?? null,
                'matched_audience_ids' => [],
                'params' => $params,
            ];
        }

        $audiences = $this->activeAudiencesForDomain($domain);

        $matchedIds = [];
        foreach ($audiences as $audience) {
            $rule = is_array($audience['rule'] ?? null)
                ? $audience['rule']
                : AudienceRuleSchema::defaultPreset();
            if (AudienceRuleEvaluator::matches($rule, $params)) {
                $matchedIds[] = (string) $audience['id'];
            }
        }

        // No saved audience yet: still fire on default preset so install/test works.
        if ($audiences === [] && AudienceRuleEvaluator::matches(AudienceRuleSchema::defaultPreset(), $params)) {
            $matchedIds[] = 'default';
        }

        $fire = $matchedIds !== [];

        return [
            'fire' => $fire,
            'event' => self::DEFAULT_EVENT,
            'traffic_verdict' => $status,
            'decision_id' => $params['cr_decision_id'] ?? null,
            'matched_audience_ids' => $matchedIds,
            'params' => $params,
        ];
    }

    /**
     * Fields merged into the browser tracking JSON response.
     *
     * @param  array<string, mixed>  $detection
     * @return array<string, mixed>
     */
    public function clientFlags(array $detection, ?Domain $domain = null): array
    {
        $decision = $this->decisionFromDetection($detection, $domain);
        if (! $decision['fire']) {
            return [];
        }

        $params = $decision['params'];
        $audienceId = (string) ($decision['matched_audience_ids'][0] ?? 'default');

        return [
            'fire_audience_event' => true,
            'fire_audience_signal' => true,
            'audience_event' => $decision['event'],
            'audience_id' => $audienceId,
            'audience_ids' => $decision['matched_audience_ids'],
            'audience_traffic_verdict' => $params['cr_traffic_verdict'] ?? self::VERDICT_INVALID,
            'audience_traffic_status' => $params['cr_traffic_verdict'] ?? self::VERDICT_INVALID,
            'audience_decision_id' => $params['cr_decision_id'] ?? null,
            'audience_event_id' => $params['cr_event_id'] ?? null,
            'audience_event_version' => self::EVENT_VERSION,
            'audience_protection_action' => $params['cr_protection_action'] ?? null,
            'audience_invalid_category' => $params['cr_invalid_category'] ?? null,
            'audience_invalid_reason' => $params['cr_invalid_reason'] ?? null,
            'audience_risk_score' => $params['cr_risk_score'] ?? null,
            'audience_action' => $params['cr_action'] ?? null,
            'audience_outcome' => $params['cr_outcome'] ?? null,
            'audience_lead_status' => $params['cr_lead_status'] ?? null,
            'audience_form_status' => $params['cr_form_status'] ?? null,
            'audience_keyword_class' => $params['cr_keyword_class'] ?? null,
            'audience_repeat_click_count' => $params['cr_repeat_click_count'] ?? null,
            'audience_challenge_result' => $params['cr_challenge_result'] ?? null,
            'audience_zip_status' => $params['cr_zip_status'] ?? null,
            'audience_campaign_id' => $params['cr_campaign_id'] ?? null,
            'audience_occurred_at' => $params['cr_occurred_at'] ?? now('UTC')->toIso8601String(),
            'audience_detection_type' => $params['cr_invalid_reason'] ?? $params['threat_group'] ?? null,
        ];
    }

    /**
     * @return list<array{id: string, rule: array<string, mixed>, method: string}>
     */
    public function activeAudiencesForDomain(?Domain $domain): array
    {
        if ($domain === null) {
            return [];
        }

        $mapping = DomainGoogleAdsMapping::query()
            ->where('domain_id', $domain->id)
            ->orderByDesc('id')
            ->first();

        $settings = is_array($mapping?->settings) ? $mapping->settings : [];
        $byList = is_array($settings['audience_lists'] ?? null) ? $settings['audience_lists'] : [];
        $byRoute = is_array($settings['audience_associations'] ?? null)
            ? $settings['audience_associations']
            : [];

        $out = [];
        $seen = [];

        foreach ($byList as $listId => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', (string) ($row['user_list_id'] ?? $listId));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rule = is_array($row['rule'] ?? null)
                ? AudienceRuleSchema::normalize($row['rule'])['rule']
                : AudienceRuleSchema::defaultPreset();

            $out[] = [
                'id' => $id,
                'rule' => $rule,
                'method' => (string) ($row['method'] ?? $row['route'] ?? 'ga4'),
                'membership_days' => (int) ($row['membership_days'] ?? 90),
            ];
        }

        foreach ($byRoute as $route => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', (string) ($row['user_list_id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rule = is_array($row['rule'] ?? null)
                ? AudienceRuleSchema::normalize($row['rule'])['rule']
                : AudienceRuleSchema::defaultPreset();

            $out[] = [
                'id' => $id,
                'rule' => $rule,
                'method' => (string) ($row['method'] ?? $route),
                'membership_days' => (int) ($row['membership_days'] ?? 90),
            ];
        }

        return $out;
    }

    /**
     * Map stored / behavior event labels onto AudienceRuleSchema cr_action values.
     */
    public function normalizeJourneyAction(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $key = strtolower(trim((string) $raw));

        return match ($key) {
            'page_view', 'page' => 'page_view',
            'form_submitted', 'form_submit', 'form_fill' => 'form_submitted',
            'form_link', 'form_start' => 'form_link',
            'cta_click', 'cta' => 'cta_click',
            'tel_click', 'phone_click', 'call_click' => 'tel_click',
            'add_to_cart', 'cart' => 'add_to_cart',
            'checkout' => 'checkout',
            'purchase', 'sale' => 'purchase',
            'exit', 'session_exit' => 'exit',
            default => in_array($key, AudienceRuleSchema::parameters()['cr_action']['values'] ?? [], true) ? $key : null,
        };
    }

    /**
     * @param  mixed  $raw
     * @return list<string>|null
     */
    public function normalizeJourneyActions(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $list = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($list as $item) {
            $normalized = $this->normalizeJourneyAction($item);
            if ($normalized !== null) {
                $out[$normalized] = $normalized;
            }
        }

        return $out === [] ? null : array_values($out);
    }

    /**
     * @param  array<string, mixed>  $detection
     */
    private function decisionId(array $detection, string $status): string
    {
        if (! empty($detection['decision_id']) && is_string($detection['decision_id'])) {
            return (string) $detection['decision_id'];
        }

        $basis = implode('|', [
            (string) ($detection['visit_id'] ?? ''),
            (string) ($detection['ip'] ?? ''),
            (string) ($detection['session_id'] ?? ''),
            $status,
            (string) ($detection['threat_group'] ?? ''),
            (string) ($detection['action_taken'] ?? ''),
            (string) ($detection['threat_score'] ?? ''),
        ]);

        return 'DEC-'.strtoupper(substr(hash('sha256', $basis !== '|||||' ? $basis : uniqid('dec', true)), 0, 10));
    }

    private function eventId(?string $decisionId): string
    {
        $seed = ($decisionId ?: uniqid('evt', true)).'|'.microtime(true);

        return 'EVT-'.strtoupper(substr(hash('sha256', $seed), 0, 8));
    }
}
