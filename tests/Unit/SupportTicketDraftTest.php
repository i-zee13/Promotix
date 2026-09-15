<?php

namespace Tests\Unit;

use App\Support\SupportTicketDraft;
use PHPUnit\Framework\TestCase;

class SupportTicketDraftTest extends TestCase
{
    public function test_rejects_hi(): void
    {
        $r = SupportTicketDraft::validate('hi', 'hi');
        $this->assertFalse($r['ok']);
    }

    public function test_rejects_short_body(): void
    {
        $r = SupportTicketDraft::validate('Domain not tracking', 'please help');
        $this->assertFalse($r['ok']);
    }

    public function test_accepts_detailed_issue(): void
    {
        $r = SupportTicketDraft::validate(
            'Domain tag not detecting visits',
            'I installed the Direct tag on example.com yesterday, cleared cache, and Verify still says Not detected after opening the homepage.'
        );
        $this->assertTrue($r['ok'], $r['message'] ?? '');
    }
}
