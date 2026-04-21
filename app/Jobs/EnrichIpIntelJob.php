<?php

namespace App\Jobs;

use App\Models\IpLog;
use App\Models\PaidMarketingVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class EnrichIpIntelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ipLogId)
    {
    }

    public function handle(): void
    {
        $log = IpLog::find($this->ipLogId);
        if (! $log) {
            return;
        }

        // Cache: refresh at most once per 24 hours.
        if ($log->intel_checked_at && $log->intel_checked_at->gt(now()->subDay())) {
            $log->intel_status = 'skipped';
            $log->save();
            return;
        }

        $ip = $log->ip;

        try {
            $ipdetails = $this->checkIpDetails($ip);
            $geo = $this->checkIpWhoIs($ip); // free, no key (geo fallback)
            $abuse = $this->checkAbuseIpDb($ip); // optional (only if ABUSEIPDB_KEY is set)

            // ipdetails.io (free)
            $log->ipdetails_raw = $ipdetails ?: null;
            $log->ipdetails_abuser_score = isset($ipdetails['abuser_score']) ? (float) $ipdetails['abuser_score'] : null;

            $log->intel_country_code = ($geo['country_code'] ?? null) ?? ($abuse['countryCode'] ?? null);
            $log->intel_country_name = ($geo['country'] ?? null);
            $log->intel_isp = ($geo['connection']['isp'] ?? null) ?? ($ipdetails['company'] ?? null) ?? ($abuse['isp'] ?? null);

            $log->abuse_confidence_score = $abuse['abuseConfidenceScore'] ?? null;
            $log->abuse_total_reports = $abuse['totalReports'] ?? null;
            $log->abuse_is_tor = $abuse['isTor'] ?? null;

            $log->intel_checked_at = Carbon::now();
            $log->intel_status = 'ok';
            $log->save();

            // Update paid marketing rows for the same IP (best-effort).
            [$group, $type] = $this->mapThreat($log);
            $update = [];
            if ($log->intel_country_name) {
                $update['country'] = $log->intel_country_name;
            }
            if ($group) {
                $update['threat_group'] = $group;
            }
            if ($type) {
                $update['threat_type'] = $type;
            }

            if (! empty($update)) {
                PaidMarketingVisit::where('ip', $ip)->update($update);
            }
        } catch (\Throwable $e) {
            $log->intel_checked_at = Carbon::now();
            $log->intel_status = 'error';
            $log->save();
        }
    }

    private function checkIpDetails(string $ip): array
    {
        $base = rtrim(config('services.ipdetails.base_url'), '/');
        $url = $base . '/';

        $res = Http::timeout(8)
            ->acceptJson()
            ->get($url, ['ip' => $ip]);

        if (! $res->successful()) {
            return [];
        }

        return (array) $res->json();
    }

    private function checkIpWhoIs(string $ip): array
    {
        // Docs: https://ipwho.is (no key required)
        // Endpoint returns { success, country, country_code, connection: { isp, ... }, ... }
        $url = 'https://ipwho.is/' . $ip;

        $res = Http::timeout(8)
            ->acceptJson()
            ->get($url);

        if (! $res->successful()) {
            return [];
        }

        $json = (array) $res->json();
        if (($json['success'] ?? true) === false) {
            return [];
        }

        return $json;
    }

    private function checkAbuseIpDb(string $ip): array
    {
        $key = config('services.abuseipdb.key');
        if (! $key) {
            return [];
        }

        $base = rtrim(config('services.abuseipdb.base_url'), '/');
        $url = $base . '/api/v2/check';

        $res = Http::timeout(8)
            ->acceptJson()
            ->withHeaders(['Key' => $key])
            ->get($url, [
                'ipAddress' => $ip,
                'maxAgeInDays' => 30,
            ]);

        if (! $res->successful()) {
            return [];
        }

        $json = (array) $res->json();
        return (array) ($json['data'] ?? []);
    }

    private function mapThreat(IpLog $log): array
    {
        // Simple mapping for UI columns (can be refined later).
        // ipdetails_abuser_score appears to be 0..1 (screenshot shows 0.0039 Low).
        if (is_numeric($log->ipdetails_abuser_score)) {
            $s = (float) $log->ipdetails_abuser_score;
            if ($s >= 0.7) {
                return ['Malicious Activity', 'Abuser score'];
            }
            if ($s >= 0.2) {
                return ['Suspicious Activity', 'Abuser score'];
            }
        }

        if (is_int($log->abuse_confidence_score) && $log->abuse_confidence_score >= 50) {
            return ['Malicious Activity', 'Abuse'];
        }

        return [null, null];
    }
}

