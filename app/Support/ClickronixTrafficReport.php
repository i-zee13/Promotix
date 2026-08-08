<?php

namespace App\Support;

/**
 * Clickronix client Traffic Report template (42 columns).
 * Used by Paid Marketing Advanced CSV / XLSX exports.
 */
class ClickronixTrafficReport
{
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
}
