<?php

namespace App\Services;

use App\Models\ClickronixDevice;
use App\Models\Domain;
use App\Support\PaidAdvertising\PaidDeviceFingerprinter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Device-intelligence brain (spec V1):
 * persistent DEV_ (token ≠ fingerprint), paid-click history, conversions,
 * combined exclusion rules, GA4 clickronix_exclude event.
 */
class ClickronixDeviceService
{
    public const EXCLUDE_EVENT = 'clickronix_exclude';

    /**
     * @param  array{
     *   device_token?: ?string,
     *   fingerprint?: ?string,
     *   fingerprint_id?: ?string,
     *   ga4_client_id?: ?string,
     *   gclid?: ?string,
     *   gbraid?: ?string,
     *   wbraid?: ?string,
     *   ip?: ?string,
     *   is_paid?: bool,
     *   is_invalid?: bool,
     *   landing_page?: ?string,
     *   session_id?: ?string,
     *   automation?: bool,
     *   proxy?: bool,
     *   vpn?: bool,
     *   datacenter?: bool,
     *   user_agent?: ?string,
     * }  $ctx
     * @return array{
     *   device_id: string,
     *   device_token: string,
     *   fingerprint_id: string,
     *   device_confidence: float,
     *   exclusion_candidate: bool,
     *   risk_score: int,
     *   risk_label: ?string,
     *   risk_reason: ?string,
     *   fire_exclude_event: bool,
     *   ga4_client_id: ?string,
     * }
     */
    public function ingest(Domain $domain, array $ctx): array
    {
        if (! Schema::hasTable('clickronix_devices')) {
            $fp = PaidDeviceFingerprinter::fingerprintId(
                $ctx['fingerprint'] ?? null,
                '',
                $ctx['user_agent'] ?? null,
                null,
            );
            $dev = PaidDeviceFingerprinter::deviceId($fp, $ctx['user_agent'] ?? null);

            return $this->emptyResult($dev, (string) ($ctx['device_token'] ?? ''), $fp);
        }

        $token = $this->normalizeToken($ctx['device_token'] ?? null);
        $fingerprintId = trim((string) ($ctx['fingerprint_id'] ?? ''));
        if ($fingerprintId === '') {
            $fingerprintId = PaidDeviceFingerprinter::fingerprintId(
                $ctx['fingerprint'] ?? null,
                '',
                $ctx['user_agent'] ?? null,
                null,
            );
        }

        $device = $this->resolveDevice($domain, $token, $fingerprintId, $ctx);
        $confidence = $this->scoreConfidence($device, $token, $fingerprintId, $ctx);
        $device->device_confidence = $confidence;
        $device->fingerprint_id = $fingerprintId !== '' ? $fingerprintId : $device->fingerprint_id;

        $ga4 = trim((string) ($ctx['ga4_client_id'] ?? ''));
        if ($ga4 !== '') {
            $device->ga4_client_id = Str::limit($ga4, 128, '');
        }

        $this->touchIpHistory($device, trim((string) ($ctx['ip'] ?? '')));
        $this->touchThreatFlags($device, $ctx);

        $isPaid = (bool) ($ctx['is_paid'] ?? false);
        $isInvalid = (bool) ($ctx['is_invalid'] ?? false);
        if ($isPaid) {
            $this->recordPaidClick($device, $ctx, $isInvalid);
        }

        $decision = $this->evaluateExclusion($device);
        $device->risk_score = $decision['risk_score'];
        $device->risk_reason = $decision['risk_reason'];
        $device->risk_label = $decision['risk_label'];
        $device->exclusion_candidate = $decision['exclusion_candidate'];
        $device->save();

        $fireExclude = false;
        if ($device->exclusion_candidate && ! $device->ga4_exclusion_sent) {
            $fireExclude = true;
            $this->sendGa4ExcludeEvent($domain, $device);
            // Fire once: client tag receives fire_exclude_event; MP used when configured.
            $device->ga4_exclusion_sent = true;
            $device->ga4_exclusion_sent_at = now();
            $device->save();
        }

        return [
            'device_id' => (string) $device->device_id,
            'device_token' => (string) ($device->device_token ?: $token),
            'fingerprint_id' => (string) ($device->fingerprint_id ?: $fingerprintId),
            'device_confidence' => (float) $device->device_confidence,
            'exclusion_candidate' => (bool) $device->exclusion_candidate,
            'risk_score' => (int) $device->risk_score,
            'risk_label' => $device->risk_label,
            'risk_reason' => $device->risk_reason,
            'fire_exclude_event' => $fireExclude,
            'ga4_client_id' => $device->ga4_client_id,
            'paid_click_count' => (int) $device->paid_click_count,
            'conversion_count' => (int) $device->conversion_count,
            'ip_count' => (int) $device->ip_count,
            'ip_change_count' => (int) $device->ip_change_count,
        ];
    }

