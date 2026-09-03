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
    public function it_routes_live_agent_requests(): void
    {
        $result = GuidanceService::answer('I want to talk to a human agent about Google Ads');

        $this->assertTrue($result['offer_ticket']);
        $this->assertStringContainsStringIgnoringCase('Google Ads Integration', $result['answer']);
        $this->assertTrue(($result['confidence'] ?? 0) >= 0.9);
    }
}
