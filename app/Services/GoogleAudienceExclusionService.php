<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoogleAudienceExclusionService
{
    /** @var list<string> */
    private const ALL_EXCLUSION_GROUPS = ['data_center', 'vpn', 'proxy', 'abnormal_rate_limit', 'malicious', 'out_of_geo', 'blocked'];

    /** @var list<string> */
    private const BOT_MALICIOUS_GROUPS = ['data_center', 'vpn', 'abnormal_rate_limit', 'malicious', 'blocked'];

    public function shouldQueue(string $threatGroup, string $actionTaken, DomainDetectionSetting $settings): bool
    {
        if ($actionTaken !== 'block' || $settings->audience_exclusion_event === 'disable_auto_exclusions') {
            return false;
        }

        $group = strtolower(trim($threatGroup));

        if ($settings->audience_exclusion_event === 'exclude_bot_malicious_only') {
            return in_array($group, self::BOT_MALICIOUS_GROUPS, true);
        }

        if ($settings->audience_exclusion_event === 'exclude_all_threat_groups_auto') {
            return in_array($group, self::ALL_EXCLUSION_GROUPS, true);
        }

        return false;
    }

    public function queueIp(Domain $domain, string $ip, ?string $threatGroup): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions') || $ip === '') {
            return;
        }

        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        if (! $settings) {
            return;
        }

        DB::table('google_ads_ip_exclusions')->updateOrInsert(
            ['domain_id' => $domain->id, 'ip' => $ip],
            [
                'threat_group' => $threatGroup,
                'exclusion_mode' => $settings->audience_exclusion_event,
                'sync_status' => 'pending',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