    /**
     * @param  array{client_id?:string,gclid?:string,conversion?:string,timestamp?:mixed,device_id?:string,domain_key?:string}  $payload
     */
    public function recordConversion(Domain $domain, array $payload): array
    {
        if (! Schema::hasTable('clickronix_devices')) {
            return ['ok' => false, 'message' => 'Device table missing. Run migrations.'];
        }

        $clientId = trim((string) ($payload['client_id'] ?? $payload['ga4_client_id'] ?? ''));
        $gclid = trim((string) ($payload['gclid'] ?? ''));
        $deviceId = trim((string) ($payload['device_id'] ?? ''));
        $type = Str::limit(trim((string) ($payload['conversion'] ?? $payload['conversion_type'] ?? 'lead')), 64, '');

        $device = null;
        if ($deviceId !== '') {
            $device = ClickronixDevice::query()
                ->where('domain_id', $domain->id)
                ->where('device_id', $deviceId)
                ->first();
        }
        if (! $device && $clientId !== '') {
            $device = ClickronixDevice::query()
                ->where('domain_id', $domain->id)
                ->where('ga4_client_id', $clientId)
                ->orderByDesc('last_paid_click_at')
                ->first();
        }
        if (! $device && $gclid !== '') {
            $device = ClickronixDevice::query()
                ->where('domain_id', $domain->id)
                ->where(function ($q) use ($gclid): void {
                    $q->where('last_gclid', $gclid);
                })
                ->orderByDesc('last_paid_click_at')
                ->first();
        }

        if (! $device) {
            return ['ok' => false, 'message' => 'No matching Clickronix device for this conversion.'];
        }

        $device->conversion_count = (int) $device->conversion_count + 1;
        $device->last_conversion_at = now();
        $device->last_conversion_type = $type !== '' ? $type : 'lead';
        if ($clientId !== '' && ! filled($device->ga4_client_id)) {
            $device->ga4_client_id = Str::limit($clientId, 128, '');
        }

        // Converted visitors are not treated as wasted / exclusion candidates.
        $device->exclusion_candidate = false;
        $device->risk_label = 'Converted';
        $device->risk_reason = 'converted';
        $device->risk_score = min((int) $device->risk_score, 35);
        $device->save();

        return [
            'ok' => true,
            'message' => 'Conversion linked to device.',
            'device_id' => $device->device_id,
            'conversion_count' => (int) $device->conversion_count,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function resolveDevice(Domain $domain, string $token, string $fingerprintId, array $ctx): ClickronixDevice
    {
        $device = null;

        if ($token !== '') {
            $device = ClickronixDevice::query()
                ->where('domain_id', $domain->id)
                ->where('device_token', $token)
                ->first();
        }

        // Do NOT merge different tokens just because fingerprints match (spec).
        if (! $device) {
            $deviceId = PaidDeviceFingerprinter::deviceId(
                $fingerprintId !== '' ? $fingerprintId : ('FP_'.strtoupper(substr(hash('sha256', (string) $domain->id), 0, 12))),
                $ctx['user_agent'] ?? null,
                $token !== '' ? $token : null,
                (int) $domain->id,
            );
            if ($token === '') {
                // Mint a durable token so the next visit can bind without fingerprint merge.
                $token = Str::lower(Str::uuid()->toString());
                $deviceId = PaidDeviceFingerprinter::deviceId($fingerprintId, $ctx['user_agent'] ?? null, $token, (int) $domain->id);
            }

            $device = ClickronixDevice::query()->firstOrCreate(
                ['domain_id' => $domain->id, 'device_id' => $deviceId],
                [
                    'device_token' => $token,
                    'fingerprint_id' => $fingerprintId !== '' ? $fingerprintId : null,
                    'device_confidence' => 0.55,
                ]
            );

            if (! filled($device->device_token)) {
                $device->device_token = $token;
            }
        }

        return $device;
    }

    private function normalizeToken(?string $token): string
    {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }
        // Accept UUID / opaque tokens; reject obvious garbage.
        if (strlen($token) < 8 || strlen($token) > 80) {
            return '';
        }

        return Str::limit($token, 80, '');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function scoreConfidence(ClickronixDevice $device, string $token, string $fingerprintId, array $ctx): float
    {
        $score = 0.40;
        if ($token !== '' && filled($device->device_token) && hash_equals((string) $device->device_token, $token)) {
            $score += 0.35;
        }
        if ($fingerprintId !== '' && filled($device->fingerprint_id)) {
            if (hash_equals((string) $device->fingerprint_id, $fingerprintId)) {
                $score += 0.20;
            } else {
                $sim = PaidDeviceFingerprinter::similarity($device->fingerprint_id, $fingerprintId);
                $score += min(0.15, $sim * 0.15);
            }
        } elseif ($fingerprintId !== '') {
            $score += 0.08;
        }
        if (filled($ctx['ga4_client_id'] ?? null) && filled($device->ga4_client_id)
            && hash_equals((string) $device->ga4_client_id, trim((string) $ctx['ga4_client_id']))) {
            $score += 0.05;
        }

        return round(min(0.99, max(0.05, $score)), 4);
    }

    private function touchIpHistory(ClickronixDevice $device, string $ip): void
    {
        if ($ip === '') {
            return;
        }

        $history = is_array($device->ip_history) ? $device->ip_history : [];
        $ips = [];
        foreach ($history as $row) {
            $prev = trim((string) (is_array($row) ? ($row['ip'] ?? '') : $row));
            if ($prev !== '') {
                $ips[$prev] = true;
            }
        }

        $changed = filled($device->last_ip) && (string) $device->last_ip !== $ip;
        if ($changed) {
            $device->ip_change_count = (int) $device->ip_change_count + 1;
        }

        if (! isset($ips[$ip])) {
            $history[] = ['ip' => $ip, 'at' => now()->toIso8601String()];
            if (count($history) > 40) {
                $history = array_slice($history, -40);
            }
            $device->ip_history = $history;
            $device->ip_count = count(array_unique(array_map(
                fn ($r) => is_array($r) ? (string) ($r['ip'] ?? '') : (string) $r,
                $history
            )));
        }

        $device->last_ip = $ip;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function touchThreatFlags(ClickronixDevice $device, array $ctx): void
    {
        if (! empty($ctx['automation'])) {
            $device->automation_detected = true;
        }
        if (! empty($ctx['proxy'])) {
            $device->proxy = true;
        }
        if (! empty($ctx['vpn'])) {
            $device->vpn = true;
        }
        if (! empty($ctx['datacenter'])) {
            $device->datacenter = true;
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function recordPaidClick(ClickronixDevice $device, array $ctx, bool $isInvalid): void
    {
        $now = now();
        if (! $device->first_paid_click_at) {
            $device->first_paid_click_at = $now;
        }
        $device->last_paid_click_at = $now;
        $device->paid_click_count = (int) $device->paid_click_count + 1;

        $gclid = trim((string) ($ctx['gclid'] ?? ''));
        $gbraid = trim((string) ($ctx['gbraid'] ?? ''));
        $wbraid = trim((string) ($ctx['wbraid'] ?? ''));
        if ($gclid !== '') {
            $device->last_gclid = Str::limit($gclid, 255, '');
        }
        if ($gbraid !== '') {
            $device->last_gbraid = Str::limit($gbraid, 255, '');
        }
        if ($wbraid !== '') {
            $device->last_wbraid = Str::limit($wbraid, 255, '');
        }

        if ($isInvalid) {
            $device->invalid_click_count = (int) $device->invalid_click_count + 1;
        } else {
            $device->valid_click_count = (int) $device->valid_click_count + 1;
        }

        $meta = is_array($device->meta) ? $device->meta : [];
        $meta['last_landing'] = Str::limit((string) ($ctx['landing_page'] ?? ''), 500, '');
        $meta['last_session_id'] = Str::limit((string) ($ctx['session_id'] ?? ''), 120, '');
        $device->meta = $meta;
    }

    /**
     * Spec V1: NOT "2 clicks = fraud". Combined conditions only.
     *
     * @return array{exclusion_candidate: bool, risk_score: int, risk_reason: ?string, risk_label: ?string}
     */
    public function evaluateExclusion(ClickronixDevice $device): array
    {
        if ((int) $device->conversion_count > 0) {
            return [
                'exclusion_candidate' => false,
                'risk_score' => min(35, (int) $device->risk_score),
                'risk_reason' => 'converted',
                'risk_label' => 'Converted',
            ];
        }

        $paid = (int) $device->paid_click_count;
        $confidence = (float) $device->device_confidence;
        $confidencePct = (int) round($confidence * 100);

        $strong = [];
        if ($device->automation_detected) {
            $strong[] = 'automation';
        }
        if ((int) $device->ip_change_count >= 3) {
            $strong[] = 'ip_changes';
        }
        if ((int) $device->invalid_click_count >= 3) {
            $strong[] = 'invalid_clicks';
        }
        if ($device->datacenter || $device->proxy || $device->vpn) {
            $strong[] = 'network_risk';
        }
        if ($paid >= 6 && (int) $device->ip_count >= 4) {
            $strong[] = 'multi_ip_reentry';
        }

        // Low confidence → never auto-exclude (false-positive guard).
        if ($confidencePct < 90) {
            $score = min(70, 20 + ($paid * 6) + (count($strong) * 8));

            return [
                'exclusion_candidate' => false,
                'risk_score' => $score,
                'risk_reason' => $paid >= 2 ? 'low_device_confidence' : 'insufficient_signal',
                'risk_label' => $paid >= 2 ? 'Monitor' : 'Watch',
            ];
        }

        // V1 rule: paid >= 4 AND conversions 0 AND confidence >= 90 AND ≥1 strong signal
        if ($paid >= 4 && $strong !== []) {
            $reason = $device->automation_detected
                ? 'automation'
                : ((int) $device->ip_change_count >= 3 ? 'ip_rotation' : 'repeat_nonconverter');
            $label = match ($reason) {
                'automation' => 'Automation',
                'ip_rotation' => 'Suspicious Repeat Clicker',
                default => 'Repeat Non-Converter',
            };
            $score = min(99, 70 + min(25, $paid * 3) + (count($strong) * 4));

            return [
                'exclusion_candidate' => true,
                'risk_score' => $score,
                'risk_reason' => $reason,
                'risk_label' => $label,
            ];
        }

        if ($paid >= 4 && $confidencePct >= 90) {
            return [
                'exclusion_candidate' => false,
                'risk_score' => min(75, 45 + $paid * 4),
                'risk_reason' => 'repeat_watching',
                'risk_label' => 'Suspicious Repeat Clicker',
            ];
        }

        if ($paid >= 2) {
            return [
                'exclusion_candidate' => false,
                'risk_score' => min(55, 25 + $paid * 5),
                'risk_reason' => 'monitor',
                'risk_label' => 'Monitor',
            ];
        }

        return [
            'exclusion_candidate' => false,
            'risk_score' => max(0, (int) $device->risk_score),
            'risk_reason' => null,
            'risk_label' => null,
        ];
    }

    private function sendGa4ExcludeEvent(Domain $domain, ClickronixDevice $device): void
    {
        $measurementId = trim((string) ($domain->ga4_measurement_id ?? ''));
        $apiSecret = trim((string) ($domain->ga4_api_secret ?? ''));
        $clientId = trim((string) ($device->ga4_client_id ?? ''));

        if ($measurementId === '' || $apiSecret === '' || $clientId === '') {
            // Client tag will fire clickronix_exclude when fire_exclude_event is set.
            return;
        }

        try {
            $url = 'https://www.google-analytics.com/mp/collect?'
                .http_build_query([
                    'measurement_id' => $measurementId,
                    'api_secret' => $apiSecret,
                ]);

            Http::timeout(8)->asJson()->post($url, [
                'client_id' => $clientId,
                'events' => [[
                    'name' => self::EXCLUDE_EVENT,
                    'params' => [
                        'risk_score' => (int) $device->risk_score,
                        'device_confidence' => (int) round(((float) $device->device_confidence) * 100),
                        'paid_clicks' => (int) $device->paid_click_count,
                        'reason' => (string) ($device->risk_reason ?: 'repeat_nonconverter'),
                        // Never send Clickronix DEV_ as Google Ads device id.
                        'engagement_time_msec' => 1,
                    ],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('GA4 clickronix_exclude MP failed', [
                'domain_id' => $domain->id,
                'device_id' => $device->device_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $deviceId, string $token, string $fingerprintId): array
    {
        return [
            'device_id' => $deviceId,
            'device_token' => $token,
            'fingerprint_id' => $fingerprintId,
            'device_confidence' => 0.5,
            'exclusion_candidate' => false,
            'risk_score' => 0,
            'risk_label' => null,
            'risk_reason' => null,
            'fire_exclude_event' => false,
            'ga4_client_id' => null,
            'paid_click_count' => 0,
            'conversion_count' => 0,
            'ip_count' => 0,
            'ip_change_count' => 0,
        ];
    }
}
