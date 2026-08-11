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
        $fingerprintId = PaidDeviceFingerprinter::fingerprintId(
            $clientFingerprint,
            $browserId,
            $request->userAgent(),
            $request->header('Accept-Language'),
        );
        $deviceId = PaidDeviceFingerprinter::deviceId($fingerprintId, $request->userAgent());

        [$confidence, $band] = $this->confidence($visitorId, $browserId, $fingerprintId, $clientFingerprint);
        $fpSimilarity = 1.0;
        $rematched = false;

        if (! $this->tableReady('paid_identities')) {
            return new ResolvedPaidIdentity(
                publicId: 'PID_'.strtoupper(substr(hash('sha256', $domainId.'|'.$deviceId.'|'.$browserId), 0, 10)),
                visitorId: $visitorId,
                browserId: $browserId,
                deviceId: $deviceId,
                fingerprintId: $fingerprintId,
                confidence: $confidence,
                confidenceBand: $band,
                fpSimilarity: $fpSimilarity,
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
            $clientFingerprint,
        );

        if (! empty($row->_rematched_via_fingerprint)) {
            $rematched = true;
            $fpSimilarity = (float) ($row->_fp_similarity ?? 1.0);
            // Cookie churn but same fingerprint → treat as high confidence rematch.
            if (PaidDeviceFingerprinter::isHighSimilarity($fpSimilarity)) {
                $confidence = max($confidence, 0.93);
                $band = $confidence >= 0.95 ? 'very_high' : 'high';
            }
            $deviceId = (string) ($row->device_id ?: $deviceId);
        }

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
            confidence: max($confidence, (float) $row->identity_confidence),
            confidenceBand: $band,
            knownFraud: (bool) $row->known_fraud,
            rowId: (int) $row->id,
            fpSimilarity: $fpSimilarity,
            rematchedViaFingerprint: $rematched,
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
        return PaidDeviceFingerprinter::fingerprintId(
            $clientFingerprint,
            $browserId,
            $request->userAgent(),
            $request->header('Accept-Language'),
        );
    }

    private function deviceId(string $fingerprintId, string $browserId, Request $request): string
    {
        return PaidDeviceFingerprinter::deviceId($fingerprintId, $request->userAgent());
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
        ?string $clientFingerprint = null,
    ): object {
        // 1) Exact fingerprint rematch (cookie Visitor/Browser reset, same device telemetry).
        $byFingerprint = DB::table('paid_identities')
            ->where('domain_id', $domainId)
            ->where('fingerprint_id', $fingerprintId)
            ->orderByDesc('last_seen_at')
            ->first();

        if ($byFingerprint) {
            $sameCookies = ((string) $byFingerprint->visitor_id === $visitorId)
                && ((string) $byFingerprint->browser_id === $browserId);
            $similarity = $sameCookies ? 1.0 : 0.96;
            if (filled($clientFingerprint)) {
                $similarity = max($similarity, 1.0);
            }

            DB::table('paid_identities')->where('id', $byFingerprint->id)->update([
                'visitor_id' => $visitorId,
                'browser_id' => $browserId,
                // Keep stable device id from first sighting when rematching by FP.
                'device_id' => $byFingerprint->device_id ?: $deviceId,
                'fingerprint_id' => $fingerprintId,
                'identity_confidence' => max((float) $byFingerprint->identity_confidence, $confidence),
                'confidence_band' => $confidence >= (float) $byFingerprint->identity_confidence ? $band : $byFingerprint->confidence_band,
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

            $fresh = DB::table('paid_identities')->where('id', $byFingerprint->id)->first();

            return (object) array_merge((array) $fresh, [
                '_rematched_via_fingerprint' => ! $sameCookies,
                '_fp_similarity' => $similarity,
            ]);
        }

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

            $fresh = DB::table('paid_identities')->where('id', $existing->id)->first();

            return (object) array_merge((array) $fresh, [
                '_rematched_via_fingerprint' => false,
                '_fp_similarity' => 1.0,
            ]);
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

        $fresh = DB::table('paid_identities')->where('id', $id)->first();

        return (object) array_merge((array) $fresh, [
            '_rematched_via_fingerprint' => false,
            '_fp_similarity' => 1.0,
        ]);
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
