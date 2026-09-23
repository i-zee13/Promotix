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
                ['param' => 'cr_repeat_click_count', 'op' => '>=', 'value' => 4],
            ],
        ];
        $this->assertTrue(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 4]));
        $this->assertTrue(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 7]));
        $this->assertFalse(AudienceRuleEvaluator::matches($repeatRule, ['cr_repeat_click_count' => 3]));

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

    public function test_natural_language_summary(): void
    {
        $summary = AudienceRuleSchema::naturalLanguageSummary(AudienceRuleSchema::defaultPreset());
        $this->assertStringContainsString('ANY', $summary);
        $this->assertStringContainsString('Traffic verdict', $summary);
        $this->assertStringContainsString('Protection action', $summary);
    }
}
