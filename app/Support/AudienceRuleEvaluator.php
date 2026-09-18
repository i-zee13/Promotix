<?php

namespace App\Support;

/**
 * Evaluate customer audience rule against a decision parameter map.
 */
final class AudienceRuleEvaluator
{
    /**
     * @param  array{match_mode?: string, conditions?: list<array{param: string, op: string, value: mixed}>}  $rule
     * @param  array<string, mixed>  $params  Canonical cr_* keys + aliases
     */
    public static function matches(array $rule, array $params): bool
    {
        $normalized = AudienceRuleSchema::normalize($rule);
        $rule = $normalized['rule'];
        $conditions = $rule['conditions'] ?? [];
        if ($conditions === []) {
            return false;
        }

        $mode = ($rule['match_mode'] ?? AudienceRuleSchema::MATCH_ANY) === AudienceRuleSchema::MATCH_ALL
            ? AudienceRuleSchema::MATCH_ALL
            : AudienceRuleSchema::MATCH_ANY;

        $results = [];
        foreach ($conditions as $condition) {
            $results[] = self::matchCondition($condition, $params);
        }

        if ($mode === AudienceRuleSchema::MATCH_ALL) {
            return ! in_array(false, $results, true);
        }

        return in_array(true, $results, true);
    }

    /**
     * @param  array{param: string, op: string, value: mixed}  $condition
     * @param  array<string, mixed>  $params
     */
    private static function matchCondition(array $condition, array $params): bool
    {
        $param = (string) ($condition['param'] ?? '');
        $op = (string) ($condition['op'] ?? '=');
        $expected = $condition['value'] ?? null;
        $actual = $params[$param] ?? null;

        // Aliases for GA4-mapped short names.
        if ($actual === null) {
            $aliases = [
                'cr_traffic_verdict' => ['traffic_verdict', 'traffic_status'],
                'cr_protection_action' => ['protection_action', 'action_taken'],
                'cr_invalid_reason' => ['invalid_reason', 'threat_group'],
                'cr_risk_score' => ['risk_score', 'threat_score', 'paid_risk_score'],
            ];
            foreach ($aliases[$param] ?? [] as $alias) {
                if (array_key_exists($alias, $params) && $params[$alias] !== null && $params[$alias] !== '') {
                    $actual = $params[$alias];
                    break;
                }
            }
        }

        if ($actual === null || $actual === '') {
            return false;
        }

        return match ($op) {
            '=' => self::equals($actual, $expected),
            '!=' => ! self::equals($actual, $expected),
            'in' => self::inList($actual, $expected),
            '>' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            '>=' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'between' => self::between($actual, $expected),
            default => false,
        };
    }

    private static function equals(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return strtolower((string) $actual) === strtolower((string) $expected);
    }

    private static function inList(mixed $actual, mixed $expected): bool
    {
        $list = is_array($expected)
            ? $expected
            : (preg_split('/\s*,\s*/', (string) $expected) ?: []);

        $needle = strtolower((string) $actual);
        foreach ($list as $item) {
            if ($needle === strtolower((string) $item)) {
                return true;
            }
        }

        return false;
    }

    private static function between(mixed $actual, mixed $expected): bool
    {
        if (! is_numeric($actual)) {
            return false;
        }
        if (is_array($expected) && count($expected) >= 2) {
            $min = (float) $expected[0];
            $max = (float) $expected[1];
        } else {
            $parts = preg_split('/\s*(?:,|-|to)\s*/i', (string) $expected) ?: [];
            if (count($parts) < 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                return false;
            }
            $min = (float) $parts[0];
            $max = (float) $parts[1];
        }
        $n = (float) $actual;

        return $n >= min($min, $max) && $n <= max($min, $max);
    }
}
