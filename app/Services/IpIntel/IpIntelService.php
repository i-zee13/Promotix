<?php

namespace App\Services\IpIntel;

use App\Models\IpLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpIntelService
{
    /** @var list<string> */
    private const VPN_ISP_KEYWORDS = [
        'vpn', 'nordvpn', 'expressvpn', 'surfshark', 'mullvad', 'protonvpn',
        'cyberghost', 'private internet access', 'pia', 'hotspot shield',
        'tunnelbear', 'windscribe', 'tor exit', 'tor network',
    ];

    /** @var list<string> */
    private const PROXY_ISP_KEYWORDS = [
        'proxy', 'residential proxy', 'bright data', 'luminati', 'oxylabs',
        'smartproxy', 'packetstream', 'geosurf', 'netnut', 'iproyal',
    ];

    /**
     * Refresh intel when missing or older than 24 hours.
     */
    public function enrichIfStale(IpLog $log): IpLog
    {
        if ($this->isFresh($log)) {
            return $log;
        }

        return $this->enrich($log);
    }

    public function isFresh(IpLog $log): bool
    {
        return $log->intel_checked_at !== null
            && $log->intel_checked_at->gt(now()->subDay())
            && $log->intel_status === 'ok';
    }

    public function enrich(IpLog $log): IpLog
    {
        $ip = $log->ip;

        try {
            [$geo, $reputation] = $this->fetchAllParallel($ip);

            $log->intel_country_code = $geo['country_code']
                ?? $reputation['abuse']['countryCode']
                ?? $reputation['ipdetails']['country_code']
                ?? null;
            $log->intel_country_name = $geo['country'] ?? null;
            $log->intel_isp = $geo['isp']
                ?? $reputation['ipdetails']['company']
                ?? $reputation['abuse']['isp']
                ?? null;

            $log->ipdetails_raw = $reputation['ipdetails'] ?: null;
            $log->ipdetails_abuser_score = $this->parseAbuserScore($reputation['ipdetails']['abuser_score'] ?? null);

            $log->abuse_confidence_score = isset($reputation['abuse']['abuseConfidenceScore'])
                ? (int) $reputation['abuse']['abuseConfidenceScore']
                : null;
            $log->abuse_total_reports = isset($reputation['abuse']['totalReports'])
                ? (int) $reputation['abuse']['totalReports']
                : null;
            $log->abuse_is_tor = isset($reputation['abuse']['isTor'])
                ? (bool) $reputation['abuse']['isTor']
                : null;

            $log->intel_checked_at = Carbon::now();
            $log->intel_status = 'ok';
            $log->save();
        } catch (\Throwable $e) {
            Log::warning('IP intel enrichment failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            $log->intel_checked_at = Carbon::now();
            $log->intel_status = 'error';
            $log->save();
        }

        return $log->fresh();
    }

    /**
     * Free providers only: ipwho.is + ipdetails.io + AbuseIPDB (optional key).
     *
     * @return array{0: array<string, mixed>, 1: array{ipdetails: array<string, mixed>, abuse: array<string, mixed>}}
     */
    private function fetchAllParallel(string $ip): array
    {
        $abuseKey = config('services.abuseipdb.key');
        $ipdetailsBase = rtrim((string) config('services.ipdetails.base_url'), '/');
        $abuseBase = rtrim((string) config('services.abuseipdb.base_url'), '/');

        $responses = Http::pool(function ($pool) use ($ip, $ipdetailsBase, $abuseKey, $abuseBase) {
            $batch = [
                $pool->as('geo')->timeout(6)->acceptJson()->get('https://ipwho.is/' . $ip),
                $pool->as('ipdetails')->timeout(6)->acceptJson()->get($ipdetailsBase . '/', ['ip' => $ip]),
            ];

            if ($abuseKey) {
                $batch[] = $pool->as('abuse')->timeout(6)->acceptJson()
                    ->withHeaders(['Key' => $abuseKey])
                    ->get($abuseBase . '/api/v2/check', [
                        'ipAddress' => $ip,
                        'maxAgeInDays' => 30,
                    ]);
            }

            return $batch;
        });

        $geoJson = $responses['geo']->successful() ? (array) $responses['geo']->json() : [];
        if (($geoJson['success'] ?? true) === false) {
            $geoJson = [];
        }

        $geo = [
            'country' => $geoJson['country'] ?? null,
            'country_code' => $geoJson['country_code'] ?? null,
            'isp' => ($geoJson['connection']['isp'] ?? null) ?? ($geoJson['connection']['org'] ?? null),
            'city' => $geoJson['city'] ?? null,
            'region' => $geoJson['region'] ?? null,
        ];

        $ipdetails = $responses['ipdetails']->successful()
            ? (array) $responses['ipdetails']->json()
            : [];

        $abuse = [];
        if (isset($responses['abuse']) && $responses['abuse']->successful()) {
            $abuseJson = (array) $responses['abuse']->json();
            $abuse = (array) ($abuseJson['data'] ?? []);
        }

        return [$geo, ['ipdetails' => $ipdetails, 'abuse' => $abuse]];
    }

    public function parseAbuserScore(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    public function isHostingType(IpLog $log): bool
    {
        $raw = (array) ($log->ipdetails_raw ?? []);
        $type = strtolower((string) ($raw['type'] ?? ''));

        return in_array($type, ['hosting', 'datacenter', 'data_center', 'business'], true);
    }

    public function isVpnSuspect(IpLog $log): bool
    {
        if ((bool) ($log->abuse_is_tor ?? false)) {
            return true;
        }

        return $this->ispMatchesKeywords($log, self::VPN_ISP_KEYWORDS);
    }

    public function isProxySuspect(IpLog $log): bool
    {
        return $this->ispMatchesKeywords($log, self::PROXY_ISP_KEYWORDS);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function ispMatchesKeywords(IpLog $log, array $keywords): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $log->intel_isp,
            (string) (($log->ipdetails_raw ?? [])['company'] ?? ''),
            (string) (($log->ipdetails_raw ?? [])['abuse_name'] ?? ''),
        ])));

        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
