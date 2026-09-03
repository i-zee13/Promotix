<?php

namespace Tests\Unit;

use App\Support\ClickronixKnowledgeBank;
use App\Support\GuidanceService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClickronixKnowledgeBankTest extends TestCase
{
    #[Test]
    public function it_loads_local_knowledge_bank_without_openai(): void
    {
        $this->assertTrue(ClickronixKnowledgeBank::isAvailable());
        $this->assertGreaterThan(50, count(ClickronixKnowledgeBank::entries()));
    }

    #[Test]
    public function it_answers_tracking_accuracy_from_faq(): void
    {
        $result = GuidanceService::answer('What does Tracking Accuracy mean?');

        $this->assertNotSame('', $result['answer']);
        $this->assertGreaterThan(0.2, $result['confidence']);
        $this->assertStringContainsStringIgnoringCase('tracking', $result['answer']);
    }

    #[Test]
    public function it_answers_campaign_questions(): void
    {
        $result = GuidanceService::answer('i want to know about the campaign');

        $this->assertGreaterThan(0.16, $result['confidence']);
        $this->assertStringContainsStringIgnoringCase('Campaign Performance', $result['answer']);
    }

    #[Test]
    public function it_routes_live_agent_requests(): void
    {
        $result = GuidanceService::answer('I want to talk to a human agent about Google Ads');

        $this->assertTrue($result['offer_ticket']);
        $this->assertStringContainsStringIgnoringCase('Google Ads Integration', $result['answer']);
        $this->assertTrue(($result['confidence'] ?? 0) >= 0.9);
    }

    #[Test]
    public function it_answers_website_connection_without_dumping_gclid_docs(): void
    {
        $result = GuidanceService::answer('hi i have an issue regarding website connection');

        $this->assertStringContainsStringIgnoringCase('Domains', $result['answer']);
        $this->assertStringContainsStringIgnoringCase('Tag Manager', $result['answer']);
        $this->assertStringContainsStringIgnoringCase('Analytics', $result['answer']);
        $this->assertStringNotContainsStringIgnoringCase('ADS_GCLID_DUP', $result['answer']);
        $this->assertStringNotContainsStringIgnoringCase('BY. DUPLICATE', $result['answer']);
        $this->assertDoesNotMatchRegularExpression('/^Customer:/m', $result['answer']);
        $this->assertLessThan(800, strlen($result['answer']));
    }

    #[Test]
    public function it_answers_tag_manager_as_website_tag_not_paid_ads(): void
    {
        $result = GuidanceService::answer('tag manager tracking not working');

        $this->assertStringContainsStringIgnoringCase('Tag Manager', $result['answer']);
        $this->assertStringContainsStringIgnoringCase('website', $result['answer']);
        $this->assertStringContainsStringIgnoringCase('Analytics', $result['answer']);
        $this->assertLessThan(800, strlen($result['answer']));
    }
}
