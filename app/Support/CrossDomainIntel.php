<?php

namespace App\Support;

use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrossDomainIntel
{
    /**
     * @param  list<int>|null  $domainIds  null = all domains (super admin)
     * @return list<array<string, mixed>>
     */
    public function buildForDomainIds(?array $domainIds, int $limit = 20): array
    {
        if (! Schema::hasTable('visits') || ! Schema::hasColumn('visits', 'ip')) {
            return [];
        }

        $scoreCol = Schema::hasColumn('visits', 'bot_score') ? 'bot_score' : (Schema::hasColumn('visits', 'threat_score') ? 'threat_score' : null);
        $dateCol = Schema::hasColumn('visits', 'visited_at') ? 'visited_at' : 'created_at';

        $select = [
            'ip',
            DB::raw('COUNT(*) as hits'),
            DB::raw('COUNT(DISTINCT domain_id) as domain_count'),
        ];
        if ($scoreCol) {
            $select[] = DB::raw("MAX({$scoreCol}) as max_bot_score");
            $select[] = DB::raw("AVG({$scoreCol}) as avg_bot_score");
        } else {
            $select[] = DB::raw('0 as max_bot_score');
            $select[] = DB::raw('0 as avg_bot_score');
        }

        $query = DB::table('visits')
            ->select($select)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->where($dateCol, '>=', now()->subDays(30));

        if ($domainIds !== null && $domainIds !== []) {
            $query->whereIn('domain_id', $domainIds);
        }

        $query->groupBy('ip')
            ->havingRaw('COUNT(DISTINCT domain_id) > 1')
            ->orderByDesc('domain_count')
            ->orderByDesc('hits')
            ->limit($limit);

        $domainNames = Domain::query()->pluck('hostname', 'id');

        return collect($query->get())->map(function ($row) use ($domainNames, $dateCol, $domainIds): array {
            $ip = (string) $row->ip;
            $visitQuery = DB::table('visits')
                ->where('ip', $ip)
                ->where($dateCol, '>=', now()->subDays(30));
            if ($domainIds !== null && $domainIds !== []) {
                $visitQuery->whereIn('domain_id', $domainIds);
            }
            $domainIdsForIp = $visitQuery->distinct()->limit(8)->pluck('domain_id');
            $domains = $domainIdsForIp->map(fn ($id) => $domainNames[$id] ?? ('#'.$id))->values()->all();

            $domainCount = (int) $row->domain_count;
            $hits = (int) $row->hits;
            $maxBot = (float) ($row->max_bot_score ?? 0);
            $invalidBoost = 0;
            if (Schema::hasColumn('visits', 'is_invalid_traffic')) {
                $invalidQuery = DB::table('visits')
                    ->where('ip', $ip)
                    ->where('is_invalid_traffic', true)
                    ->where($dateCol, '>=', now()->subDays(30));
                if ($domainIds !== null && $domainIds !== []) {
                    $invalidQuery->whereIn('domain_id', $domainIds);
                }
                $invalidBoost = $invalidQuery->limit(1)->exists() ? 15 : 0;
            }

            $evidence = min(100, (int) round(
                min(40, $domainCount * 12)
                + min(30, log(max(1, $hits), 10) * 12)
                + min(30, $maxBot * 0.3)
                + $invalidBoost
            ));

            return [
                'ip' => $ip,
                'hits' => $hits,
                'domain_count' => $domainCount,
                'domains' => $domains,
                'max_bot_score' => round($maxBot, 1),
                'avg_bot_score' => round((float) ($row->avg_bot_score ?? 0), 1),
                'evidence_score' => $evidence,
                'auto_block' => false,
                ...$this->computeDomainSimilarity($domains),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public function filterIpsByMode(array $rows, string $mode): array
    {
        return collect($rows)
            ->filter(function (array $row) use ($mode): bool {
                if ($mode === 'domain_similarity') {
                    $label = (string) ($row['domain_similarity_label'] ?? '');

                    return in_array($label, ['High', 'Medium'], true);
                }

                return true;
            })
            ->pluck('ip')
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param  list<string>  $domains */
    public function computeDomainSimilarity(array $domains): array
    {
        $normalized = array_values(array_filter(array_map(
            fn ($d) => strtolower(trim(preg_replace('/^www\./', '', (string) $d))),
            $domains
        )));

        if (count($normalized) < 2) {
            return [
                'domain_similarity' => 0,
                'domain_similarity_label' => '—',
                'domain_similarity_pair' => null,
            ];
        }

        $maxSim = 0.0;
        $bestPair = null;
        for ($i = 0; $i < count($normalized); $i++) {
            for ($j = $i + 1; $j < count($normalized); $j++) {
                $sim = $this->hostnameSimilarity($normalized[$i], $normalized[$j]);
                if ($sim > $maxSim) {
                    $maxSim = $sim;
                    $bestPair = [$normalized[$i], $normalized[$j]];
                }
            }
        }

        $score = (int) round($maxSim * 100);
        $label = $score >= 85 ? 'High' : ($score >= 55 ? 'Medium' : 'Low');

        return [
            'domain_similarity' => $score,
            'domain_similarity_label' => $label,
            'domain_similarity_pair' => $bestPair,
        ];
    }

    private function hostnameSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $baseA = $this->registrableDomain($a);
        $baseB = $this->registrableDomain($b);
        if ($baseA !== '' && $baseA === $baseB) {
            return 0.92;
        }

        similar_text($a, $b, $pct);

        return min(1.0, $pct / 100);
    }

    private function registrableDomain(string $host): string
    {
        $parts = array_values(array_filter(explode('.', $host)));
        if (count($parts) <= 2) {
            return $host;
        }

        return implode('.', array_slice($parts, -2));
    }
}
