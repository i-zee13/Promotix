<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClientIp;
use App\Models\Domain;
use App\Models\SupportTicket;
use App\Models\UserApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public lead → support ticket webhook (WordPress / external forms).
 *
 * POST /api/v1/ticket
 * Auth: domainKey + secret_key (or Bearer pmx_… API key)
 * No Clickronix login cookie required.
 */
class PublicTicketController extends Controller
{
    use ResolvesClientIp;

    public function store(Request $request): Response
    {
        if ($request->isMethod('options')) {
            return $this->cors($request, response()->noContent());
        }

        $data = $request->validate([
            'domainKey' => ['nullable', 'string', 'max:64'],
            'domain_key' => ['nullable', 'string', 'max:64'],
            'secret_key' => ['nullable', 'string', 'max:128'],
            'authentication_key' => ['nullable', 'string', 'max:128'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'message' => ['nullable', 'string', 'max:10000'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'in:low,normal,medium,high,emergency,urgent'],
        ]);

        $domainKey = trim((string) ($data['domainKey'] ?? $data['domain_key'] ?? ''));
        $secret = trim((string) (
            $data['secret_key']
            ?? $request->header('X-Promotix-Secret')
            ?? $request->header('X-Clickronix-Secret')
            ?? ''
        ));
        $authKey = trim((string) (
            $data['authentication_key']
            ?? $request->header('X-Promotix-Auth')
            ?? ''
        ));
        $bearer = $this->bearerToken($request);

        $ownerId = null;
        $domain = null;

        if ($bearer !== '' && str_starts_with($bearer, 'pmx_')) {
            $apiUserId = $this->userIdFromApiKey($bearer);
            if (! $apiUserId) {
                return $this->cors($request, response()->json([
                    'ok' => false,
                    'message' => 'Invalid API key.',
                ], 401));
            }
            $ownerId = $apiUserId;
            if ($domainKey !== '') {
                $domain = Domain::query()
                    ->where('domain_key', $domainKey)
                    ->where('user_id', $ownerId)
                    ->first();
                if (! $domain) {
                    return $this->cors($request, response()->json([
                        'ok' => false,
                        'message' => 'domainKey does not belong to this API key.',
                    ], 403));
                }
            }
        } else {
            if ($domainKey === '') {
                return $this->cors($request, response()->json([
                    'ok' => false,
                    'message' => 'domainKey is required (or send Authorization: Bearer pmx_…).',
                ], 422));
            }
            if ($secret === '' && $authKey === '') {
                return $this->cors($request, response()->json([
                    'ok' => false,
                    'message' => 'secret_key (or authentication_key) is required for public ticket posts.',
                ], 401));
            }

            $domain = Domain::query()->where('domain_key', $domainKey)->first();
            if (! $domain) {
                return $this->cors($request, response()->json([
                    'ok' => false,
                    'message' => 'Unknown domainKey.',
                ], 404));
            }

            $domainSecret = trim((string) ($domain->secret_key ?? ''));
            $domainAuth = trim((string) ($domain->authentication_key ?? ''));
            $okSecret = $secret !== '' && $domainSecret !== '' && hash_equals($domainSecret, $secret);
            $okAuth = $authKey !== '' && $domainAuth !== '' && hash_equals($domainAuth, $authKey);
            if (! $okSecret && ! $okAuth) {
                return $this->cors($request, response()->json([
                    'ok' => false,
                    'message' => 'Invalid secret_key / authentication_key.',
                ], 403));
            }

            $ownerId = (int) $domain->user_id;
        }

        if (! $ownerId) {
            return $this->cors($request, response()->json([
                'ok' => false,
                'message' => 'Could not resolve workspace owner for this domain.',
            ], 422));
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? $data['message'] ?? ''));

        if ($subject === '' && $name !== '') {
            $subject = 'Lead from '.$name;
        } elseif ($subject === '' && $email !== '') {
            $subject = 'Lead from '.$email;
        }
        if ($subject === '') {
            $subject = 'Website form lead';
        }

        if ($body === '') {
            $parts = array_filter([
                $name !== '' ? 'Name: '.$name : null,
                $email !== '' ? 'Email: '.$email : null,
                $phone !== '' ? 'Phone: '.$phone : null,
            ]);
            $body = $parts !== [] ? implode("\n", $parts) : 'No message provided.';
        } elseif ($name !== '' || $email !== '' || $phone !== '') {
            $meta = array_filter([
                $name !== '' ? 'Name: '.$name : null,
                $email !== '' ? 'Email: '.$email : null,
                $phone !== '' ? 'Phone: '.$phone : null,
            ]);
            if ($meta !== []) {
                $body = implode("\n", $meta)."\n\n".$body;
            }
        }

        $priority = $data['priority'] ?? 'normal';
        if (in_array($priority, ['emergency', 'medium'], true)) {
            $priority = $priority === 'emergency' ? 'urgent' : 'normal';
        }

        $ticket = new SupportTicket([
            'user_id' => $ownerId,
            'requester_id' => $ownerId,
            'subject' => mb_substr($subject, 0, 255),
            'body' => mb_substr($body, 0, 10000),
            'status' => 'open',
            'priority' => $priority,
            'category' => $data['department'] ?? 'support',
            'sla_due_at' => now()->addHours(24),
        ]);

        if (Schema::hasColumn('support_tickets', 'department')) {
            $ticket->department = $data['department'] ?? 'support';
        }
        if (Schema::hasColumn('support_tickets', 'source')) {
            $ticket->source = 'web_form';
        }
        if (Schema::hasColumn('support_tickets', 'context')) {
            $ticket->context = array_filter([
                'domain_id' => $domain?->id,
                'domain_key' => $domainKey !== '' ? $domainKey : ($domain?->domain_key),
                'hostname' => $domain?->hostname,
                'lead_name' => $name !== '' ? $name : null,
                'lead_email' => $email !== '' ? $email : null,
                'lead_phone' => $phone !== '' ? $phone : null,
                'ip' => $this->clientIp($request),
                'user_agent' => ($ua = trim((string) $request->userAgent())) !== ''
                    ? mb_substr($ua, 0, 255)
                    : null,
            ]);
        }
        if (Schema::hasColumn('support_tickets', 'ticket_number')) {
            $ticket->ticket_number = $this->nextTicketNumber();
        }

        $ticket->save();

        return $this->cors($request, response()->json([
            'ok' => true,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number ?? ('TKT-'.$ticket->id),
        ], 201));
    }

    private function bearerToken(Request $request): string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if (preg_match('/^Bearer\s+(\S+)/i', $header, $m)) {
            return trim($m[1]);
        }

        return trim((string) $request->header('X-Api-Key', ''));
    }

    private function userIdFromApiKey(string $plain): ?int
    {
        if (! Schema::hasTable('user_api_keys')) {
            return null;
        }

        $hash = hash('sha256', $plain);
        $row = UserApiKey::query()->where('token_hash', $hash)->first();
        if (! $row) {
            return null;
        }
        $row->forceFill(['last_used_at' => now()])->save();

        return (int) $row->user_id;
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
}
