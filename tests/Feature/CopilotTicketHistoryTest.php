<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopilotTicketHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_copilot_lists_only_the_users_tickets(): void
    {
        $user = $this->portalUser();
        $other = $this->portalUser(['email' => 'other-tickets@example.com']);

        $mine = SupportTicket::query()->create([
            'user_id' => $user->id,
            'requester_id' => $user->id,
            'subject' => 'Website tag not firing',
            'body' => 'Please check the tag.',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        SupportTicket::query()->create([
            'user_id' => $other->id,
            'requester_id' => $other->id,
            'subject' => 'Secret other ticket',
            'body' => 'Should not appear.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->getJson('/api/admin/guidance/tickets')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.id', $mine->id)
            ->assertJsonMissing(['subject' => 'Secret other ticket']);
    }

    public function test_copilot_ticket_reply_is_customer_not_agent(): void
    {
        $user = $this->portalUser();
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'requester_id' => $user->id,
            'subject' => 'Follow up',
            'body' => 'Opening',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->postJson('/api/admin/guidance/tickets/'.$ticket->id.'/reply', [
                'body' => 'Here is more detail.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $message = SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->first();
        $this->assertNotNull($message);
        $this->assertFalse((bool) $message->is_agent_reply);
        $this->assertSame('Here is more detail.', $message->body);
    }

    public function test_copilot_cannot_open_someone_elses_ticket(): void
    {
        $owner = $this->portalUser(['email' => 'owner-copilot@example.com']);
        $intruder = $this->portalUser(['email' => 'intruder-copilot@example.com']);
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'requester_id' => $owner->id,
            'subject' => 'Private',
            'body' => 'Hidden',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($intruder)
            ->getJson('/api/admin/guidance/tickets/'.$ticket->id)
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function portalUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'is_super_admin' => true,
            'is_admin' => false,
            'email_verified_at' => now(),
        ], $overrides));
    }
}
