<?php

namespace App\Support;

/**
 * Controlled audience exclusion rule catalog (guide §4).
 * Customers pick labels/operators/values — never free-text parameter names.
 */
final class AudienceRuleSchema
{
    /** Canonical cr_invalid_traffic parameter catalog version (guide §5 / §19). */
    public const SCHEMA_VERSION = '1.0';

    public const MATCH_ANY = 'any';

    public const MATCH_ALL = 'all';

    /**
     * @return array<string, array{label: string, operators: list<string>, values?: list<string>, type: string}>
     */
    public static function parameters(): array
    {
        return [
            'cr_traffic_verdict' => [
                'label' => 'Traffic verdict',
                'operators' => ['=', '!='],
                'values' => ['invalid', 'suspicious', 'valid'],
                'type' => 'enum',
            ],
            'cr_protection_action' => [
                'label' => 'Protection action',
                'operators' => ['=', 'in'],
                'values' => ['blocked', 'challenge', 'flag', 'allow'],
                'type' => 'enum',
            ],
            'cr_invalid_category' => [
                'label' => 'Invalid category',
                'operators' => ['=', 'in'],
                'values' => ['click_fraud', 'bot', 'form_spam', 'other'],
                'type' => 'enum',
            ],
            'cr_invalid_reason' => [
                'label' => 'Invalid reason',
                'operators' => ['=', 'in'],
                'values' => ['repeat_clicks', 'bot_automation', 'duplicate', 'challenge_failed', 'other'],
                'type' => 'enum',
            ],
            'cr_risk_score' => [
                'label' => 'Risk score',
                'operators' => ['>', '>=', 'between'],
                'type' => 'number',
            ],
            'cr_action' => [
                'label' => 'Journey action',
                'operators' => ['=', 'in'],
                'values' => [
                    'page_view',
                    'form_submitted',
                    'form_link',
                    'cta_click',
                    'tel_click',
                    'add_to_cart',
                    'checkout',
                    'purchase',
                    'exit',
                ],
                'type' => 'enum',
            ],
            'cr_outcome' => [
                'label' => 'Outcome',
                'operators' => ['=', 'in'],
                'values' => ['spam', 'lead', 'none', 'pending'],
                'type' => 'enum',
            ],
            'cr_lead_status' => [
                'label' => 'Lead status',
                'operators' => ['=', 'in'],
                'values' => ['invalid', 'valid', 'pending'],
                'type' => 'enum',
            ],
            'cr_form_status' => [
                'label' => 'Form status',
                'operators' => ['=', 'in'],
                'values' => ['submitted', 'started', 'abandoned'],
                'type' => 'enum',
            ],
            'cr_keyword_class' => [
                'label' => 'Keyword class',
                'operators' => ['=', 'in'],
                'values' => ['support_intent', 'high_risk', 'brand', 'other'],
                'type' => 'enum',
            ],
            'cr_repeat_click_count' => [
                'label' => 'Repeat clicks',
                // Only >= — threshold N means at least N (includes N). Do not offer ">" (excludes N).
                'operators' => ['>='],
                'type' => 'number',
            ],
            'cr_challenge_result' => [
                'label' => 'Challenge result',
                'operators' => ['=', 'in'],
                'values' => ['failed', 'passed', 'skipped'],
                'type' => 'enum',
            ],
            'cr_zip_status' => [
                'label' => 'ZIP status',
                'operators' => ['=', 'in'],
                'values' => ['unserviceable', 'serviceable', 'unknown'],
                'type' => 'enum',
            ],
        ];
    }

    /**
     * Guide default: invalid OR blocked, 90 days, CR - Invalid and Blocked.
     *
     * @return array{match_mode: string, conditions: list<array{param: string, op: string, value: mixed}>}
     */
    public static function defaultPreset(): array
    {
        return [
            'match_mode' => self::MATCH_ANY,
            'conditions' => [
                ['param' => 'cr_traffic_verdict', 'op' => '=', 'value' => 'invalid'],
                ['param' => 'cr_protection_action', 'op' => '=', 'value' => 'blocked'],
            ],
        ];
    }

