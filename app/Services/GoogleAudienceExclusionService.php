<?php

namespace App\Services;

use App\Jobs\SyncGoogleAdsIpExclusionJob;
use App\Models\DomainDetectionSetting;

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

        $rules = $this->normalizedRules($settings);
        if (! ($rules['enabled'] ?? true)) {
            return false;
        }

        $group = strtolower(trim($threatGroup));

        if ($settings->audience_exclusion_event === 'exclude_bot_malicious_only'
            && ! in_array($group, self::BOT_MALICIOUS_GROUPS, true)) {
            return false;
        }

        if ($group === '' || $group === 'blocked') {
            return (bool) ($rules['exclude_invalid'] ?? true);
        }

        $ruleKey = match ($group) {
            'malicious' => 'exclude_malicious',
            'vpn' => 'exclude_vpn',
            'proxy' => 'exclude_proxy',
            'data_center', 'datacenter' => 'exclude_data_center',
            'abnormal_rate_limit' => 'exclude_rate_limit',
            'out_of_geo' => 'exclude_out_of_geo',
            default => 'exclude_invalid',
        };

        return (bool) ($rules[$ruleKey] ?? $rules['exclude_invalid'] ?? true);
    }

    public function queueIp(\App\Models\Domain $domain, string $ip, ?string $threatGroup): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('google_ads_ip_exclusions') || $ip === '') {
            return;
        }

        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        if (! $settings) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('google_ads_ip_exclusions')->updateOrInsert(
            ['domain_id' => $domain->id, 'ip' => $ip],
            [
                'threat_group' => $threatGroup,
                'exclusion_mode' => $settings->audience_exclusion_event,
                'sync_status' => 'pending',
                'sync_error' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        SyncGoogleAdsIpExclusionJob::dispatch($domain->id, $ip);
    }

    /** @return array<string, bool> */
    public function defaultRules(): array
    {
        return [
            'enabled' => true,
            'exclude_invalid' => true,
            'exclude_malicious' => true,
            'exclude_vpn' => true,
            'exclude_data_center' => true,
            'exclude_proxy' => true,
            'exclude_rate_limit' => true,
            'exclude_out_of_geo' => true,
        ];
    }

    /** @return array<string, bool> */
    private function normalizedRules(DomainDetectionSetting $settings): array
    {
        $stored = is_array($settings->google_exclusion_rules) ? $settings->google_exclusion_rules : [];

        return array_merge($this->defaultRules(), $stored);
    }
}
