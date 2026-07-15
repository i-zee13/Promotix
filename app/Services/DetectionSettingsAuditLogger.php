<?php

namespace App\Services;

use App\Models\DetectionSettingsAudit;
use App\Models\DomainDetectionSetting;
use Illuminate\Support\Facades\Schema;

class DetectionSettingsAuditLogger
{
    /** @var list<string> */
    private const COUNTRY_FIELDS = [
        'control_mode',
        'out_of_geo_enabled',
        'out_of_geo_countries',
        'out_of_geo_audience',
        'google_geo_block_enabled',
        'google_geo_block_audience',
    ];

    /** @var list<string> */
    private const CONFIG_FIELDS = [
        'detection_profile',
        'detection_thresholds',
        'fail_mode',
        'allow_list_enabled',
        'allow_list_ips',
        'block_list_enabled',
        'block_list_ips',
        'session_recordings',
        'frequency_capping',
        'suspicious_enabled',
        'suspicious_matrix',
    ];

    public function logCountryRuleChanges(
        DomainDetectionSetting $before,
        DomainDetectionSetting $after,
        ?int $userId,
        string $scope = 'domain',
    ): int {
        return $this->logFieldChanges($before, $after, $userId, $scope, self::COUNTRY_FIELDS);
    }

    public function logConfigChanges(
        DomainDetectionSetting $before,
        DomainDetectionSetting $after,
        ?int $userId,
        string $scope = 'domain',
    ): int {
        return $this->logFieldChanges($before, $after, $userId, $scope, self::CONFIG_FIELDS);
    }

    /**
     * @param  list<string>  $fields
     */
    private function logFieldChanges(
        DomainDetectionSetting $before,
        DomainDetectionSetting $after,
        ?int $userId,
        string $scope,
        array $fields,
    ): int {
        if (! Schema::hasTable('detection_settings_audits')) {
            return 0;
        }

        $written = 0;
        foreach ($fields as $field) {
            $prev = $this->normalize($before->getAttribute($field));
            $next = $this->normalize($after->getAttribute($field));
            if ($prev === $next) {
                continue;
            }

            $action = 'updated';
            if (str_ends_with($field, '_enabled') || $field === 'session_recordings' || $field === 'frequency_capping' || $field === 'suspicious_enabled') {
                $action = ((bool) $next) ? 'enabled' : 'disabled';
            }

            DetectionSettingsAudit::query()->create([
                'domain_id' => $after->domain_id,
                'user_id' => $userId,
                'scope' => $scope,
                'action' => $action,
                'field' => $field,
                'previous_value' => ['value' => $prev],
                'new_value' => ['value' => $next],
            ]);
            $written++;
        }

        return $written;
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }
}
