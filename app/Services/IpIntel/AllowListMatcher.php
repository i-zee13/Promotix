<?php

namespace App\Services\IpIntel;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Support\GlobalIpAllowlist;

class AllowListMatcher
{
    public static function isAllowListed(Domain $domain, string $ip): bool
    {
        if (GlobalIpAllowlist::matches($ip)) {
            return true;
        }

        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();

        return self::matchesSettings($settings, $ip);
    }

    public static function matchesSettings(?DomainDetectionSetting $settings, string $ip): bool
    {
        if ($settings === null || ! $settings->allow_list_enabled) {
            return false;
        }

        return IpFraudEvaluator::isIpAllowListed($ip, (string) $settings->allow_list_ips);
    }

    /** @param  list<string>  $reasons */
    public static function reasonsIndicateAllowList(array $reasons): bool
    {
        return array_intersect($reasons, ['allow_list', 'allow_list_override']) !== [];
    }
}
