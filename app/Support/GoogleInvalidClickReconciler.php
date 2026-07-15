<?php

namespace App\Support;

/**
 * DE-07: bucket paid visits into Google-only, platform-only, and overlap invalid signals.
 */
final class GoogleInvalidClickReconciler
{
    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array{
     *   platform_only: int,
     *   google_only: int,
     *   overlap: int,
     *   platform_invalid_total: int,
     *   google_gap_total: int
     * }
     */
    public function categorize(
        iterable $rows,
        GoogleVerifiedCampaignLookup $lookup,
        string $reportingTz,
    ): array {
        $platformOnly = 0;
        $googleOnly = 0;
        $overlap = 0;
        $platformInvalidTotal = 0;
        $googleGapTotal = 0;
        $seenClickIds = [];

        foreach ($rows as $row) {
            $data = (array) $row;
            $clickId = GoogleClickAttribution::clickIdValue($data);
            if ($clickId !== '') {
                $dedupeKey = (int) ($data['domain_id'] ?? 0) . '|' . $clickId;
                if (isset($seenClickIds[$dedupeKey])) {
                    continue;
                }
                $seenClickIds[$dedupeKey] = true;
            }

            $domainId = (int) ($data['domain_id'] ?? 0);
            $campaignId = GoogleVerifiedPaidTraffic::resolveCampaignId($row);
            $clickedAt = $data['visited_at'] ?? $data['clicked_at'] ?? null;
            $platformInvalid = GoogleVerifiedPaidTraffic::isInvalidRow($data);
            $verified = $lookup->isVerified($domainId, $campaignId, $clickedAt, $reportingTz);

            if ($platformInvalid) {
                $platformInvalidTotal++;
            }

            if (! $verified) {
                $googleGapTotal++;
            }

            if ($platformInvalid && $verified) {
                $platformOnly++;
            } elseif (! $platformInvalid && ! $verified) {
                $googleOnly++;
            } elseif ($platformInvalid && ! $verified) {
                $overlap++;
            }
        }

        return [
            'platform_only' => $platformOnly,
            'google_only' => $googleOnly,
            'overlap' => $overlap,
            'platform_invalid_total' => $platformInvalidTotal,
            'google_gap_total' => $googleGapTotal,
        ];
    }
}
