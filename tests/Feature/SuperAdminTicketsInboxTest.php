<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
            ->assertRedirect(route('super-admin.tickets.queue'));

        $this->actingAs($admin)
            ->get(route('super-admin.tickets.index', ['inbox' => 1]))
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

    public function test_assignment_board_is_available_alongside_chat_inbox(): void
    {
        $admin = $this->superAdmin();
        $customer = User::factory()->create(['email' => 'cust-queue@example.com']);

        $ticket = SupportTicket::query()->create([
            'user_id' => $customer->id,
            'requester_id' => $customer->id,
            'subject' => 'Assign this ticket',
            'body' => 'Needs an agent.',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $this->actingAs($admin)
            ->get(route('super-admin.tickets.queue'))
            ->assertOk()
            ->assertSee('Assignment board')
            ->assertSee('Assign this ticket')
            ->assertSee('Chat inbox');

        $this->actingAs($admin)
            ->from(route('super-admin.tickets.queue.show', $ticket))
            ->post(route('super-admin.tickets.assign', $ticket), [
                'assigned_to_id' => $admin->id,
                'return' => 'queue',
            ])
            ->assertRedirect(route('super-admin.tickets.queue.show', $ticket));
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

    public function test_team_department_agent_can_claim_unassigned_balance_ticket(): void
    {
        $agent = $this->supportAgent('support');
        $customer = User::factory()->create(['email' => 'cust-claim@example.com']);

        $attrs = [
            'user_id' => $customer->id,
            'requester_id' => $customer->id,
            'subject' => 'Balance ticket',
            'body' => 'Please claim me.',
            'status' => 'open',
            'priority' => 'high',
        ];
        if (Schema::hasColumn('support_tickets', 'department')) {
            $attrs['department'] = 'support';
        }

        $ticket = SupportTicket::query()->create($attrs);

        $this->actingAs($agent)
            ->get(route('super-admin.tickets.queue'))
            ->assertOk()
            ->assertSee('Balance ticket')
            ->assertSee('Assign to me');

        $this->actingAs($agent)
            ->post(route('super-admin.tickets.claim', $ticket), ['return' => 'queue'])
            ->assertRedirect(route('super-admin.tickets.queue.show', $ticket));

        $ticket->refresh();
        $this->assertSame($agent->id, (int) $ticket->assigned_to_id);
        $this->assertSame('assigned', $ticket->status);
    }

    public function test_team_agent_appears_in_assignee_list_for_super_admin(): void
    {
        $admin = $this->superAdmin();
        $agent = $this->supportAgent('support');
        $customer = User::factory()->create(['email' => 'cust-assignee@example.com']);

        $attrs = [
            'user_id' => $customer->id,
            'requester_id' => $customer->id,
            'subject' => 'Pick team agent',
            'body' => 'Assign from balance.',
            'status' => 'open',
            'priority' => 'normal',
        ];
        if (Schema::hasColumn('support_tickets', 'department')) {
            $attrs['department'] = 'support';
        }
        $ticket = SupportTicket::query()->create($attrs);

        $this->actingAs($admin)
            ->get(route('super-admin.tickets.queue.show', $ticket))
            ->assertOk()
            ->assertSee($agent->email);
    }

    public function test_team_agent_cannot_open_unrelated_department_ticket(): void
    {
        $agent = $this->supportAgent('support');
        $customer = User::factory()->create(['email' => 'cust-billing@example.com']);

        if (! Schema::hasColumn('support_tickets', 'department')) {
            $this->markTestSkipped('department column required');
        }

        $ticket = SupportTicket::query()->create([
            'user_id' => $customer->id,
            'requester_id' => $customer->id,
            'subject' => 'Billing only',
            'body' => 'Invoice question',
            'status' => 'open',
            'priority' => 'normal',
            'department' => 'billing',
            'assigned_to_id' => null,
        ]);

        $this->actingAs($agent)
            ->get(route('super-admin.tickets.queue.show', $ticket))
            ->assertForbidden();
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function supportAgent(string $departmentSlug): User
    {
        $dept = Department::query()->firstOrCreate(
            ['slug' => $departmentSlug],
            ['name' => ucfirst($departmentSlug), 'sort_order' => 10]
        );

        $team = Team::query()->firstOrCreate(
            ['slug' => $departmentSlug.'-desk'],
            [
                'name' => ucfirst($departmentSlug).' Desk',
                'department_id' => $dept->id,
                'is_active' => true,
            ]
        );

        $agent = User::factory()->create([
            'is_super_admin' => false,
            'is_admin' => false,
            'email_verified_at' => now(),
            'email' => $departmentSlug.'.agent.'.uniqid().'@example.com',
        ]);

        $team->members()->syncWithoutDetaching([$agent->id]);

        return $agent;
    }
}
