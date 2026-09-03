<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTicketsInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_system_uses_chat_inbox_not_table(): void
    {
        $admin = $this->superAdmin();
        $customer = User::factory()->create(['email' => 'cust-inbox@example.com']);

        $ticket = SupportTicket::query()->create([
            'user_id' => $customer->id,
            'requester_id' => $customer->id,
            'assigned_to_id' => $admin->id,
            'subject' => 'Tag not tracking',
            'body' => 'Visits are missing.',
            'status' => 'assigned',
            'priority' => 'high',
        ]);

        $this->actingAs($admin)
            ->get(route('super-admin.tickets.index'))
            ->assertOk()
            ->assertSee('Assigned')
            ->assertSee('Tag not tracking')
            ->assertSee('ticket-inbox', false);

        $this->actingAs($admin)
            ->get(route('super-admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Visits are missing.')
            ->assertSee('Reply as support');
    }

    public function test_agent_reply_stays_on_ticket_and_is_marked_as_support(): void
    {
        $admin = $this->superAdmin();
        $customer = User::factory()->create(['email' => 'cust-reply@example.com']);

        $ticket = SupportTicket::query()->create([
            'user_id' => $customer->id,
            'requester_id' => $customer->id,
            'assigned_to_id' => $admin->id,
            'subject' => 'Need help',
            'body' => 'First message',
            'status' => 'assigned',
            'priority' => 'medium',
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.tickets.reply', $ticket), [
                'body' => 'We are looking into this.',
            ])
            ->assertRedirect(route('super-admin.tickets.show', $ticket));

        $message = SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->first();
        $this->assertNotNull($message);
        $this->assertTrue((bool) $message->is_agent_reply);
        $this->assertSame($admin->id, $message->user_id);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
