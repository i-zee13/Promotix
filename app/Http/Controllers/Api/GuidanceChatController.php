<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\SupportTicket;
use App\Support\GuidanceService;
use App\Support\SupportTicketInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GuidanceChatController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        if (! \App\Support\AdminIntegrationCatalog::guidanceChatbotEnabled()) {
            return response()->json([
                'ok' => false,
                'message' => 'Guidance chatbot is disabled in Super Admin → Integrations.',
            ], 503);
        }

        if (! $request->user()) {
            return response()->json([
                'ok' => false,
                'message' => 'Sign in to use Guidance chatbot.',
            ], 401);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'department' => ['nullable', 'string', 'max:64'],
            'session_id' => ['nullable', 'integer'],
            'channel' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $session = null;
        if (! empty($data['session_id']) && Schema::hasTable('chat_sessions')) {
            $session = ChatSession::query()
                ->whereKey($data['session_id'])
                ->when($user, fn ($q) => $q->where('user_id', $user->id))
                ->first();
        }

        if (! $session && Schema::hasTable('chat_sessions')) {
            $session = ChatSession::query()->create([
                'channel' => $data['channel'] ?? 'dashboard',
                'user_id' => $user?->id,
                'department' => $data['department'] ?? null,
                'status' => 'open',
                'transcript' => [],
                'last_activity_at' => now(),
            ]);
        }

        $result = GuidanceService::answer($data['message'], $data['department'] ?? null);
        $result = $this->utf8SafeResult($result);

        if ($session) {
            try {
                $transcript = is_array($session->transcript) ? $session->transcript : [];
                $transcript[] = ['from' => 'user', 'text' => $this->utf8Safe((string) $data['message']), 'at' => now()->toIso8601String()];
                $transcript[] = [
                    'from' => 'agent',
                    'text' => $this->utf8Safe((string) ($result['answer'] ?? '')),
                    'at' => now()->toIso8601String(),
                    'meta' => $this->utf8SafeResult([
                        'title' => $result['title'] ?? null,
                        'related_page' => $result['related_page'] ?? null,
                        'confidence' => $result['confidence'] ?? null,
                        'source' => $result['source'] ?? null,
                        'department' => $result['department'] ?? null,
                    ]),
                ];
                $session->transcript = $transcript;
                $session->last_activity_at = now();
                $session->department = $data['department'] ?? ($result['department'] ?? $session->department);
                $session->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'ok' => true,
            'session_id' => $session?->id,
            'ux_delay_ms' => 500,
            'department' => $result['department'] ?? null,
            'source' => $result['source'] ?? null,
            ...$result,
        ]);
    }

    public function createTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'department' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'in:low,normal,high,emergency,urgent'],
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $priority = $data['priority'] ?? 'normal';
        if ($priority === 'emergency') {
            $priority = 'urgent';
        }

        $ticket = new SupportTicket([
            'user_id' => $user->id,
            'requester_id' => $user->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'open',
            'priority' => $priority,
            'category' => $data['department'] ?? 'support',
        ]);

        if (Schema::hasColumn('support_tickets', 'department')) {
            $ticket->department = $data['department'] ?? 'support';
        }
        if (Schema::hasColumn('support_tickets', 'source')) {
            $ticket->source = 'ai_live_agent';
        }
        if (Schema::hasColumn('support_tickets', 'ticket_number')) {
            $ticket->ticket_number = $this->nextTicketNumber();
        }

        $session = null;
        if (! empty($data['session_id']) && Schema::hasTable('chat_sessions')) {
            $session = ChatSession::query()->whereKey($data['session_id'])->where('user_id', $user->id)->first();
            if ($session && Schema::hasColumn('support_tickets', 'context')) {
                $ticket->context = [
                    'chat_session_id' => $session->id,
                    'transcript' => $session->transcript,
                ];
            }
        }

        $ticket->save();

        if ($session) {
            $session->ticket_id = $ticket->id;
            $session->status = 'ticketed';
            $session->save();
        }

        return response()->json([
            'ok' => true,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number ?? ('TKT-'.$ticket->id),
        ]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->with(['requester:id,name,email', 'owner:id,name,email'])
            ->latest('updated_at')
            ->limit(40)
            ->get();

        return response()->json([
            'ok' => true,
            'tickets' => collect(SupportTicketInbox::rows($tickets, 'support-system.show'))
                ->map(fn (array $row) => [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'subject' => $row['subject'],
                    'number' => $row['number'],
                    'status' => $row['status'],
                    'href' => $row['href'],
                ])
                ->values(),
        ]);
    }

    public function ticket(Request $request, SupportTicket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $ticket->user_id === $user->id, 403);

        return response()->json([
            'ok' => true,
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'number' => $ticket->ticket_number ?: ('#'.$ticket->id),
                'status' => $ticket->status,
                'can_reply' => SupportTicketInbox::canCustomerReply($ticket),
                'messages' => SupportTicketInbox::thread($ticket),
            ],
        ]);
    }

    public function replyTicket(Request $request, SupportTicket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $ticket->user_id === $user->id, 403);
        abort_unless(SupportTicketInbox::canCustomerReply($ticket), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket->messages()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_agent_reply' => false,
        ]);
        $ticket->forceFill(['status' => 'open'])->save();

        return response()->json([
            'ok' => true,
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'number' => $ticket->ticket_number ?: ('#'.$ticket->id),
                'status' => $ticket->status,
                'can_reply' => SupportTicketInbox::canCustomerReply($ticket),
                'messages' => SupportTicketInbox::thread($ticket->fresh()),
            ],
        ]);
    }

    private function nextTicketNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'TKT-'.$year.'-';
        $latest = SupportTicket::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('ticket_number');
        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function utf8SafeResult(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = $this->utf8Safe($value);
            } elseif (is_array($value)) {
                $payload[$key] = $this->utf8SafeResult($value);
            }
        }

        return $payload;
    }

    private function utf8Safe(string $value): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (! is_string($clean) || $clean === '') {
            $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return is_string($clean) ? $clean : '';
    }
}
