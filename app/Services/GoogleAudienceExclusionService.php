<?php

namespace App\Services;

use App\Jobs\SyncGoogleAdsIpExclusionJob;
use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Support\GlobalIpAllowlist;

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

        return $this->ruleAllowsThreatGroup($group, $rules);
    }

    /**
     * Queue a blocked IP for Google Ads campaign exclusion when detection-settings rules allow it.
     * Site blocking is handled separately; this only controls the Google exclusion list.
     */
    public function queueBlockedIpIfEligible(
        Domain $domain,
        string $ip,
        ?string $threatGroup,
        ?DomainDetectionSetting $settings = null,
        bool $isPaidTraffic = true,
    ): bool {
        if ($ip === '' || ! $isPaidTraffic) {
            return false;
        }

        if (! $domain->hasGoogleAdsConnection()) {
            return false;
        }

        if (GlobalIpAllowlist::matches($ip)) {
            return false;
        }

        $domain->loadMissing('user');
        if (! \App\Support\DetectionPlanFeatures::enabled(
            $domain->user,
            \App\Support\DetectionPlanFeatures::GOOGLE_EXCLUSION
        )) {
            return false;
        }

        $settings ??= DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        if (! $settings || ! $this->shouldQueue((string) ($threatGroup ?? ''), 'block', $settings)) {
            return false;
        }

        $this->queueIp($domain, $ip, $threatGroup, $settings);

        return true;
    }

    public function queueIp(
        Domain $domain,
        string $ip,
        ?string $threatGroup,
        ?DomainDetectionSetting $settings = null,
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('google_ads_ip_exclusions') || $ip === '') {
            return;
        }

        $settings ??= DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        if (! $settings) {
            return;
        }

        $payload = [
            'threat_group' => $threatGroup,
            'exclusion_mode' => $settings->audience_exclusion_event,
            'sync_status' => 'pending',
            'sync_error' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('google_ads_ip_exclusions', 'is_active')) {
            $payload['is_active'] = true;
        }

        \Illuminate\Support\Facades\DB::table('google_ads_ip_exclusions')->updateOrInsert(
            ['domain_id' => $domain->id, 'ip' => $ip],
            $payload
        );

        SyncGoogleAdsIpExclusionJob::dispatch($domain->id, $ip);
    }

    /** @param  array<string, bool>  $rules */
    public function ruleAllowsThreatGroup(string $threatGroup, array $rules): bool
    {
        $group = strtolower(trim($threatGroup));

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

    /** @return array<string, bool|string> */
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
            'cross_domain_enabled' => false,
            'cross_domain_mode' => 'all',
            'guidance_chatbot_enabled' => false,
        ];
    }

    /** @return array<string, bool> */
    private function normalizedRules(DomainDetectionSetting $settings): array
    {
        $stored = is_array($settings->google_exclusion_rules) ? $settings->google_exclusion_rules : [];

        return array_merge($this->defaultRules(), $stored);
    }

    /**
     * Exclusion Manager lists only detection blocks and cross-domain IPs — not manual uploads.
     */
    public static function isExclusionManagerRow(?string $threatGroup, ?string $exclusionMode = null): bool
    {
        $group = strtolower(trim((string) $threatGroup));
        $mode = strtolower(trim((string) $exclusionMode));

        if (in_array($group, ['manual', 'manual_bulk'], true) || $mode === 'manual_bulk') {
            return false;
        }

        if ($group === 'cross_domain') {
            return true;
        }

        return $group !== '';
    }

    public static function threatGroupLabel(?string $threatGroup): string
    {
        $group = strtolower(trim((string) $threatGroup));

        return match ($group) {
            'cross_domain' => 'Cross-domain',
            'data_center', 'datacenter' => 'Data center',
            'abnormal_rate_limit' => 'Rate limit',
            'out_of_geo' => 'Out of geo',
            'google_invalid' => 'Google invalid',
            'blocked' => 'Detected block',
            '' => 'Detected block',
            default => ucwords(str_replace('_', ' ', $group)),
        };
    }
}
