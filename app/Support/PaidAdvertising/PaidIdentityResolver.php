<?php

namespace App\Support\PaidAdvertising;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Manual v3 Steps 2–3: create/read Visitor + Browser IDs, fingerprint/device,
 * and maintain a canonical paid_identity row + links.
 *
 * Phase A keeps matching practical: signed first-party cookies + UA/fingerprint
 * similarity. IP is linked as evidence only and never treated as a person.
 */
class PaidIdentityResolver
{
    public const COOKIE_VISITOR = 'cx_vid';

    public const COOKIE_BROWSER = 'cx_bid';

    public function resolve(
        Request $request,
        int $domainId,
        string $ip,
        ?string $sessionId = null,
        ?string $clientFingerprint = null,
    ): ResolvedPaidIdentity {
        $visitorId = $this->readOrCreateCookieId($request, self::COOKIE_VISITOR);
        $browserId = $this->readOrCreateCookieId($request, self::COOKIE_BROWSER);
        $fingerprintId = $this->fingerprintId($request, $clientFingerprint, $browserId);
        $deviceId = $this->deviceId($fingerprintId, $browserId, $request);

        [$confidence, $band] = $this->confidence($visitorId, $browserId, $fingerprintId, $clientFingerprint);

        if (! $this->tableReady('paid_identities')) {
            return new ResolvedPaidIdentity(
                publicId: 'PID_'.strtoupper(substr(hash('sha256', $domainId.'|'.$deviceId.'|'.$browserId), 0, 10)),
                visitorId: $visitorId,
                browserId: $browserId,
                deviceId: $deviceId,
                fingerprintId: $fingerprintId,
                confidence: $confidence,
                confidenceBand: $band,
            );
        }

        $row = $this->findOrCreateIdentityRow(
            $domainId,
            $visitorId,
            $browserId,
            $deviceId,
            $fingerprintId,
            $confidence,
            $band,
        );

        $this->touchLinks($row->id, [
            'visitor' => $visitorId,
            'browser' => $browserId,
            'device' => $deviceId,
            'fingerprint' => $fingerprintId,
            'ip' => $ip,
            'session' => $sessionId,
        ]);

        return new ResolvedPaidIdentity(
            publicId: (string) $row->public_id,
            visitorId: $visitorId,
            browserId: $browserId,
            deviceId: $deviceId,
            fingerprintId: $fingerprintId,
            confidence: (float) $row->identity_confidence,
            confidenceBand: (string) $row->confidence_band,
            knownFraud: (bool) $row->known_fraud,
            rowId: (int) $row->id,
        );
    }

    /**
     * Queue cookies on the response (call from controller after resolve).
     *
     * @return list<array{name:string,value:string,minutes:int}>
     */
    public function cookiesToQueue(ResolvedPaidIdentity $identity): array
    {
        $year = 60 * 24 * 365;

        return [
            ['name' => self::COOKIE_VISITOR, 'value' => (string) $identity->visitorId, 'minutes' => $year],
            ['name' => self::COOKIE_BROWSER, 'value' => (string) $identity->browserId, 'minutes' => $year],
        ];
    }

    private function readOrCreateCookieId(Request $request, string $name): string
    {
        $existing = trim((string) $request->cookie($name, ''));
        if ($existing !== '' && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $existing)) {
            return $existing;
        }

        $fromBody = trim((string) $request->input($name === self::COOKIE_VISITOR ? 'visitor_id' : 'browser_id', ''));
        if ($fromBody !== '' && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $fromBody)) {
            return $fromBody;
        }

        return strtoupper(Str::random(16));
    }

    private function fingerprintId(Request $request, ?string $clientFingerprint, string $browserId): string
    {
        $client = trim((string) $clientFingerprint);
        if ($client !== '') {
            return 'FP_'.strtoupper(substr(hash('sha256', $client), 0, 12));
        }

        $ua = (string) $request->userAgent();
        $lang = (string) $request->header('Accept-Language', '');
        $basis = $browserId.'|'.$ua.'|'.$lang;

        return 'FP_'.strtoupper(substr(hash('sha256', $basis), 0, 12));
    }

    private function deviceId(string $fingerprintId, string $browserId, Request $request): string
    {
        // Phase A: device = stable hash of fingerprint + coarse UA family (not IP).
        $ua = strtolower((string) $request->userAgent());
        $family = 'other';
        foreach (['iphone', 'ipad', 'android', 'windows', 'mac os', 'linux', 'cros'] as $needle) {
            if (str_contains($ua, $needle)) {
                $family = str_replace(' ', '', $needle);
                break;
            }
        }

        return 'DEV_'.strtoupper(substr(hash('sha256', $fingerprintId.'|'.$family.'|'.$browserId), 0, 12));
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function confidence(?string $visitorId, ?string $browserId, ?string $fingerprintId, ?string $clientFingerprint): array
    {
        if ($visitorId && $browserId && $fingerprintId && filled($clientFingerprint)) {
            return [0.97, 'very_high'];
        }
        if ($visitorId && $browserId && $fingerprintId) {
            return [0.90, 'high'];
        }
        if ($browserId && $fingerprintId) {
            return [0.78, 'medium'];
        }
        if ($browserId || $visitorId) {
            return [0.55, 'low'];
        }

        return [0.35, 'unknown'];
    }

    private function findOrCreateIdentityRow(
        int $domainId,
        string $visitorId,
        string $browserId,
        string $deviceId,
        string $fingerprintId,
        float $confidence,
        string $band,
    ): object {
        $existing = DB::table('paid_identities')
            ->where('domain_id', $domainId)
            ->where(function ($q) use ($deviceId, $browserId, $visitorId) {
                $q->where('device_id', $deviceId)
                    ->orWhere('browser_id', $browserId)
                    ->orWhere('visitor_id', $visitorId);
            })
            ->orderByDesc('last_seen_at')
            ->first();

        $now = now();

        if ($existing) {
            DB::table('paid_identities')->where('id', $existing->id)->update([
                'visitor_id' => $visitorId,
                'browser_id' => $browserId,
                'device_id' => $deviceId,
                'fingerprint_id' => $fingerprintId,
                'identity_confidence' => max((float) $existing->identity_confidence, $confidence),
                'confidence_band' => $confidence >= (float) $existing->identity_confidence ? $band : $existing->confidence_band,
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('paid_identities')->where('id', $existing->id)->first();
        }

        $publicId = 'PID_'.strtoupper(Str::random(10));
        $id = DB::table('paid_identities')->insertGetId([
            'public_id' => $publicId,
            'domain_id' => $domainId,
            'visitor_id' => $visitorId,
            'browser_id' => $browserId,
            'device_id' => $deviceId,
            'fingerprint_id' => $fingerprintId,
            'identity_confidence' => $confidence,
            'confidence_band' => $band,
            'known_fraud' => false,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('paid_identities')->where('id', $id)->first();
    }

    /**
     * @param  array<string, ?string>  $links
     */
    private function touchLinks(int $paidIdentityId, array $links): void
    {
        if (! $this->tableReady('paid_identity_links')) {
            return;
        }

        $now = now();
        foreach ($links as $type => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $existing = DB::table('paid_identity_links')
                ->where('paid_identity_id', $paidIdentityId)
                ->where('link_type', $type)
                ->where('link_value', $value)
                ->first();

            if ($existing) {
                DB::table('paid_identity_links')->where('id', $existing->id)->update([
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('paid_identity_links')->insert([
                'paid_identity_id' => $paidIdentityId,
                'link_type' => $type,
                'link_value' => $value,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
