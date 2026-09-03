<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupportInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_support_page_redirects_into_copilot(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)
            ->get('/admin/support-system')
            ->assertRedirect(route('dashboard', ['open_copilot' => 1]));
    }

    public function test_replies_stay_on_the_same_ticket_and_open_copilot(): void
    {
        $user = $this->portalUser();

        $create = $this->actingAs($user)->post('/admin/support-system', [
            'subject' => 'Billing question',
            'body' => 'Invoice looks wrong.',
            'priority' => 'medium',
        ]);

        $ticket = SupportTicket::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($ticket);
        $create->assertRedirect(route('dashboard', ['open_copilot' => 1, 'ticket' => $ticket->id]));

        $this->actingAs($user)
            ->post(route('support-system.reply', $ticket), [
                'body' => 'Here is the extra detail.',
            ])
            ->assertRedirect(route('dashboard', ['open_copilot' => 1, 'ticket' => $ticket->id]));

        $this->assertSame(1, SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->count());
        $this->assertFalse((bool) $ticket->messages()->first()?->is_agent_reply);
    }

    public function test_customer_cannot_open_another_users_ticket(): void
    {
        $owner = $this->portalUser(['email' => 'owner-inbox@example.com']);
        $intruder = $this->portalUser(['email' => 'intruder-inbox@example.com']);

        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'requester_id' => $owner->id,
            'subject' => 'Private ticket',
            'body' => 'Do not show this.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($intruder)
            ->get(route('support-system.show', $ticket))
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
