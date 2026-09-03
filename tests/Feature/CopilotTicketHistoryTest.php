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

    public function test_copilot_header_chips_include_existing_tickets(): void
    {
        $user = $this->portalUser();
        SupportTicket::query()->create([
            'user_id' => $user->id,
            'requester_id' => $user->id,
            'subject' => 'campaign related',
            'body' => 'i want to know the camapign',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($user);
        $chips = \App\Support\SupportTicketInbox::copilotChips($user);

        $this->assertNotEmpty($chips);
        $this->assertSame('campaign related', $chips[0]['subject']);
        $this->assertArrayNotHasKey('href', $chips[0]);
        $this->assertNotEmpty($chips[0]['messages']);
        $this->assertSame('i want to know the camapign', $chips[0]['messages'][0]['body']);
        $this->assertFalse($chips[0]['messages'][0]['is_agent']);
    }

    public function test_copilot_ticket_thread_includes_opening_and_admin_replies(): void
    {
        $user = $this->portalUser();
        $agent = $this->portalUser(['email' => 'agent-reply@example.com', 'name' => 'Support Agent']);
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'requester_id' => $user->id,
            'subject' => 'campaign related',
            'body' => 'Need campaign help.',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'We checked your campaign settings.',
            'is_agent_reply' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/admin/guidance/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ticket.subject', 'campaign related')
            ->assertJsonPath('ticket.messages.0.body', 'Need campaign help.')
            ->assertJsonPath('ticket.messages.0.is_agent', false)
            ->assertJsonPath('ticket.messages.1.body', 'We checked your campaign settings.')
            ->assertJsonPath('ticket.messages.1.is_agent', true);

        $chips = \App\Support\SupportTicketInbox::copilotChips($user);
        $this->assertSame('We checked your campaign settings.', $chips[0]['messages'][1]['body']);
        $this->assertTrue($chips[0]['messages'][1]['is_agent']);
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
