<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Canonical Device ID labels shared by Visitor Journey, Traffic Control, and Advanced View.
 * Keeps display + search aligned so a copied Device ID can be found elsewhere.
 */
final class DeviceIdLabel
{
    /**
     * Prefer real DEV_ tokens; never surface unknown_* placeholders in the UI.
     */
    public static function format(?string $deviceRaw, ?string $fingerprintRaw = null, ?string $ip = null): string
    {
        $deviceRaw = trim((string) $deviceRaw);
        $fingerprintRaw = trim((string) $fingerprintRaw);
        $ip = trim((string) $ip);

        if ($deviceRaw !== '') {
            if (str_starts_with($deviceRaw, 'unknown_')) {
                return 'DEV_'.strtoupper(substr(hash('sha256', $deviceRaw), 0, 12));
            }
            if (str_starts_with($deviceRaw, 'FP_')) {
                return 'DEV_'.substr($deviceRaw, 3);
            }
            if (str_starts_with($deviceRaw, 'dev_')) {
                return 'DEV_'.substr($deviceRaw, 4);
            }

            return $deviceRaw;
        }

        if ($fingerprintRaw !== '') {
            if (str_starts_with($fingerprintRaw, 'DEV_') || str_starts_with($fingerprintRaw, 'dev_')) {
                return str_starts_with($fingerprintRaw, 'dev_')
                    ? 'DEV_'.substr($fingerprintRaw, 4)
                    : $fingerprintRaw;
            }
            if (str_starts_with($fingerprintRaw, 'FP_')) {
                return 'DEV_'.substr($fingerprintRaw, 3);
            }

            return 'DEV_'.strtoupper(substr(hash('sha256', $fingerprintRaw), 0, 12));
        }

        if ($ip !== '') {
            return 'DEV_'.strtoupper(substr(hash('sha256', 'ip|'.$ip), 0, 12));
        }

        return 'DEV_UNKNOWN';
    }

    /**
     * Variants to try when searching DB columns for a UI Device ID.
     *
     * @return list<string>
     */
    public static function searchNeedles(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $needles = [$term];
        $upper = strtoupper($term);
        $lower = strtolower($term);
        $needles[] = $upper;
        $needles[] = $lower;

        if (preg_match('/^dev_(.+)$/i', $term, $m)) {
            $needles[] = 'DEV_'.$m[1];
            $needles[] = 'dev_'.$m[1];
            $needles[] = 'FP_'.$m[1];
            $needles[] = $m[1];
        }

        if (preg_match('/^fp_(.+)$/i', $term, $m)) {
            $needles[] = 'DEV_'.$m[1];
            $needles[] = 'FP_'.$m[1];
        }

        return array_values(array_unique(array_filter($needles, static fn ($v) => $v !== '')));
    }

    public static function looksLikeDeviceId(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^(DEV_|dev_|FP_|fp_)[A-Za-z0-9_-]+$/i', $value);
    }

    /**
     * Match visits rows for a Device ID / fingerprint / session pasted from Journey UI.
     * Covers raw column values plus synthetic DEV_ labels from format() (fp/unknown_/ip hashes).
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder  $query
     */
    public static function applyVisitIdentityFilter($query, string $term, string $prefix = 'visits'): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $p = $prefix !== '' ? $prefix.'.' : '';
        $needles = self::searchNeedles($term);
        if ($needles === []) {
            $needles = [$term];
        }
        $isDevice = self::looksLikeDeviceId($term);

        $query->where(function ($match) use ($term, $needles, $p, $isDevice): void {
            // Device IDs must not fall through to ip LIKE (contaminates with unrelated IPs).
            if (! $isDevice) {
                $match->where($p.'ip', 'like', '%'.$term.'%');
            } else {
                $match->whereRaw('0 = 1');
            }

            foreach ($needles as $needle) {
                if (Schema::hasColumn('visits', 'device_id')) {
                    if ($isDevice) {
                        // Exact / prefix only — never %DEV_% (that was matching random IPs via bad fallbacks).
                        $match->orWhere($p.'device_id', $needle)
                            ->orWhere($p.'device_id', 'like', $needle.'%');
                    } else {
                        $match->orWhere($p.'device_id', 'like', '%'.$needle.'%');
                    }
                }
                if (Schema::hasColumn('visits', 'fingerprint_id')) {
                    if ($isDevice) {
                        $match->orWhere($p.'fingerprint_id', $needle)
                            ->orWhere($p.'fingerprint_id', 'like', $needle.'%');
                    } else {
                        $match->orWhere($p.'fingerprint_id', 'like', '%'.$needle.'%');
                    }
                }
                if (Schema::hasColumn('visits', 'session_id')) {
                    $match->orWhere($p.'session_id', 'like', '%'.$needle.'%');
                }
            }

            // Reverse format() synthetic labels: DEV_ + sha256(fp|unknown_|ip)[:12]
            if (preg_match('/^DEV_([A-Fa-f0-9]{12})$/i', $term, $m)) {
                $token = strtoupper($m[1]);
                if (Schema::hasColumn('visits', 'fingerprint_id')) {
                    $match->orWhereRaw(
                        "{$p}fingerprint_id != '' AND UPPER(SUBSTRING(SHA2({$p}fingerprint_id, 256), 1, 12)) = ?",
                        [$token]
                    );
                }
                if (Schema::hasColumn('visits', 'device_id')) {
                    $match->orWhereRaw(
                        "({$p}device_id LIKE 'unknown_%' AND UPPER(SUBSTRING(SHA2({$p}device_id, 256), 1, 12)) = ?)",
                        [$token]
                    );
                }
                $match->orWhereRaw(
                    "UPPER(SUBSTRING(SHA2(CONCAT('ip|', {$p}ip), 256), 1, 12)) = ?",
                    [$token]
                );
            }
        });
    }
}
