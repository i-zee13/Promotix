<?php

namespace App\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupportTicketInbox
{
    /**
     * @param  Collection<int, SupportTicket>  $tickets
     * @return list<array<string, mixed>>
     */
    public static function rows(Collection $tickets, string $hrefRoute): array
    {
        $lastBodies = SupportTicketMessage::query()
            ->select('support_ticket_id', 'body')
            ->whereIn('support_ticket_id', $tickets->pluck('id')->filter())
            ->whereIn('id', function ($query): void {
                $query->selectRaw('max(id)')
                    ->from('support_ticket_messages')
                    ->groupBy('support_ticket_id');
            })
            ->pluck('body', 'support_ticket_id');

        return $tickets->map(function (SupportTicket $item) use ($lastBodies, $hrefRoute) {
            $preview = $lastBodies[$item->id] ?? $item->body ?? '';

            return [
                'id' => $item->id,
                'href' => route($hrefRoute, $item),
                'subject' => $item->subject,
                'title' => Str::limit((string) $item->subject, 22),
                'number' => $item->ticket_number ?: ('#'.$item->id),
                'preview' => Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $preview)), 80),
                'status' => $item->status,
                'when' => ($item->updated_at ?? $item->created_at)?->diffForHumans() ?? '',
                'requester' => $item->requester?->email ?: ($item->owner?->email ?: ''),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function thread(SupportTicket $ticket): array
    {
        $ticket->loadMissing(['messages.user:id,name,email', 'requester:id,name,email']);
        $thread = [];

        if (filled($ticket->body)) {
            $thread[] = [
                'id' => 'opening',
                'body' => $ticket->body,
                'name' => $ticket->requester?->name ?? 'Customer',
                'when' => $ticket->created_at?->diffForHumans() ?? '',
                'is_agent' => false,
            ];
        }

        foreach ($ticket->messages as $message) {
            $thread[] = [
                'id' => $message->id,
                'body' => $message->body,
                'name' => $message->user?->name
                    ?? ($message->is_agent_reply ? 'Support' : ($ticket->requester?->name ?? 'Customer')),
                'when' => $message->created_at?->diffForHumans() ?? '',
                'is_agent' => (bool) $message->is_agent_reply,
            ];
        }

        return $thread;
    }

    public static function canCustomerReply(SupportTicket $ticket): bool
    {
        return ! in_array($ticket->status, ['closed', 'resolved'], true);
    }
}
