<?php

namespace Tests\Unit;

use App\Support\AudienceRuleEvaluator;
use App\Support\AudienceRuleSchema;
use Tests\TestCase;

class AudienceRuleEvaluatorTest extends TestCase
{
    public function test_match_any_fires_on_invalid_or_blocked(): void
    {
        $rule = AudienceRuleSchema::defaultPreset();
        $this->assertSame('any', $rule['match_mode']);

        $this->assertTrue(AudienceRuleEvaluator::matches($rule, [
            'cr_traffic_verdict' => 'invalid',
            'cr_protection_action' => 'allow',
        ]));

        $this->assertTrue(AudienceRuleEvaluator::matches($rule, [
            'cr_traffic_verdict' => 'suspicious',
            'cr_protection_action' => 'blocked',
        ]));

        $this->assertFalse(AudienceRuleEvaluator::matches($rule, [
            'cr_traffic_verdict' => 'valid',
            'cr_protection_action' => 'allow',
        ]));
    }

    public function test_match_all_requires_every_condition(): void
    {
        $rule = [
            'match_mode' => 'all',
            'conditions' => [
                ['param' => 'cr_traffic_verdict', 'op' => '=', 'value' => 'invalid'],
                ['param' => 'cr_protection_action', 'op' => '=', 'value' => 'blocked'],
            ],
        ];

        $this->assertTrue(AudienceRuleEvaluator::matches($rule, [
            'cr_traffic_verdict' => 'invalid',
            'cr_protection_action' => 'blocked',
        ]));

        $this->assertFalse(AudienceRuleEvaluator::matches($rule, [
            'cr_traffic_verdict' => 'invalid',
            'cr_protection_action' => 'challenge',
        ]));
    }

    public function test_risk_score_operators(): void
    {
        $rule = [
            'match_mode' => 'any',
            'conditions' => [
                ['param' => 'cr_risk_score', 'op' => '>=', 'value' => 80],
            ],
        ];

        $this->assertTrue(AudienceRuleEvaluator::matches($rule, ['cr_risk_score' => 90]));
        $this->assertFalse(AudienceRuleEvaluator::matches($rule, ['cr_risk_score' => 40]));
    }

    public function test_repeat_click_count_and_journey_actions(): void
    {
        $repeatRule = [
            'match_mode' => 'all',
            'conditions' => [
                ['param' => 'cr_repeat_click_count', 'op' => '>', 'value' => 3],
            ],
        ];
        $this->assertFalse(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 3]));
        $this->assertTrue(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 4]));
        $this->assertTrue(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 7]));
        $this->assertFalse(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 2]));

        $actionRule = [
            'match_mode' => 'any',
            'conditions' => [
                ['param' => 'cr_action', 'op' => 'in', 'value' => ['cta_click', 'tel_click', 'checkout']],
            ],
        ];
        $this->assertTrue(AudienceRuleEvaluator::matches($actionRule, [
            'cr_actions' => ['page_view', 'tel_click'],
        ]));
        $this->assertFalse(AudienceRuleEvaluator::matches($actionRule, [
            'cr_actions' => ['page_view', 'exit'],
        ]));
    }

    public function test_repeat_clicks_threshold_is_strictly_above(): void
    {
        $rule = [
            'match_mode' => 'any',
            'conditions' => [
                ['param' => 'cr_repeat_click_count', 'op' => '>=', 'value' => 3],
            ],
        ];
        $normalized = AudienceRuleSchema::normalize($rule);
        $this->assertSame('>', $normalized['rule']['conditions'][0]['op']);
        $this->assertSame(3, $normalized['rule']['conditions'][0]['value']);

        // Limit 3 → only 4+ (above the limit), never 1–3.
        $this->assertFalse(AudienceRuleEvaluator::matches($rule, ['cr_repeat_click_count' => 1]));
        $this->assertFalse(AudienceRuleEvaluator::matches($rule, ['cr_repeat_click_count' => 2]));
        $this->assertFalse(AudienceRuleEvaluator::matches($rule, ['cr_repeat_click_count' => 3]));
        $this->assertTrue(AudienceRuleEvaluator::matches($rule, ['cr_repeat_click_count' => 4]));
        $this->assertTrue(AudienceRuleEvaluator::matches($rule, ['cr_repeat_click_count' => 7]));
        $this->assertStringContainsString('Repeat clicks > 3', AudienceRuleSchema::naturalLanguageSummary($rule));
    }

    public function test_natural_language_summary(): void
    {
        $summary = AudienceRuleSchema::naturalLanguageSummary(AudienceRuleSchema::defaultPreset());
        $this->assertStringContainsString('ANY', $summary);
        $this->assertStringContainsString('Traffic verdict', $summary);
        $this->assertStringContainsString('Protection action', $summary);
    }

    public function test_repeat_clicks_invalid_string_coerces_to_threshold_one(): void
    {
        $bad = [
            'match_mode' => 'any',
            'conditions' => [
                ['param' => 'cr_repeat_click_count', 'op' => '>', 'value' => 'invalid'],
            ],
        ];
        $this->assertTrue(AudienceRuleSchema::needsNumericCoercion($bad));

        $normalized = AudienceRuleSchema::normalize($bad);
        $this->assertTrue($normalized['ok']);
        $this->assertSame(1, $normalized['rule']['conditions'][0]['value']);
        $this->assertSame('>', $normalized['rule']['conditions'][0]['op']);
        $this->assertStringContainsString('Repeat clicks > 1', AudienceRuleSchema::naturalLanguageSummary($bad));

        $this->assertTrue(AudienceRuleEvaluator::matches($bad, ['cr_repeat_click_count' => 3]));
        $this->assertFalse(AudienceRuleEvaluator::matches($bad, ['cr_repeat_click_count' => 1]));
    }
}