    public static function defaultAudienceName(string $method = 'ga4'): string
    {
        return $method === 'website'
            ? 'CR - Direct Ads - Invalid Visitors'
            : 'CR - Invalid and Blocked';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{ok: bool, rule: array{match_mode: string, conditions: list<array{param: string, op: string, value: mixed}>}, error: ?string}
     */
    public static function normalize(array $rule): array
    {
        $catalog = self::parameters();
        $mode = strtolower((string) ($rule['match_mode'] ?? self::MATCH_ANY));
        if (! in_array($mode, [self::MATCH_ANY, self::MATCH_ALL], true)) {
            $mode = self::MATCH_ANY;
        }

        $conditions = [];
        $raw = $rule['conditions'] ?? [];
        if (! is_array($raw)) {
            return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => 'Invalid conditions.'];
        }

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $param = (string) ($row['param'] ?? '');
            if (! isset($catalog[$param])) {
                return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => 'Unknown parameter: '.$param];
            }
            $op = (string) ($row['op'] ?? '=');
            // Product meaning: threshold N = at least N (includes N). Map legacy ops to >=.
            if ($param === 'cr_repeat_click_count' && in_array($op, ['>', '=', '=>'], true)) {
                $op = '>=';
            }
            if (! in_array($op, $catalog[$param]['operators'], true)) {
                return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => 'Operator not allowed for '.$param];
            }
            $value = $row['value'] ?? null;
            if ($value === null || $value === '') {
                return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => 'Value required for '.$param];
            }

            $type = $catalog[$param]['type'] ?? 'enum';
            if ($type === 'number') {
                if ($op === 'between') {
                    if (is_array($value)) {
                        if (count($value) < 2 || ! is_numeric($value[0]) || ! is_numeric($value[1])) {
                            return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => 'Repeat / risk scores need numeric values (e.g. 2), not "'.$value.'".'];
                        }
                        $value = [(float) $value[0], (float) $value[1]];
                    } else {
                        $parts = preg_split('/\s*(?:,|-|to)\s*/i', (string) $value) ?: [];
                        if (count($parts) < 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                            return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => $param.' needs a number (e.g. 2), not "'.(string) $value.'".'];
                        }
                        $value = [(float) $parts[0], (float) $parts[1]];
                    }
                } elseif (! is_numeric($value)) {
                    // Common UI bug: switching from Traffic verdict (= invalid) to Repeat clicks
                    // leaves value "invalid" — coerce to a safe default threshold.
                    $value = 1;
                } else {
                    $value = (float) $value + 0;
                    if ((float) $value == (int) $value) {
                        $value = (int) $value;
                    }
                }
            } elseif ($type === 'enum') {
                $allowed = $catalog[$param]['values'] ?? [];
                if ($allowed !== [] && $op !== 'in' && ! in_array((string) $value, $allowed, true)
                    && ! in_array(strtolower((string) $value), array_map('strtolower', $allowed), true)) {
                    return ['ok' => false, 'rule' => self::defaultPreset(), 'error' => 'Invalid value for '.$param];
                }
            }

            $conditions[] = [
                'param' => $param,
                'op' => $op,
                'value' => $value,
            ];
        }

        if ($conditions === []) {
            return ['ok' => true, 'rule' => self::defaultPreset(), 'error' => null];
        }

        return [
            'ok' => true,
            'rule' => [
                'match_mode' => $mode,
                'conditions' => $conditions,
            ],
            'error' => null,
        ];
    }

    /**
     * True when normalize() would change a number condition's value (e.g. "invalid" → 1)
     * or upgrade Repeat clicks ">" / "=" to ">=".
     *
     * @param  array{match_mode?: string, conditions?: list<array{param?: string, op?: string, value?: mixed}>}  $rule
     */
    public static function needsNumericCoercion(array $rule): bool
    {
        return self::sanitizationNotes($rule) !== [];
    }

    /**
     * Human notes for what normalize() would fix on a stored rule.
     *
     * @param  array{match_mode?: string, conditions?: list<array{param?: string, op?: string, value?: mixed}>}  $rule
     * @return list<string>
     */
    public static function sanitizationNotes(array $rule): array
    {
        $catalog = self::parameters();
        $notes = [];
        foreach ($rule['conditions'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $param = (string) ($row['param'] ?? '');
            if (! isset($catalog[$param]) || ($catalog[$param]['type'] ?? '') !== 'number') {
                continue;
            }
            $value = $row['value'] ?? null;
            $op = (string) ($row['op'] ?? '=');
            if ($param === 'cr_repeat_click_count' && in_array($op, ['>', '=', '=>'], true)) {
                $notes[] = 'operator upgraded from '.$op.' to >= (includes the threshold)';
            }
            if ($op === 'between') {
                continue;
            }
            if ($value !== null && $value !== '' && ! is_numeric($value)) {
                $notes[] = 'non-numeric value "'.(string) $value.'" replaced with 1';
            }
        }

        return array_values(array_unique($notes));
    }

    /**
     * @param  array{match_mode?: string, conditions?: list<array{param: string, op: string, value: mixed}>}  $rule
     */
    public static function naturalLanguageSummary(array $rule): string
    {
        $normalized = self::normalize($rule);
        $rule = $normalized['rule'];
        $mode = ($rule['match_mode'] ?? self::MATCH_ANY) === self::MATCH_ALL ? 'ALL' : 'ANY';
        $catalog = self::parameters();
        $parts = [];
        foreach ($rule['conditions'] as $c) {
            $label = $catalog[$c['param']]['label'] ?? $c['param'];
            $val = is_array($c['value']) ? implode(', ', $c['value']) : (string) $c['value'];
            $parts[] = "{$label} {$c['op']} {$val}";
        }

        if ($parts === []) {
            return 'No conditions configured.';
        }

        $joiner = $mode === 'ALL' ? ' AND ' : ' OR ';

        return 'Include when '.$mode.' of: '.implode($joiner, $parts).'.';
    }

    /**
     * UI catalog payload for Alpine / API.
     *
     * @return array{parameters: list<array<string, mixed>>, default_preset: array<string, mixed>, default_names: array{ga4: string, website: string}}
     */
    public static function forUi(): array
    {
        $params = [];
        foreach (self::parameters() as $key => $meta) {
            $params[] = [
                'param' => $key,
                'label' => $meta['label'],
                'operators' => $meta['operators'],
                'values' => $meta['values'] ?? [],
                'type' => $meta['type'],
            ];
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'parameters' => $params,
            'default_preset' => self::defaultPreset(),
            'default_names' => [
                'ga4' => self::defaultAudienceName('ga4'),
                'website' => self::defaultAudienceName('website'),
            ],
        ];
    }
}
