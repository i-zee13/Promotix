<?php

namespace App\Support\PaidAdvertising;

/**
 * User-facing labels for paid ADS_* primary detection codes (Advanced View / IP rows).
 */
final class AdsDetectionLabels
{
    /** @var array<string, string> */
    private const MAP = [
        'ADS_FP_REMATCH' => 'Same Device Detected',
        'ADS_GCLID_DUP' => 'Duplicate Ad Click ID',
        'ADS_WBRAID_DUP' => 'Duplicate Ad Click ID',
        'ADS_GBRAID_DUP' => 'Duplicate Ad Click ID',
        'ADS_CLICKID_MISSING' => 'Missing Ad Click ID',
        'ADS_REPEAT_2_60M' => 'Repeated Ad Clicks',
        'ADS_REPEAT_3_5M' => 'Repeated Ad Clicks',
        'ADS_REPEAT_3_15M' => 'Repeated Ad Clicks',
        'ADS_REPEAT_3_60M' => 'Repeated Ad Clicks',
        'ADS_REPEAT_4_60M' => 'Repeated Ad Clicks',
        'ADS_REPEAT_5_60M' => 'Repeated Ad Clicks',
        'ADS_REPEAT_8_24H' => 'Repeated Ad Clicks',
        'ADS_REPEAT_10_24H' => 'Repeated Ad Clicks',
        'ADS_REPEAT_20_7D' => 'Repeated Ad Clicks',
        'ADS_PERSISTENT_REPEAT' => 'Recurring Ad Visits',
        'ADS_IP_MULTI_DEVICE' => 'Shared IP / Multi Device',
        'ADS_KNOWN_FRAUD' => 'Known Fraud Pattern',
        'ABNORMAL_RATE' => 'Unusual Activity',
        'ABNORMAL RATE' => 'Unusual Activity',
        'ABNORMAL_RATE_LIMIT' => 'Unusual Activity',
        'REPEATED' => 'Repeated Ad Clicks',
        'REPEATED_CLICK' => 'Repeated Ad Clicks',
        'REPEATED CLICK' => 'Repeated Ad Clicks',
        'VPN' => 'VPN Detected',
        'PROXY' => 'Proxy Detected',
        'DATA_CENTER' => 'Datacenter Traffic',
        'DATACENTER' => 'Datacenter Traffic',
        'BOT' => 'Bot / Automation',
        'MALICIOUS' => 'Malicious Traffic',
        'OUT_OF_GEO' => 'Out of Geo',
    ];

    public static function label(?string $code): string
    {
        $raw = trim((string) $code);
        if ($raw === '' || $raw === '—' || $raw === '-') {
            return '';
        }

        // Compound cells e.g. "Abnormal Rate, Repeated"
        if (str_contains($raw, ',')) {
            $parts = array_values(array_filter(array_map(
                static fn (string $part): string => self::label(trim($part)),
                explode(',', $raw)
            )));

            return $parts !== [] ? implode(', ', array_unique($parts)) : $raw;
        }

        $key = strtoupper(str_replace(['-', ' '], ['_', '_'], $raw));
        $keySpaced = strtoupper(trim($raw));

        return self::MAP[$key]
            ?? self::MAP[$keySpaced]
            ?? self::MAP[str_replace('_', ' ', $key)]
            ?? (str_starts_with($key, 'ADS_REPEAT') ? 'Repeated Ad Clicks' : $raw);
    }
}
