<?php

namespace App\Support;

final class GoogleClickAttribution
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, type: string}|null
     */
    public static function resolve(array $data): ?array
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $type) {
            $value = trim((string) ($data[$type] ?? ''));
            if ($value !== '') {
                return ['id' => $value, 'type' => $type];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isPaidTraffic(array $data): bool
    {
        if (self::resolve($data) !== null) {
            return true;
        }

        return trim((string) ($data['utm_campaign'] ?? '')) !== '';
    }
}
