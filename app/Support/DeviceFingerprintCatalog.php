<?php

namespace App\Support;

/**
 * Device fingerprint scan catalog (client tag → FP_ id).
 *
 * Role is relative entropy for identity matching, not a risk score.
 */
final class DeviceFingerprintCatalog
{
    /**
     * @return list<array{group: string, key: string, label: string, role: string}>
     */
    public static function fields(): array
    {
        return [
            ['group' => 'Browser', 'key' => 'browser_family', 'label' => 'Browser Family', 'role' => 'Medium'],
            ['group' => 'Browser', 'key' => 'browser_major', 'label' => 'Browser Major Version', 'role' => 'Medium'],
            ['group' => 'Browser', 'key' => 'user_agent', 'label' => 'User Agent', 'role' => 'Medium'],
            ['group' => 'Browser', 'key' => 'client_hints', 'label' => 'Client Hints / Platform', 'role' => 'Medium'],
            ['group' => 'OS', 'key' => 'os_family', 'label' => 'OS Family', 'role' => 'Medium'],
            ['group' => 'OS', 'key' => 'os_version', 'label' => 'OS Version', 'role' => 'Medium'],
            ['group' => 'Device', 'key' => 'device_type', 'label' => 'Device Type', 'role' => 'Medium'],
            ['group' => 'Display', 'key' => 'screen_size', 'label' => 'Screen Size', 'role' => 'Medium'],
            ['group' => 'Display', 'key' => 'pixel_ratio', 'label' => 'Pixel Ratio', 'role' => 'Medium'],
            ['group' => 'Interaction', 'key' => 'touch_points', 'label' => 'Touch Points', 'role' => 'Medium'],
            ['group' => 'Hardware', 'key' => 'hardware_concurrency', 'label' => 'CPU / Hardware Concurrency', 'role' => 'Medium'],
            ['group' => 'Hardware', 'key' => 'device_memory', 'label' => 'Device Memory', 'role' => 'Medium'],
            ['group' => 'Rendering', 'key' => 'webgl_vendor', 'label' => 'WebGL Vendor', 'role' => 'Strong'],
            ['group' => 'Rendering', 'key' => 'webgl_renderer', 'label' => 'WebGL Renderer', 'role' => 'Strong'],
            ['group' => 'Rendering', 'key' => 'webgl_hash', 'label' => 'WebGL Capability Hash', 'role' => 'Strong'],
            ['group' => 'Rendering', 'key' => 'canvas_hash', 'label' => 'Canvas Hash', 'role' => 'Strong'],
            ['group' => 'Locale', 'key' => 'language', 'label' => 'Language', 'role' => 'Weak'],
            ['group' => 'Locale', 'key' => 'timezone', 'label' => 'Timezone', 'role' => 'Weak/Medium'],
            ['group' => 'Input', 'key' => 'pointer_type', 'label' => 'Pointer Type', 'role' => 'Low/Medium'],
            ['group' => 'Browser Capabilities', 'key' => 'api_profile', 'label' => 'Feature/API Profile', 'role' => 'Medium'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $signals
     * @return list<array{group: string, key: string, label: string, role: string, value: string}>
     */
    public static function rows(?array $signals): array
    {
        $signals = is_array($signals) ? $signals : [];
        $rows = [];
        foreach (self::fields() as $field) {
            $value = trim((string) ($signals[$field['key']] ?? ''));
            $rows[] = $field + ['value' => $value !== '' ? $value : '—'];
        }

        return $rows;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, string>
     */
    public static function sanitize(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $allowed = [];
        foreach (self::fields() as $field) {
            $allowed[$field['key']] = true;
        }

        $out = [];
        foreach ($raw as $key => $value) {
            $key = is_string($key) ? $key : '';
            if ($key === '' || ! isset($allowed[$key])) {
                continue;
            }
            $text = trim(is_scalar($value) ? (string) $value : '');
            if ($text === '') {
                continue;
            }
            $out[$key] = mb_substr($text, 0, 400);
        }

        return $out;
    }
}
