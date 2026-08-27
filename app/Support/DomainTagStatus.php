<?php

namespace App\Support;

use App\Models\Domain;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Tag install status labels for Domain setup / Finish Setup cards (PDF §8).
 */
class DomainTagStatus
{
    public const INSTALLED = 'Installed';

    public const NOT_DETECTED = 'Not detected';

    /**
     * @return array{status: string, label: string, last_seen_at: ?string, last_seen_human: string, installed: bool}
     */
    public static function forDomain(?Domain $domain): array
    {
        $raw = $domain?->getAttributes()['last_seen_at'] ?? null;
        $lastSeen = null;
        if ($raw instanceof CarbonInterface) {
            $lastSeen = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            try {
                $lastSeen = Carbon::parse($raw);
            } catch (\Throwable) {
                $lastSeen = null;
            }
        }

        $installed = false;
        if ($domain !== null) {
            $attrs = $domain->getAttributes();
            $installed = (bool) ($attrs['tag_connected'] ?? false);
        }

        return self::describe(
            installed: $installed,
            lastSeenAt: $lastSeen,
        );
    }

    /**
     * @return array{status: string, label: string, last_seen_at: ?string, last_seen_human: string, installed: bool}
     */
    public static function describe(bool $installed, ?CarbonInterface $lastSeenAt = null): array
    {
        return [
            'status' => $installed ? 'installed' : 'not_detected',
            'label' => $installed ? self::INSTALLED : self::NOT_DETECTED,
            'last_seen_at' => $lastSeenAt?->toIso8601String(),
            'last_seen_human' => $lastSeenAt ? $lastSeenAt->diffForHumans() : '—',
            'installed' => $installed,
        ];
    }
}
