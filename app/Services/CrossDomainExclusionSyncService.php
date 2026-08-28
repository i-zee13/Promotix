<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Support\CrossDomainIntel;
use App\Support\GoogleIpBlockFormatter;

class CrossDomainExclusionSyncService
{
    public function __construct(
        private readonly CrossDomainIntel $crossDomainIntel,
        private readonly GoogleAudienceExclusionService $exclusionService,
    ) {}

    /**
     * Queue cross-domain IPs into the domain's Google Ads exclusion manager.
     *
     * @param  array<string, mixed>  $rules  google_exclusion_rules payload
     * @return array{queued: int, matched: int}
     */
    public function syncForDomain(Domain $domain, DomainDetectionSetting $settings, array $rules): array
    {
        if (! ($rules['cross_domain_enabled'] ?? false)) {
            return ['queued' => 0, 'matched' => 0];
        }

        $mode = (string) ($rules['cross_domain_mode'] ?? 'all');
        if (! in_array($mode, ['all', 'domain_similarity'], true)) {
            $mode = 'all';
        }

        $domain->loadMissing('user');
        $workspaceDomainIds = Domain::query()
            ->where('user_id', $domain->user_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($workspaceDomainIds === []) {
            return ['queued' => 0, 'matched' => 0];
        }

        $rows = $this->crossDomainIntel->buildForDomainIds($workspaceDomainIds, 500);
        $ips = $this->crossDomainIntel->filterIpsByMode($rows, $mode);
        $queued = 0;

        foreach ($ips as $ip) {
            if (! GoogleIpBlockFormatter::isSupported($ip)) {
                continue;
            }
            $this->exclusionService->queueIp($domain, $ip, 'cross_domain', $settings);
            $queued++;
        }

        return ['queued' => $queued, 'matched' => count($ips)];
    }
}
