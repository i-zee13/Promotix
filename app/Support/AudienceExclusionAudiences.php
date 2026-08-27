<?php

namespace App\Support;

/**
 * Normalizes Google Ads Audience Exclusion conversion rows (PDF § Audience Exclusion).
 */
class AudienceExclusionAudiences
{
    /**
     * @param  list<mixed>  $rows
     * @return list<array{conversion_id: string, conversion_label: string, tag: string, domain_id: ?int}>
     */
    public static function normalize(array $rows): array
    {
        $out = [];
        foreach ($rows as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $conversionId = trim((string) ($raw['conversion_id'] ?? ''));
            $conversionLabel = trim((string) ($raw['conversion_label'] ?? ''));
            $tag = trim((string) ($raw['tag'] ?? $raw['domain_key'] ?? ''));
            $domainId = isset($raw['domain_id']) && is_numeric($raw['domain_id'])
                ? (int) $raw['domain_id']
                : null;

            if ($conversionId === '' && $conversionLabel === '' && $tag === '') {
                continue;
            }

            $out[] = [
                'conversion_id' => mb_substr($conversionId, 0, 120),
                'conversion_label' => mb_substr($conversionLabel, 0, 255),
                'tag' => mb_substr($tag, 0, 120),
                'domain_id' => $domainId && $domainId > 0 ? $domainId : null,
            ];
        }

        return array_values($out);
    }

    /**
     * @param  list<array{conversion_id: string, conversion_label: string, tag: string, domain_id: ?int}>  $rows
     * @return list<string>
     */
    public static function validationErrors(array $rows): array
    {
        $errors = [];
        if ($rows === []) {
            $errors[] = 'Add at least one Conversion ID / Label / Tag row.';

            return $errors;
        }

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            if ($row['conversion_id'] === '') {
                $errors[] = "Row {$n}: Conversion ID is required.";
            }
            if ($row['conversion_label'] === '') {
                $errors[] = "Row {$n}: Conversion Label is required.";
            }
            if ($row['tag'] === '' && empty($row['domain_id'])) {
                $errors[] = "Row {$n}: Select a tag / domain.";
            }
        }

        return $errors;
    }
}
