<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyBehaviorEvents extends Command
{
    protected $signature = 'clickronix:verify-events
        {--domain= : Domain id (omit to loop active domains)}
        {--domains-limit=25 : When no --domain, how many active domains to scan (id desc)}
        {--session= : Session id}
        {--ip= : Match via visit_session_recordings.ip}
        {--type= : Comma-separated event_type filter (e.g. phone_click,cta_click,form_submit)}
        {--limit=50 : Max rows per domain}
        {--hours=72 : Lookback hours}';

    protected $description = 'Verify auto-detected behavior events (visit_behavior_events) for SSH/tinker checks';

    public function handle(): int
    {
        if (! Schema::hasTable('visit_behavior_events')) {
            $this->error('Table visit_behavior_events does not exist.');

            return self::FAILURE;
        }

        $sessionId = trim((string) $this->option('session'));
        $ip = trim((string) $this->option('ip'));
        $limit = max(1, min(500, (int) $this->option('limit')));
        $hours = max(1, min(720, (int) $this->option('hours')));
        $domainsLimit = max(1, min(500, (int) $this->option('domains-limit')));
        $types = array_values(array_filter(array_map(
            static fn (string $t): string => strtolower(trim($t)),
            explode(',', (string) $this->option('type'))
        )));

        $domainIds = $this->resolveDomainIds($domainsLimit);
        if ($domainIds === []) {
            $this->warn('No active domains found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Scanning %d domain(s) | last %dh | session=%s ip=%s types=%s | limit=%d/domain',
            count($domainIds),
            $hours,
            $sessionId !== '' ? $sessionId : 'any',
            $ip !== '' ? $ip : 'any',
            $types !== [] ? implode(',', $types) : 'any',
            $limit
        ));

        $anyRows = false;
        foreach ($domainIds as $domain) {
            $hostname = is_array($domain) ? (string) ($domain['hostname'] ?? '') : (string) ($domain->hostname ?? '');
            $id = is_array($domain) ? (int) $domain['id'] : (int) $domain->id;

            $rows = $this->fetchRows($id, $sessionId, $ip, $types, $hours, $limit);
            $label = $hostname !== '' ? "{$id} ({$hostname})" : (string) $id;

            $this->newLine();
            $this->line("── domain {$label} ──");

            if ($rows->isEmpty()) {
                $this->line('  (no events)');

                continue;
            }

            $anyRows = true;
            $summary = $rows->groupBy('event_type')->map->count()->sortDesc();
            $this->table(
                ['event_type', 'count'],
                $summary->map(fn ($c, $t) => [$t, $c])->values()->all()
            );
            $this->table(
                ['id', 'session', 'type', 'text', 'href/tel', 'form', 'occurred_at'],
                $rows->map(static function ($r): array {
                    $href = (string) ($r->tel_number ?: $r->href ?: '');
                    if (strlen($href) > 40) {
                        $href = substr($href, 0, 37).'...';
                    }
                    $text = (string) ($r->element_text ?: '');
                    if (strlen($text) > 28) {
                        $text = substr($text, 0, 25).'...';
                    }

                    return [
                        $r->id,
                        substr((string) $r->session_id, 0, 18),
                        $r->event_type,
                        $text,
                        $href,
                        (string) ($r->form_id ?: ''),
                        (string) $r->occurred_at,
                    ];
                })->all()
            );
        }

        if (! $anyRows) {
            $this->newLine();
            $this->warn('No rows on scanned domains. Check Session Recording ON + Pixel Guard + --hours.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<object{id:int,hostname?:string}|array{id:int,hostname?:string}>
     */
    private function resolveDomainIds(int $domainsLimit): array
    {
        $single = $this->option('domain');
        if ($single !== null && trim((string) $single) !== '') {
            $id = (int) $single;
            if ($id <= 0) {
                return [];
            }
            if (! Schema::hasTable('domains')) {
                return [['id' => $id, 'hostname' => '']];
            }
            $row = DB::table('domains')->where('id', $id)->first(['id', 'hostname']);

            return $row ? [$row] : [['id' => $id, 'hostname' => '']];
        }

        if (! Schema::hasTable('domains')) {
            $this->error('domains table missing; pass --domain=ID');

            return [];
        }

        $q = DB::table('domains')->select(['id', 'hostname', 'status'])->orderByDesc('id');
        if (Schema::hasColumn('domains', 'status')) {
            // Domain statuses are pending|connected|disabled (not "active").
            $q->whereIn('status', ['connected', 'pending']);
        }
        $rows = $q->limit($domainsLimit)->get();

        if ($rows->isEmpty() && Schema::hasColumn('domains', 'status')) {
            // Fallback: any non-disabled domain.
            $rows = DB::table('domains')
                ->select(['id', 'hostname', 'status'])
                ->where(function ($inner): void {
                    $inner->whereNull('status')->orWhere('status', '!=', 'disabled');
                })
                ->orderByDesc('id')
                ->limit($domainsLimit)
                ->get();
        }

        if ($rows->isEmpty()) {
            // Last resort: newest domains regardless of status.
            $rows = DB::table('domains')
                ->select(['id', 'hostname'])
                ->orderByDesc('id')
                ->limit($domainsLimit)
                ->get();
        }

        return $rows->all();
    }

    /**
     * @param  list<string>  $types
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchRows(
        int $domainId,
        string $sessionId,
        string $ip,
        array $types,
        int $hours,
        int $limit,
    ) {
        $since = now('UTC')->subHours($hours);

        $q = DB::table('visit_behavior_events as e')
            ->where('e.domain_id', $domainId)
            ->where('e.occurred_at', '>=', $since)
            ->orderByDesc('e.occurred_at')
            ->limit($limit);

        if ($sessionId !== '') {
            $q->where('e.session_id', $sessionId);
        }
        if ($types !== []) {
            $q->whereIn('e.event_type', $types);
        }
        if ($ip !== '' && Schema::hasTable('visit_session_recordings')) {
            $q->leftJoin('visit_session_recordings as r', 'r.id', '=', 'e.recording_id')
                ->where('r.ip', $ip)
                ->select('e.*');
        } else {
            $q->select('e.*');
        }

        return $q->get();
    }
}
