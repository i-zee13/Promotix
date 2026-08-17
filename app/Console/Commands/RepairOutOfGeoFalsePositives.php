<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\IpLog;
use App\Support\GeoAudienceMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Strip false-positive out_of_geo tags when the current CA/geo audience matcher
 * would allow the visit (e.g. San Diego blocked while California is selected).
 */
class RepairOutOfGeoFalsePositives extends Command
{
    protected $signature = 'geo:repair-out-of-geo
                            {--domain= : Hostname or domain id}
                            {--dry-run : Show counts without writing}
                            {--limit=5000 : Max visits to scan}';

    protected $description = 'Remove false-positive out_of_geo labels when the visitor is inside the allow geo (e.g. California cities).';

    public function handle(): int
    {
        if (! Schema::hasTable('visits') || ! Schema::hasTable('domain_detection_settings')) {
            $this->error('Required tables missing.');

            return self::FAILURE;
        }

        $domainOpt = trim((string) $this->option('domain'));
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $domainQuery = Domain::query()->orderBy('id');
        if ($domainOpt !== '') {
            if (ctype_digit($domainOpt)) {
                $domainQuery->where('id', (int) $domainOpt);
            } else {
                $domainQuery->where('hostname', $domainOpt);
            }
        }

        $domains = $domainQuery->get();
        if ($domains->isEmpty()) {
            $this->warn('No domains matched.');

            return self::SUCCESS;
        }

        $scanned = 0;
        $repaired = 0;
        $intelBackfilled = 0;

        foreach ($domains as $domain) {
            $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
            if (! $settings || ! $settings->out_of_geo_enabled) {
                continue;
            }

            $select = ['id', 'ip', 'country', 'threat_group', 'action_taken', 'is_invalid_traffic', 'detection_reasons'];
            if (Schema::hasColumn('visits', 'threat_type')) {
                $select[] = 'threat_type';
            }

            $visits = DB::table('visits')
                ->where('domain_id', $domain->id)
                ->where(function ($q): void {
                    $q->where('threat_group', 'out_of_geo')
                        ->orWhere('detection_reasons', 'like', '%out_of_geo%');
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get($select);

            foreach ($visits as $visit) {
                $scanned++;
                $ipLog = IpLog::query()->where('ip', $visit->ip)->first();
                if ($ipLog) {
                    $intelBackfilled += $this->backfillIntelFromRaw($ipLog, $dryRun);
                }

                $allowed = GeoAudienceMatcher::isAllowed(
                    $settings,
                    $visit->country,
                    $ipLog?->intel_region,
                    $ipLog?->intel_city,
                    $ipLog,
                    $domain,
                );

                if (! $allowed) {
                    continue;
                }

                $reasons = json_decode((string) ($visit->detection_reasons ?? '[]'), true);
                $reasons = is_array($reasons) ? array_values($reasons) : [];
                $filtered = array_values(array_filter(
                    $reasons,
                    static fn ($r) => strtolower(trim((string) $r)) !== 'out_of_geo'
                ));

                if ($filtered === $reasons && (string) $visit->threat_group !== 'out_of_geo') {
                    continue;
                }

                $nextThreat = $filtered[0] ?? null;
                $stillInvalid = $filtered !== [];

                $payload = [
                    'detection_reasons' => json_encode($filtered),
                    'threat_group' => $nextThreat,
                    'updated_at' => now(),
                ];

                if (! $stillInvalid) {
                    $payload['is_invalid_traffic'] = false;
                    if (strtolower((string) $visit->action_taken) === 'block'
                        && strtolower((string) ($visit->threat_group ?? '')) === 'out_of_geo') {
                        $payload['action_taken'] = 'allow';
                    }
                }

                $repaired++;
                if ($dryRun) {
                    $this->line("[dry-run] visit {$visit->id} {$visit->ip} → threat=".($nextThreat ?? 'null'));
                    continue;
                }

                DB::table('visits')->where('id', $visit->id)->update($payload);
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Scanned {$scanned}, repaired {$repaired}, intel backfilled {$intelBackfilled}.");

        return self::SUCCESS;
    }

    private function backfillIntelFromRaw(IpLog $ipLog, bool $dryRun): int
    {
        $raw = (array) ($ipLog->ipdetails_raw ?? []);
        $changed = false;

        if (! filled($ipLog->intel_city) && filled($raw['city'] ?? null)) {
            $ipLog->intel_city = (string) $raw['city'];
            $changed = true;
        }

        $region = $raw['region'] ?? $raw['state'] ?? $raw['state1'] ?? $raw['state2'] ?? $raw['region_code'] ?? null;
        if (! filled($ipLog->intel_region) && filled($region)) {
            $ipLog->intel_region = (string) $region;
            $changed = true;
        }

        if (! $changed) {
            return 0;
        }

        if (! $dryRun) {
            $ipLog->save();
        }

        return 1;
    }
}
