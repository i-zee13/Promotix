<?php

namespace App\Services\IpIntel;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;

class BlockListMatcher
{
    public static function isBlockListed(Domain $domain, string $ip): bool
    {
        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();

        return self::matchesSettings($settings, $ip);
    }

    public static function matchesSettings(?DomainDetectionSetting $settings, string $ip): bool
    {
        if ($settings === null || ! $settings->block_list_enabled) {
            return false;
        }

        return IpFraudEvaluator::isIpInList($ip, (string) $settings->block_list_ips);
    }

    /** @param  list<string>  $reasons */
    public static function reasonsIndicateBlockList(array $reasons): bool
    {
        return in_array('block_list', $reasons, true);
    }
}
