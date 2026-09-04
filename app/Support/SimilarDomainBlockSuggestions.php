<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Services\GoogleAudienceExclusionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimilarDomainBlockSuggestions
{
    private readonly CrossDomainIntel $intel;

    public function __construct(?CrossDomainIntel $intel = null)
    {
        $this->intel = $intel ?? new CrossDomainIntel;
    }

    /**
     * When a user adds e.g. internetfiber.online, suggest blocked IPs from
     * name-similar domains already in the DB (Medium/High similarity).
     *
     * @return array{
     *   hostname: string,
     *   similar_domains: list<array{id:int,hostname:string,similarity:int,similarity_label:string}>,
     *   suggested_ips: list<array{ip:string,from_domains:list<string>,sources:list<string>,hits:int}>,
     *   count: int
     * }
     */
    public function forHostname(string $hostname, float $minScore = 0.55, int $domainLimit = 40, int $ipLimit = 100): array
    {
        $hostname = $this->normalizeHostname($hostname);
        $empty = [
            'hostname' => $hostname,
            'similar_domains' => [],
            'suggested_ips' => [],
            'count' => 0,
        ];

        if ($hostname === '') {
            return $empty;
        }

        $candidates = Domain::query()
            ->whereNotNull('hostname')
            ->where('hostname', '!=', '')
            ->orderByDesc('id')
            ->limit(800)
            ->get(['id', 'hostname']);

        $similar = [];
        foreach ($candidates as $domain) {
            $other = $this->normalizeHostname((string) $domain->hostname);
            if ($other === '' || $other === $hostname) {
                continue;
            }
            $score = $this->intel->hostnameSimilarity($hostname, $other);
            if ($score < $minScore) {
                continue;
            }
            $pct = (int) round($score * 100);
            $similar[] = [
                'id' => (int) $domain->id,
                'hostname' => $other,
                'similarity' => $pct,
                'similarity_label' => $pct >= 85 ? 'High' : 'Medium',
            ];
        }

        usort($similar, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
        $similar = array_slice($similar, 0, $domainLimit);
        if ($similar === []) {
            return $empty;
        }

        $similarIds = array_column($similar, 'id');
        $hostnameById = [];
        foreach ($similar as $row) {
            $hostnameById[$row['id']] = $row['hostname'];
        }

        /** @var array<string, array{ip:string,from_domains:array<string,bool>,sources:array<string,bool>,hits:int}> $bucket */
        $bucket = [];

        $this->collectFromBlockLists($similarIds, $hostnameById, $bucket);
        $this->collectFromExclusions($similarIds, $hostnameById, $bucket);
        $this->collectFromBlockedVisits($similarIds, $hostnameById, $bucket);

        $suggested = collect($bucket)
            ->map(fn (array $row) => [
                'ip' => $row['ip'],
                'from_domains' => array_keys($row['from_domains']),
                'sources' => array_keys($row['sources']),
                'hits' => $row['hits'],
            ])
            ->sortByDesc('hits')
            ->take($ipLimit)
            ->values()
            ->all();

        return [
            'hostname' => $hostname,
            'similar_domains' => $similar,
            'suggested_ips' => $suggested,
            'count' => count($suggested),
        ];
    }

    /**
     * Merge suggested IPs into the domain block list and queue Google exclusions.
     *
     * @param  list<string>  $ips
     * @return array{applied:int,queued:int,block_list_count:int}
     */
    public function applyToDomain(Domain $domain, array $ips): array
    {
        $clean = collect($ips)
            ->map(fn ($ip) => trim((string) $ip))
            ->filter(fn ($ip) => $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false)
            ->unique()
            ->values()
            ->all();

        if ($clean === []) {
            return ['applied' => 0, 'queued' => 0, 'block_list_count' => 0];
        }

        $settings = DomainDetectionSetting::query()->firstOrCreate(
            ['domain_id' => $domain->id],
            [
                'invalid_bot_action' => 'block',
                'invalid_malicious_action' => 'block',
                'suspicious_enabled' => true,
                'block_list_enabled' => true,
                'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
            ]
        );

        $existing = IpListParser::normalizeLines((string) ($settings->block_list_ips ?? ''));
        $existingIps = [];
        foreach ($existing as $line) {
            $existingIps[IpListParser::entryIp($line)] = true;
        }

        $added = 0;
        foreach ($clean as $ip) {
            if (isset($existingIps[$ip])) {
                continue;
            }
            $existing[] = $ip;
            $existingIps[$ip] = true;
            $added++;
        }

        $settings->forceFill([
            'block_list_enabled' => true,
            'block_list_ips' => implode("\n", $existing),
        ])->save();

        $queued = 0;
        if ($domain->hasGoogleAdsConnection()) {
            $exclusion = app(GoogleAudienceExclusionService::class);
            foreach ($clean as $ip) {
                if (! GoogleIpBlockFormatter::isSupported($ip)) {
                    continue;
                }
                $exclusion->queueIp($domain, $ip, 'cross_domain', $settings);
                $queued++;
            }
        }

        return [
            'applied' => $added,
            'queued' => $queued,
            'block_list_count' => count($existing),
        ];
    }

    /**
     * @param  list<int>  $domainIds
     * @param  array<int, string>  $hostnameById
     * @param  array<string, array{ip:string,from_domains:array<string,bool>,sources:array<string,bool>,hits:int}>  $bucket
     */
    private function collectFromBlockLists(array $domainIds, array $hostnameById, array &$bucket): void
    {
        if (! Schema::hasTable('domain_detection_settings') || ! Schema::hasColumn('domain_detection_settings', 'block_list_ips')) {
            return;
        }

        $rows = DomainDetectionSetting::query()
            ->whereIn('domain_id', $domainIds)
            ->whereNotNull('block_list_ips')
            ->get(['domain_id', 'block_list_ips']);

        foreach ($rows as $row) {
            $host = $hostnameById[(int) $row->domain_id] ?? ('#'.$row->domain_id);
            foreach (IpListParser::normalizeLines((string) $row->block_list_ips) as $line) {
                if (! IpListParser::isActiveEntry($line)) {
                    continue;
                }
                $ip = IpListParser::entryIp($line);
                if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    continue;
                }
                $this->pushIp($bucket, $ip, $host, 'block_list');
            }
        }
    }

    /**
     * @param  list<int>  $domainIds
     * @param  array<int, string>  $hostnameById
     * @param  array<string, array{ip:string,from_domains:array<string,bool>,sources:array<string,bool>,hits:int}>  $bucket
     */
    private function collectFromExclusions(array $domainIds, array $hostnameById, array &$bucket): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            return;
        }

        $query = DB::table('google_ads_ip_exclusions')
            ->whereIn('domain_id', $domainIds)
            ->select(['domain_id', 'ip']);
        if (Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            $query->where(function ($q): void {
                $q->where('is_active', true)->orWhereNull('is_active');
            });
        }

        foreach ($query->limit(2000)->get() as $row) {
            $ip = trim((string) $row->ip);
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $host = $hostnameById[(int) $row->domain_id] ?? ('#'.$row->domain_id);
            $this->pushIp($bucket, $ip, $host, 'google_exclusion');
        }
    }

    /**
     * @param  list<int>  $domainIds
     * @param  array<int, string>  $hostnameById
     * @param  array<string, array{ip:string,from_domains:array<string,bool>,sources:array<string,bool>,hits:int}>  $bucket
     */
    private function collectFromBlockedVisits(array $domainIds, array $hostnameById, array &$bucket): void
    {
        if (! Schema::hasTable('visits') || ! Schema::hasColumn('visits', 'ip')) {
            return;
        }

        $dateCol = Schema::hasColumn('visits', 'visited_at') ? 'visited_at' : 'created_at';
        $query = DB::table('visits')
            ->select(['ip', 'domain_id', DB::raw('COUNT(*) as hits')])
            ->whereIn('domain_id', $domainIds)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->where($dateCol, '>=', now()->subDays(30))
            ->groupBy('ip', 'domain_id')
            ->orderByDesc('hits')
            ->limit(1500);

        if (Schema::hasColumn('visits', 'action_taken')) {
            $query->where('action_taken', 'block');
        } elseif (Schema::hasColumn('visits', 'is_invalid_traffic')) {
            $query->where('is_invalid_traffic', true);
        } else {
            return;
        }

        foreach ($query->get() as $row) {
            $ip = trim((string) $row->ip);
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $host = $hostnameById[(int) $row->domain_id] ?? ('#'.$row->domain_id);
            $this->pushIp($bucket, $ip, $host, 'blocked_visit', (int) $row->hits);
        }
    }

    /**
     * @param  array<string, array{ip:string,from_domains:array<string,bool>,sources:array<string,bool>,hits:int}>  $bucket
     */
    private function pushIp(array &$bucket, string $ip, string $hostname, string $source, int $hits = 1): void
    {
        if (! isset($bucket[$ip])) {
            $bucket[$ip] = [
                'ip' => $ip,
                'from_domains' => [],
                'sources' => [],
                'hits' => 0,
            ];
        }
        $bucket[$ip]['from_domains'][$hostname] = true;
        $bucket[$ip]['sources'][$source] = true;
        $bucket[$ip]['hits'] += max(1, $hits);
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('#^https?://#', '', $hostname) ?? $hostname;
        $hostname = explode('/', $hostname)[0] ?? $hostname;
        $hostname = preg_replace('/:\d+$/', '', $hostname) ?? $hostname;
        $hostname = preg_replace('/^www\./', '', $hostname) ?? $hostname;

        return trim($hostname, '.');
    }
}
