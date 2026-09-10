<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Detect whether GA4 / GTM / Google tag is present on the customer's website.
 * Used to gate audience create + Ads exclusion apply.
 */
class Ga4SitePresenceService
{
    /**
     * @return array{
     *   present: bool,
     *   confidence: 'high'|'medium'|'low'|'none',
     *   measurement_ids: list<string>,
     *   gtm_ids: list<string>,
     *   aw_ids: list<string>,
     *   signals: list<string>,
     *   message: string,
     *   checked_url: ?string
     * }
     */
    public function detect(Domain $domain): array
    {
        $measurementIds = [];
        $gtmIds = [];
        $awIds = [];
        $signals = [];

        $gtmStored = strtoupper(trim((string) ($domain->gtm_container_id ?? '')));
        if (preg_match('/^GTM-[A-Z0-9]+$/', $gtmStored)) {
            $gtmIds[] = $gtmStored;
            $signals[] = 'portal_gtm_container';
        }

        $domain->loadMissing(['googleAdsAccount', 'googleAdsMappings.account']);
        $accounts = collect([$domain->googleAdsAccount])
            ->merge($domain->googleAdsMappings->pluck('account'))
            ->filter();

        foreach ($accounts as $account) {
            $tag = trim((string) ($account->resolvedGoogleTagId() ?: $account->google_tag_id ?: ''));
            if ($tag === '') {
                continue;
            }
            if (preg_match('/^G-[A-Z0-9]+$/i', $tag)) {
                $measurementIds[] = strtoupper($tag);
                $signals[] = 'linked_account_ga4_id';
            } elseif (preg_match('/^AW-/i', $tag)) {
                $awIds[] = strtoupper($tag);
                $signals[] = 'linked_account_aw_tag';
            }
        }

        if ((bool) $domain->tag_connected) {
            $signals[] = 'clickronix_tag_connected';
        }

        $checkedUrl = null;
        $host = strtolower(trim((string) $domain->hostname));
        if ($host !== '' && ! str_contains($host, 'localhost') && ! str_ends_with($host, '.test')) {
            $checkedUrl = 'https://'.preg_replace('#^https?://#i', '', $host);
            $html = $this->fetchHomepageHtml($checkedUrl);
            if ($html !== null) {
                foreach ($this->extractIdsFromHtml($html) as $kind => $ids) {
                    if ($kind === 'G') {
                        foreach ($ids as $id) {
                            $measurementIds[] = $id;
                        }
                        if ($ids !== []) {
                            $signals[] = 'homepage_ga4_snippet';
                        }
                    } elseif ($kind === 'GTM') {
                        foreach ($ids as $id) {
                            $gtmIds[] = $id;
                        }
                        if ($ids !== []) {
                            $signals[] = 'homepage_gtm_snippet';
                        }
                    } elseif ($kind === 'AW') {
                        foreach ($ids as $id) {
                            $awIds[] = $id;
                        }
                        if ($ids !== []) {
                            $signals[] = 'homepage_aw_snippet';
                        }
                    }
                }
                if (stripos($html, 'gtag(') !== false || stripos($html, 'googletagmanager.com') !== false) {
                    $signals[] = 'homepage_gtag_loader';
                }
            } else {
                $signals[] = 'homepage_fetch_failed';
            }
        }

        $measurementIds = array_values(array_unique($measurementIds));
        $gtmIds = array_values(array_unique($gtmIds));
        $awIds = array_values(array_unique($awIds));
        $signals = array_values(array_unique($signals));

        $hasGa4 = $measurementIds !== [];
        $hasGtm = $gtmIds !== [];
        $hasLiveSnippet = in_array('homepage_ga4_snippet', $signals, true)
            || in_array('homepage_gtm_snippet', $signals, true)
            || in_array('homepage_gtag_loader', $signals, true);
        $hasPortalProof = in_array('portal_gtm_container', $signals, true)
            || in_array('linked_account_ga4_id', $signals, true)
            || in_array('clickronix_tag_connected', $signals, true);

        // Present when GA4 (G-) or GTM is on the site HTML, or strongly linked in portal
        // (portal GTM / linked G- id). AW-only or Clickronix tag alone is not enough.
        $present = $hasLiveSnippet
            || $hasGa4
            || $hasGtm;

        // If homepage fetch failed but portal has GTM/G-, still allow with medium confidence.
        if (! $present && in_array('homepage_fetch_failed', $signals, true) && $hasPortalProof) {
            $present = in_array('portal_gtm_container', $signals, true)
                || in_array('linked_account_ga4_id', $signals, true);
            if ($present) {
                $signals[] = 'portal_fallback_after_fetch_fail';
            }
        }

        $confidence = 'none';
        if ($hasLiveSnippet && ($hasGa4 || $hasGtm)) {
            $confidence = 'high';
        } elseif ($hasLiveSnippet || $hasGa4 || $hasGtm) {
            $confidence = 'medium';
        } elseif ($hasPortalProof) {
            $confidence = 'low';
        }

        $message = $present
            ? 'GA4/GTM detected on the website (or linked in portal). Audience exclusion can proceed.'
            : 'GA4/GTM not detected on the website. Install GA4 (G-…) or GTM, then retry. Without it, Client ID events cannot populate the audience and Ads exclusions will not work as expected.';

        if ($hasGa4 && ! $hasGtm) {
            $message = 'GA4 (G-) detected without GTM. GA4 audience route needs GTM as the delivery container. Use GTM + GA4 together, or use the Google Ads website audience route.';
        }

        return [
            'present' => $present,
            'confidence' => $confidence,
            'has_ga4' => $hasGa4,
            'has_gtm' => $hasGtm,
            'measurement_ids' => $measurementIds,
            'gtm_ids' => $gtmIds,
            'aw_ids' => $awIds,
            'signals' => $signals,
            'message' => $message,
            'checked_url' => $checkedUrl,
        ];
    }

    private function fetchHomepageHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'ClickronixGa4Detector/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return Str::limit((string) $response->body(), 500_000, '');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{G?: list<string>, GTM?: list<string>, AW?: list<string>}
     */
    private function extractIdsFromHtml(string $html): array
    {
        $out = ['G' => [], 'GTM' => [], 'AW' => []];

        if (preg_match_all('/\bG-[A-Z0-9]{6,}\b/i', $html, $m)) {
            foreach ($m[0] as $id) {
                $out['G'][] = strtoupper($id);
            }
        }
        if (preg_match_all('/\bGTM-[A-Z0-9]+\b/i', $html, $m)) {
            foreach ($m[0] as $id) {
                $out['GTM'][] = strtoupper($id);
            }
        }
        if (preg_match_all('/\bAW-\d{5,}\b/i', $html, $m)) {
            foreach ($m[0] as $id) {
                $out['AW'][] = strtoupper($id);
            }
        }

        $out['G'] = array_values(array_unique($out['G']));
        $out['GTM'] = array_values(array_unique($out['GTM']));
        $out['AW'] = array_values(array_unique($out['AW']));

        return $out;
    }
}
