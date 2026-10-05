<?php

namespace App\Console\Commands;

use App\Models\DomainDetectionSetting;
use App\Support\DetectionPlanFeatures;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProbeCtaCaptureCommand extends Command
{
    protected $signature = 'clickronix:probe-cta-capture
        {--base= : Base URL (default APP_URL)}
        {--domain= : Single domain id}
        {--limit=50 : Max domains to probe}
        {--timeout=20 : HTTP timeout seconds}
        {--skip-recording : Only hit /ingest/visit (check record_session)}
        {--dry-run : List domains/keys only, no HTTP}';

    protected $description = 'Auto-load domain keys from DB and probe /ingest/visit + /ingest/session-recording (CTA) for each';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');
        $limit = max(1, min(500, (int) $this->option('limit')));
        $timeout = max(3, min(120, (int) $this->option('timeout')));
        $skipRecording = (bool) $this->option('skip-recording');
        $dryRun = (bool) $this->option('dry-run');

        $domains = $this->loadDomains($limit);
        if ($domains === []) {
            $this->warn('No domains with domain_key found.');

            return self::SUCCESS;
        }

        $outDir = storage_path('app/public/exports/cta-probe-'.now('UTC')->format('Ymd-His'));
        if (! $dryRun && ! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $this->info(sprintf(
            'Probing %d domain(s) via %s | out=%s',
            count($domains),
            $base,
            $dryRun ? '(dry-run)' : $outDir
        ));

        $rows = [];
        foreach ($domains as $domain) {
            $id = (int) $domain->id;
            $key = (string) $domain->domain_key;
            $host = (string) ($domain->hostname ?? '');
            $status = (string) ($domain->status ?? '');
            $pageUrl = $this->pageUrlForHost($host);
            $sessionOn = $this->sessionRecordingOn($id, (int) ($domain->user_id ?? 0));

            $this->newLine();
            $this->line("── #{$id} {$host} [{$status}] key=".Str::limit($key, 16, '…')." recording_db=".($sessionOn['db'] ? 'ON' : 'OFF').' plan='.($sessionOn['plan'] ? 'ON' : 'OFF'));

            if ($dryRun) {
                $rows[] = [$id, $host, $status, $sessionOn['db'] ? 'ON' : 'OFF', $sessionOn['plan'] ? 'ON' : 'OFF', 'dry-run', '-', '-'];
                continue;
            }

            $visitPath = "{$outDir}/domain-{$id}-visit.json";
            $recPath = "{$outDir}/domain-{$id}-recording.json";

            $sessionId = 'probe_'.Str::lower(Str::random(12));
            $visitorId = 'probe_vis_'.Str::lower(Str::random(10));

            $visit = $this->postJson("{$base}/ingest/visit", [
                'domainKey' => $key,
                'url' => $pageUrl,
                'path' => '/pricing',
                'title' => 'CTA Probe',
                'session_id' => $sessionId,
                'visitor_id' => $visitorId,
                'referrer' => 'https://www.google.com/',
            ], $timeout);

            file_put_contents($visitPath, json_encode($visit['json'] ?? ['raw' => $visit['body']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $recordSession = (bool) data_get($visit['json'], 'record_session', false);
            $visitId = data_get($visit['json'], 'visit_id');
            $blocked = data_get($visit['json'], 'blocked');
            $http = $visit['status'];

            $recStatus = 'skipped';
            $ctaClicks = '-';
            $recReason = data_get($visit['json'], 'error')
                ?? data_get($visit['json'], 'reason')
                ?? ($recordSession ? '' : 'no_record_session');

            if (! $skipRecording && $recordSession && $http >= 200 && $http < 300) {
                $nowMs = (int) round(microtime(true) * 1000);

                $rec = $this->postJson("{$base}/ingest/session-recording", [
                    'domainKey' => $key,
                    'session_id' => $sessionId,
                    'visitor_id' => $visitorId,
                    'visit_id' => $visitId,
                    'page_url' => $pageUrl,
                    'duration_ms' => 8400,
                    'threat_group' => data_get($visit['json'], 'threat_group'),
                    'events' => [
                        [
                            'type' => 'pageview',
                            't' => 0,
                            'ts' => $nowMs,
                            'page_url' => $pageUrl,
                            'path' => '/pricing',
                            'title' => 'CTA Probe',
                        ],
                        [
                            'type' => 'cta_click',
                            't' => 3200,
                            'ts' => $nowMs + 3200,
                            'href' => '/signup',
                            'element_text' => 'Get Started',
                            'text' => 'Get Started',
                            'element_id' => 'probe-cta',
                            'element_class' => 'btn btn-primary',
                            'tag' => 'A',
                            'link_type' => 'anchor',
                            'page_url' => $pageUrl,
                            'path' => '/pricing',
                            'title' => 'CTA Probe',
                        ],
                        [
                            'type' => 'session_exit',
                            't' => 8400,
                            'ts' => $nowMs + 8400,
                            'page_url' => $pageUrl,
                            'path' => '/pricing',
                        ],
                    ],
                ], $timeout);

                file_put_contents($recPath, json_encode($rec['json'] ?? ['raw' => $rec['body']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $recStatus = (string) ($rec['status'] ?? 'err');
                $ctaClicks = (string) (data_get($rec['json'], 'cta_clicks', '-'));
                if (data_get($rec['json'], 'skipped')) {
                    $recReason = (string) (data_get($rec['json'], 'reason') ?: data_get($rec['json'], 'error') ?: 'skipped');
                } elseif (data_get($rec['json'], 'ok') === true) {
                    $recReason = 'ok';
                } else {
                    $recReason = (string) (data_get($rec['json'], 'error') ?: data_get($rec['json'], 'message') ?: 'fail');
                }
            } elseif ($skipRecording) {
                $recStatus = 'not-run';
                $recReason = 'skip-recording';
            }

            $this->line(sprintf(
                '  visit_http=%s record_session=%s blocked=%s visit_id=%s | recording_http=%s cta_clicks=%s reason=%s',
                $http,
                $recordSession ? 'true' : 'false',
                is_bool($blocked) ? ($blocked ? 'true' : 'false') : (string) $blocked,
                $visitId !== null ? (string) $visitId : '-',
                $recStatus,
                $ctaClicks,
                $recReason !== '' ? $recReason : '-'
            ));

            $rows[] = [
                $id,
                Str::limit($host, 28, '…'),
                $status !== '' ? $status : '-',
                $sessionOn['db'] ? 'ON' : 'OFF',
                $sessionOn['plan'] ? 'ON' : 'OFF',
                $recordSession ? 'true' : 'false',
                $ctaClicks,
                Str::limit($recReason !== '' ? $recReason : '-', 28, '…'),
            ];
        }

        $this->newLine();
        $this->table(
            ['id', 'hostname', 'status', 'db_rec', 'plan_rec', 'record_session', 'cta_clicks', 'result'],
            $rows
        );

        if (! $dryRun) {
            $summaryPath = "{$outDir}/_summary.json";
            file_put_contents($summaryPath, json_encode([
                'base' => $base,
                'probed_at' => now('UTC')->toIso8601String(),
                'domains' => count($domains),
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("JSON saved under: {$outDir}");

            if ($this->option('domain') && is_dir($outDir)) {
                $id = (int) $this->option('domain');
                foreach (["domain-{$id}-visit.json", "domain-{$id}-recording.json"] as $file) {
                    $path = "{$outDir}/{$file}";
                    if (! is_file($path)) {
                        continue;
                    }
                    $this->newLine();
                    $this->info("── {$file} ──");
                    $this->line((string) file_get_contents($path));
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<object>
     */
    private function loadDomains(int $limit): array
    {
        if (! Schema::hasTable('domains')) {
            $this->error('domains table missing');

            return [];
        }

        $q = DB::table('domains')
            ->select(['id', 'hostname', 'domain_key', 'status', 'user_id'])
            ->whereNotNull('domain_key')
            ->where('domain_key', '!=', '')
            ->orderByDesc('id');

        $single = $this->option('domain');
        if ($single !== null && trim((string) $single) !== '') {
            $q->where('id', (int) $single);
        } elseif (Schema::hasColumn('domains', 'status')) {
            $q->whereIn('status', ['connected', 'pending']);
        }

        $rows = $q->limit($limit)->get();

        if ($rows->isEmpty() && ($single === null || trim((string) $single) === '')) {
            $rows = DB::table('domains')
                ->select(['id', 'hostname', 'domain_key', 'status', 'user_id'])
                ->whereNotNull('domain_key')
                ->where('domain_key', '!=', '')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        }

        return $rows->all();
    }

    /**
     * @return array{db: bool, plan: bool}
     */
    private function sessionRecordingOn(int $domainId, int $userId): array
    {
        $db = false;
        if (Schema::hasTable('domain_detection_settings')) {
            $settings = DomainDetectionSetting::query()->where('domain_id', $domainId)->first();
            $db = (bool) ($settings?->session_recordings);
        }

        $plan = true;
        if ($userId > 0) {
            $user = \App\Models\User::query()->find($userId);
            if ($user) {
                $plan = DetectionPlanFeatures::enabled($user, DetectionPlanFeatures::SESSION_RECORDINGS);
            }
        }

        return ['db' => $db, 'plan' => $plan];
    }

    private function pageUrlForHost(string $hostname): string
    {
        $host = trim($hostname);
        if ($host === '') {
            return 'https://example.com/pricing';
        }
        $host = preg_replace('#^https?://#i', '', $host) ?: $host;
        $host = rtrim($host, '/');

        return 'https://'.$host.'/pricing';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status:int, body:string, json:?array, sent_session_id?:string, sent_visitor_id?:string}
     */
    private function postJson(string $url, array $payload, int $timeout): array
    {
        $sentSession = isset($payload['session_id']) ? (string) $payload['session_id'] : null;
        $sentVisitor = isset($payload['visitor_id']) ? (string) $payload['visitor_id'] : null;

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['User-Agent' => 'ClickronixCtaProbe/1.0'])
                ->post($url, $payload);

            $body = $response->body();
            $json = null;
            try {
                $decoded = $response->json();
                $json = is_array($decoded) ? $decoded : null;
            } catch (\Throwable) {
                $json = null;
            }

            return [
                'status' => $response->status(),
                'body' => $body,
                'json' => $json,
                'sent_session_id' => $sentSession,
                'sent_visitor_id' => $sentVisitor,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'body' => $e->getMessage(),
                'json' => ['ok' => false, 'error' => 'http_exception', 'message' => $e->getMessage()],
                'sent_session_id' => $sentSession,
                'sent_visitor_id' => $sentVisitor,
            ];
        }
    }
}
