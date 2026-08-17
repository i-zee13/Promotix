<?php

namespace App\Support;

/**
 * Clickronix client Traffic Report template (42 columns).
 * Used by Paid Marketing Advanced CSV / XLSX exports.
 * When a column group (or explicit columns list) is selected in Advanced View,
 * export uses that sheet subset instead of the full 42-column template.
 */
class ClickronixTrafficReport
{
    /**
     * UI / sheet labels for Advanced View keys.
     *
     * @var array<string, string>
     */
    public const COLUMN_LABELS = [
        'ip' => 'IP Address',
        'visits' => 'Visits',
        'domain' => 'Domain',
        'campaign' => 'Campaigns',
        'gclid' => 'GCLID',
        'gbraid' => 'GBRAID',
        'wbraid' => 'WBRAID',
        'session_id' => 'Session ID',
        'device_fingerprint' => 'Fingerprint',
        'device' => 'Device',
        'browser' => 'Browser',
        'browser_version' => 'Browser Version',
        'os' => 'OS',
        'screen_resolution' => 'Screen',
        'language' => 'Language',
        'visitor_timezone' => 'Timezone',
        'last_click_label' => 'Last Click',
        'threat_group' => 'Threat Group',
        'threat_type' => 'Threat Type',
        'country' => 'Country',
        'invalid_clicks' => 'Invalid',
        'valid_clicks' => 'Valid',
        'cta_clicks' => 'CTA Clicks',
        'tel_clicks' => 'Tel Clicks',
        'page_changes' => 'Page Changes',
        'google_verified_label' => 'Google Verified',
        'session_recording' => 'Recording',
        'status' => 'Status',
        'intel_region' => 'Region',
        'intel_city' => 'City',
        'intel_latitude' => 'Latitude',
        'intel_longitude' => 'Longitude',
        'intel_asn' => 'ASN',
        'intel_asn_org' => 'ASN Organization',
        'intel_isp' => 'ISP',
        'intel_network_range' => 'Network Range',
        'intel_routed_prefix' => 'Routed Prefix',
        'intel_allocated_range' => 'Allocated Range',
        'intel_range_note' => 'Range Note',
        'intel_vpn' => 'VPN',
        'intel_proxy' => 'Proxy',
        'intel_tor' => 'Tor',
        'intel_datacenter' => 'Datacenter',
        'intel_risk_score' => 'Risk Score',
        'intel_risk_level' => 'Risk Level',
        'intel_confidence' => 'Confidence',
        'intel_evidence' => 'Evidence',
        'intel_checked_at' => 'Checked At',
        'intel_error' => 'Error',
        'intel_ip_need_blockation' => 'IP Need Blockation',
        'intel_blockation_type' => 'Blockation Type',
        'intel_block_reason' => 'Block Reason',
        'intel_device_action' => 'Device Action',
        'intel_provider_type' => 'Provider Type',
        'intel_matched_provider' => 'Matched Provider',
        'intel_matched_dataset' => 'Matched Dataset',
        'intel_cloud_provider' => 'Cloud Provider',
        'device_id' => 'Device ID',
        'visitor_id' => 'Visitor ID',
        'browser_id' => 'Browser ID',
        'fingerprint_id' => 'Fingerprint ID',
        'paid_identity_id' => 'Paid Identity ID',
        'identity_confidence' => 'Identity Confidence',
        'keyword' => 'Keyword',
        'ads_primary_rule' => 'Primary Detection',
        'block_status' => 'Block Status',
        'first_click_at' => 'First Click (ISO)',
        'last_click_at' => 'Last Click (ISO)',
        'first_click_label' => 'First Click',
        'last_click_datetime_label' => 'Last Click Date/Time',
        'session_count' => 'Session Count',
        'last_path' => 'Last Page',
        'last_cta' => 'Last CTA',
        'clicks_60m' => 'Clicks 60m',
        'paid_risk_score' => 'Paid Risk Score',
        'traffic_status' => 'Traffic Status',
        'block_scope' => 'Block Scope',
        'ip_exclusion' => 'IP Exclusion',
        'action_taken' => 'Action Taken',
        'google_click_id' => 'Google Click ID',
        'google_click_type' => 'Google Click Type',
        'manual_decision' => 'Manual Decision',
        'manual_decision_reason' => 'Manual Decision Reason',
        'original_threat_group' => 'Original Threat Group',
        'original_threat_type' => 'Original Threat Type',
        'vpn_hits' => 'VPN Hits',
        'data_center_hits' => 'Datacenter Hits',
        'session_recording_id' => 'Session Recording ID',
    ];

