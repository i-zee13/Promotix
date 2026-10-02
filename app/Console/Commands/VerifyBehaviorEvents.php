<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyBehaviorEvents extends Command
{
    protected $signature = 'clickronix:verify-events
        {--domain= : Domain id}
        {--session= : Session id}
        {--ip= : Match via visit_session_recordings.ip}
        {--type= : Comma-separated event_type filter (e.g. phone_click,cta_click,form_submit)}
        {--limit=50 : Max rows}
        {--hours=72 : Lookback hours}';

    protected $description = 'Verify auto-detected behavior events (visit_behavior_events) for SSH/tinker checks';

    public function handle(): int
    {
        if (! Schema::hasTable('visit_behavior_events')) {
            $this->error('Table visit_behavior_events does not exist.');

            return self::FAILURE;
        }

        $domainId = $this->option('domain') !== null && $this->option('domain') !== ''
            ? (int) $this->option('domain')
            : null;
        $sessionId = trim((string) $this->option('session'));
        $ip = trim((string) $this->option('ip'));
        $limit = max(1, min(500, (int) $this->option('limit')));
        $hours = max(1, min(720, (int) $this->option('hours')));
        $types = array_values(array_filter(array_map(
            static fn (string $t): string => strtolower(trim($t)),
            explode(',', (string) $this->option('type'))
        )));

        $since = now('UTC')->subHours($hours);

        $q = DB::table('visit_behavior_events as e')
            ->where('e.occurred_at', '>=', $since)
            ->orderByDesc('e.occurred_at')
            ->limit($limit);

        if ($domainId) {
            $q->where('e.domain_id', $domainId);
        }
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

        $rows = $q->get();

        $this->info(sprintf(
            'visit_behavior_events | last %dh | domain=%s session=%s ip=%s types=%s | %d row(s)',
            $hours,
            $domainId ?: 'any',
            $sessionId !== '' ? $sessionId : 'any',
            $ip !== '' ? $ip : 'any',
            $types !== [] ? implode(',', $types) : 'any',
            $rows->count()
        ));

        if ($rows->isEmpty()) {
            $this->warn('No rows. Check Session Recording ON + Pixel Guard, domain_id, and lookback --hours.');

            return self::SUCCESS;
        }

        $summary = $rows->groupBy('event_type')->map->count()->sortDesc();
        $this->table(['event_type', 'count'], $summary->map(fn ($c, $t) => [$t, $c])->values()->all());

        $this->table(
            ['id', 'domain', 'session', 'type', 'text', 'href/tel', 'form', 'occurred_at'],
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
                    $r->domain_id,
                    substr((string) $r->session_id, 0, 18),
                    $r->event_type,
                    $text,
                    $href,
                    (string) ($r->form_id ?: ''),
                    (string) $r->occurred_at,
                ];
            })->all()
        );

        return self::SUCCESS;
    }
}
