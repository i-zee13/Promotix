<?php

namespace App\Support;

/**
 * Settings → Data Reports catalog (PDF §7 Download Report).
 */
class SettingsDataReportCatalog
{
    public const TYPE_PAID_ADVERTISING = 'paid_advertising';

    public const TYPE_ANALYTICS_DASHBOARD = 'analytics_dashboard';

    public const TYPE_TRAFFIC_CONTROL = 'traffic_control';

    public const TYPE_DETECTION_SESSION = 'detection_session';

    /**
     * @var array<string, array{label: string, supports_column_groups: bool}>
     */
    public const REPORT_TYPES = [
        self::TYPE_PAID_ADVERTISING => [
            'label' => 'Paid Advertising',
            'supports_column_groups' => true,
        ],
        self::TYPE_ANALYTICS_DASHBOARD => [
            'label' => 'Analytics Dashboard',
            'supports_column_groups' => false,
        ],
        self::TYPE_TRAFFIC_CONTROL => [
            'label' => 'Traffic Control',
            'supports_column_groups' => false,
        ],
        self::TYPE_DETECTION_SESSION => [
            'label' => 'Detection / Session Report',
            'supports_column_groups' => true,
        ],
    ];

    /**
     * Spec column groups (traffic, conversion, device, fraud/risk, revenue, events).
     *
     * @var array<string, array{label: string, keys: list<string>}>
     */
    public const COLUMN_GROUPS = [
        'traffic' => [
            'label' => 'Traffic',
            'keys' => ['ip', 'visits', 'last_click_label', 'invalid_clicks', 'valid_clicks', 'domain', 'campaign', 'keyword', 'gclid'],
        ],
        'conversion' => [
            'label' => 'Conversion',
            'keys' => ['ip', 'cta_clicks', 'tel_clicks', 'google_verified_label', 'valid_clicks', 'invalid_clicks', 'last_cta'],
        ],
        'device' => [
            'label' => 'Device',
            'keys' => ['ip', 'device', 'browser', 'browser_version', 'os', 'screen_resolution', 'language', 'visitor_timezone', 'device_fingerprint', 'device_id'],
        ],
        'fraud_risk' => [
            'label' => 'Fraud / Risk',
            'keys' => ['ip', 'threat_group', 'threat_type', 'ads_primary_rule', 'intel_risk_score', 'intel_risk_level', 'intel_confidence', 'intel_evidence', 'intel_vpn', 'intel_proxy', 'intel_tor', 'intel_datacenter', 'intel_block_reason'],
        ],
        'revenue' => [
            'label' => 'Revenue',
            'keys' => ['ip', 'valid_clicks', 'cta_clicks', 'tel_clicks', 'google_verified_label', 'last_path', 'last_cta'],
        ],
        'events' => [
            'label' => 'Events',
            'keys' => ['ip', 'session_id', 'cta_clicks', 'tel_clicks', 'page_changes', 'session_recording', 'status', 'last_path'],
        ],
    ];

    /**
     * @return list<array{id: string, label: string, supports_column_groups: bool}>
     */
    public static function reportTypeOptions(): array
    {
        $out = [];
        foreach (self::REPORT_TYPES as $id => $meta) {
            $out[] = [
                'id' => $id,
                'label' => $meta['label'],
                'supports_column_groups' => (bool) $meta['supports_column_groups'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function columnGroupOptions(): array
    {
        $out = [];
        foreach (self::COLUMN_GROUPS as $id => $meta) {
            $out[] = [
                'id' => $id,
                'label' => $meta['label'],
            ];
        }

        return $out;
    }

    public static function normalizeType(?string $type): string
    {
        $type = trim((string) $type);
        $legacy = [
            'dashboard_ips' => self::TYPE_PAID_ADVERTISING,
            'advanced' => self::TYPE_PAID_ADVERTISING,
            'bot' => self::TYPE_ANALYTICS_DASHBOARD,
            'bot_advanced' => self::TYPE_TRAFFIC_CONTROL,
        ];
        if (isset($legacy[$type])) {
            return $legacy[$type];
        }

        return isset(self::REPORT_TYPES[$type]) ? $type : self::TYPE_PAID_ADVERTISING;
    }

    public static function normalizeFormat(?string $format): string
    {
        $format = strtolower(trim((string) $format));

        return in_array($format, ['csv', 'xlsx', 'pdf'], true) ? $format : 'csv';
    }

    /**
     * @return list<string>|null
     */
    public static function resolveColumnKeys(?string $columnGroup): ?array
    {
        $id = trim((string) $columnGroup);
        if ($id === '' || $id === 'all') {
            return null;
        }

        if (isset(self::COLUMN_GROUPS[$id])) {
            return self::COLUMN_GROUPS[$id]['keys'];
        }

        // Fall back to Advanced View groups when an older id is still selected.
        return ClickronixTrafficReport::resolveExportKeys($id, null);
    }
}