    /**
     * Advanced View main groups (same order / keys as the UI sheet groups).
     *
     * @var array<string, array{label: string, keys: list<string>}>
     */
    public const COLUMN_GROUPS = [
        'paid_identity' => [
            'label' => 'Paid Identity',
            'keys' => ['ip', 'paid_identity_id', 'visitor_id', 'device_id', 'browser_id', 'fingerprint_id', 'device_fingerprint', 'session_id', 'identity_confidence'],
        ],
        'attribution' => [
            'label' => 'Attribution',
            'keys' => ['ip', 'domain', 'campaign', 'keyword', 'gclid', 'gbraid', 'wbraid', 'google_verified_label'],
        ],
        'click_windows' => [
            'label' => 'Click Windows',
            'keys' => ['ip', 'visits', 'last_click_label', 'invalid_clicks', 'valid_clicks', 'cta_clicks', 'tel_clicks', 'page_changes'],
        ],
        'ip_intelligence' => [
            'label' => 'IP Intelligence',
            'keys' => ['ip', 'country', 'intel_region', 'intel_city', 'intel_asn', 'intel_asn_org', 'intel_isp', 'intel_network_range', 'intel_routed_prefix', 'intel_allocated_range', 'intel_provider_type', 'intel_vpn', 'intel_proxy', 'intel_tor', 'intel_datacenter', 'intel_risk_score', 'intel_risk_level', 'intel_confidence', 'intel_evidence', 'intel_ip_need_blockation', 'intel_block_reason'],
        ],
        'device_browser' => [
            'label' => 'Device / Browser',
            'keys' => ['ip', 'device', 'browser', 'browser_version', 'os', 'screen_resolution', 'language', 'visitor_timezone', 'device_fingerprint'],
        ],
        'session_behavior' => [
            'label' => 'Session / Behavior',
            'keys' => ['ip', 'session_id', 'cta_clicks', 'tel_clicks', 'page_changes', 'session_recording', 'status'],
        ],
        'conversion_lead' => [
            'label' => 'Conversion / Lead',
            'keys' => ['ip', 'cta_clicks', 'tel_clicks', 'google_verified_label', 'valid_clicks', 'invalid_clicks'],
        ],
        'detection_scoring' => [
            'label' => 'Detection / Scoring',
            'keys' => ['ip', 'threat_group', 'threat_type', 'ads_primary_rule', 'intel_risk_score', 'intel_risk_level', 'intel_confidence', 'intel_evidence', 'intel_block_reason'],
        ],
        'enforcement_review' => [
            'label' => 'Enforcement / Review',
            'keys' => ['ip', 'status', 'block_status', 'intel_ip_need_blockation', 'intel_blockation_type', 'intel_block_reason', 'intel_device_action'],
        ],
        'repeat_click' => [
            'label' => 'Repeat Click Detection',
            'keys' => ['ip', 'visits', 'invalid_clicks', 'valid_clicks', 'threat_group', 'threat_type', 'ads_primary_rule', 'last_click_label', 'device_id', 'identity_confidence'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'IP Address',
            'Visits',
            'Invalid Clicks',
            'Valid Clicks',
            'Google Verified',
            'Risk Level',
            'Risk Score',
            'Block Status',
            'Block Reason',
            'Fingerprint ID',
            'Session Count',
            'Latest Session ID',
            'Device Type',
            'Browser',
            'Operating System',
            'ASN',
            'ASN Organization',
            'ISP',
            'Provider Type',
            'VPN',
            'Proxy',
            'Tor',
            'Datacenter',
            'Evidence',
            'Confidence',
            'First Click Time',
            'Last Click Time',
            'Total CTA Clicks',
            'Total Tel Clicks',
            'Total Page Changes',
            'Country',
            'Region',
            'City',
            'Timezone',
            'Domain',
            'Campaign',
            'GCLID',
            'GBRAID',
            'WBRAID',
            'Last Page',
            'Last CTA',
            'Checked At',
        ];
    }

    /**
     * Resolve export column keys from request params.
     * Prefer explicit `columns` list (matches UI); fall back to named group; else full template.
     *
     * @return list<string>|null Null means use the full 42-column Clickronix template.
     */
    public static function resolveExportKeys(?string $columnGroup, ?string $columnsCsv): ?array
    {
        if (strtolower(trim((string) $columnsCsv)) === 'all') {
            return array_keys(self::COLUMN_LABELS);
        }

        $fromCsv = self::parseColumnsCsv($columnsCsv);
        if ($fromCsv !== []) {
            return $fromCsv;
        }

        $groupId = trim((string) $columnGroup);
        if ($groupId !== '' && isset(self::COLUMN_GROUPS[$groupId])) {
            $keys = self::COLUMN_GROUPS[$groupId]['keys'];

            return array_values(array_unique(array_merge(['ip'], array_values(array_filter(
                $keys,
                static fn (string $k): bool => $k !== 'ip'
            )))));
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function parseColumnsCsv(?string $columnsCsv): array
    {
        if ($columnsCsv === null || trim($columnsCsv) === '') {
            return [];
        }

        $allowed = array_flip(array_keys(self::COLUMN_LABELS));
        $keys = [];
        foreach (explode(',', $columnsCsv) as $raw) {
            $key = trim($raw);
            if ($key === '' || ! isset($allowed[$key])) {
                continue;
            }
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        if ($keys !== [] && ! in_array('ip', $keys, true)) {
            array_unshift($keys, 'ip');
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function headersForKeys(array $keys): array
    {
        return array_map(
            static fn (string $key): string => self::COLUMN_LABELS[$key] ?? $key,
            $keys
        );
    }

    /**
     * Map a formatDetailedVisit() row into Clickronix column order.
     *
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public static function valuesFromDetailedVisit(array $row): array
    {
        $blockStatus = (string) ($row['status'] ?? $row['risk_summary']['status'] ?? 'Valid');
        $blockReason = (string) ($row['intel_block_reason']
            ?? $row['threat_group']
            ?? '');
        if ($blockStatus === 'Valid') {
            $blockReason = $blockReason !== '' ? $blockReason : '';
        }

        return [
            $row['ip'] ?? '',
            (int) ($row['visits'] ?? 0),
            (int) ($row['invalid_clicks'] ?? 0),
            (int) ($row['valid_clicks'] ?? 0),
            $row['google_verified_label'] ?? '—',
            $row['intel_risk_level'] ?? ($row['risk_summary']['level'] ?? ''),
            $row['intel_risk_score'] ?? ($row['risk_summary']['score'] ?? ''),
            $blockStatus,
            $blockReason,
            $row['device_fingerprint'] ?? '',
            (int) ($row['session_count'] ?? 0),
            $row['session_id'] ?? '',
            $row['device'] ?? '',
            $row['browser'] ?? '',
            $row['os'] ?? '',
            $row['intel_asn'] ?? '',
            $row['intel_asn_org'] ?? '',
            $row['intel_isp'] ?? '',
            $row['intel_provider_type'] ?? ($row['intel_connection_type'] ?? ''),
            $row['intel_vpn'] ?? 'No',
            $row['intel_proxy'] ?? 'No',
            $row['intel_tor'] ?? 'No',
            $row['intel_datacenter'] ?? 'No',
            $row['intel_evidence'] ?? '',
            $row['intel_confidence'] ?? '',
            $row['first_click_label'] ?? '',
            $row['last_click_datetime_label'] ?? ($row['last_click_label'] ?? ''),
            (int) ($row['cta_clicks'] ?? 0),
            (int) ($row['tel_clicks'] ?? 0),
            (int) ($row['page_changes'] ?? 0),
            $row['country'] ?? '',
            $row['intel_region'] ?? '',
            $row['intel_city'] ?? '',
            $row['visitor_timezone'] ?? '',
            $row['domain'] ?? '',
            $row['campaign'] ?? '',
            $row['gclid'] ?? '',
            $row['gbraid'] ?? '',
            $row['wbraid'] ?? '',
            $row['last_path'] ?? '',
            $row['last_cta'] ?? '',
            $row['intel_checked_at'] ?? '',
        ];
    }

    /**
     * Map a formatDetailedVisit() row to the selected Advanced View keys (sheet group).
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    public static function valuesForKeys(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[] = self::valueForKey($row, $key);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function valueForKey(array $row, string $key): mixed
    {
        if ($key === 'session_recording') {
            $rec = $row['session_recording']
                ?? $row['session_recording_id']
                ?? $row['recording_id']
                ?? $row['has_session_recording']
                ?? null;
            if (is_array($rec)) {
                return ($rec['id'] ?? null) ? 'Yes' : 'No';
            }

            return $rec ? 'Yes' : 'No';
        }

        if ($key === 'block_status') {
            return $row['block_status']
                ?? $row['status']
                ?? ($row['risk_summary']['status'] ?? '');
        }

        if (in_array($key, ['visits', 'invalid_clicks', 'valid_clicks', 'cta_clicks', 'tel_clicks', 'page_changes', 'intel_risk_score', 'intel_confidence'], true)) {
            $value = $row[$key] ?? 0;

            return is_numeric($value) ? $value + 0 : $value;
        }

        $value = $row[$key] ?? '';
        if ($value === null || $value === '') {
            return $key === 'google_verified_label' ? '—' : '';
        }

        return $value;
    }

    public static function groupLabel(?string $columnGroup): ?string
    {
        $groupId = trim((string) $columnGroup);
        if ($groupId === '' || ! isset(self::COLUMN_GROUPS[$groupId])) {
            return null;
        }

        return self::COLUMN_GROUPS[$groupId]['label'];
    }

    public static function exportFilename(?string $columnGroup, string $extension): string
    {
        $stamp = now()->format('YmdHis');
        $groupId = trim((string) $columnGroup);
        if ($groupId !== '' && isset(self::COLUMN_GROUPS[$groupId])) {
            return 'clickronix-'.str_replace('_', '-', $groupId).'-'.$stamp.'.'.$extension;
        }

        return 'clickronix-traffic-report-'.$stamp.'.'.$extension;
    }
}
