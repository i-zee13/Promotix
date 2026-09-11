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
        $linkedMeasurementIds = [];
        $liveMeasurementIds = [];
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
                $linkedMeasurementIds[] = strtoupper($tag);
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
            $host = preg_replace('#^https?://#i', '', $host) ?: $host;
            $candidates = array_values(array_unique([
                'https://'.$host,
                str_starts_with($host, 'www.') ? 'https://'.substr($host, 4) : 'https://www.'.$host,
            ]));
            $html = null;
            foreach ($candidates as $candidate) {
                $html = $this->fetchHomepageHtml($candidate);
                if ($html !== null) {
                    $checkedUrl = $candidate;
                    break;
                }
            }
            if ($html !== null) {
                foreach ($this->extractIdsFromHtml($html) as $kind => $ids) {
                    if ($kind === 'G') {
                        foreach ($ids as $id) {
                            $liveMeasurementIds[] = $id;
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
                $checkedUrl = $candidates[0] ?? null;
                $signals[] = 'homepage_fetch_failed';
            }
        }

        $gtmIds = array_values(array_unique($gtmIds));
        $awIds = array_values(array_unique($awIds));

        // GA4 is often only injected via GTM (not in static HTML). Pull G- IDs from published GTM JS.
        if ($gtmIds !== []) {
            foreach (array_slice($gtmIds, 0, 3) as $gtmId) {
                foreach ($this->extractIdsFromGtmContainer($gtmId) as $kind => $ids) {
                    if ($kind === 'G') {
                        foreach ($ids as $id) {
                            $liveMeasurementIds[] = $id;
                        }
                        if ($ids !== []) {
                            $signals[] = 'gtm_container_ga4_id';
                        }
                    } elseif ($kind === 'AW') {
                        foreach ($ids as $id) {
                            $awIds[] = $id;
                        }
                        if ($ids !== []) {
                            $signals[] = 'gtm_container_aw_id';
                        }
                    }
                }
            }
        }

        $linkedMeasurementIds = array_values(array_unique($linkedMeasurementIds));
        $liveMeasurementIds = array_values(array_unique($liveMeasurementIds));
        // Live IDs first so UI never stamps a linked-only G- as "Detected" for this domain.
        $measurementIds = array_values(array_unique(array_merge($liveMeasurementIds, $linkedMeasurementIds)));
        $gtmIds = array_values(array_unique($gtmIds));
        $awIds = array_values(array_unique($awIds));
        $signals = array_values(array_unique($signals));

        $hasGtm = $gtmIds !== [];
        $hasLiveGa4 = in_array('homepage_ga4_snippet', $signals, true)
            || in_array('gtm_container_ga4_id', $signals, true);
        $hasLiveGtm = in_array('homepage_gtm_snippet', $signals, true);
        $hasLiveGtagLoader = in_array('homepage_gtag_loader', $signals, true);
        $hasPortalGtm = in_array('portal_gtm_container', $signals, true);
        $hasLinkedGa4 = in_array('linked_account_ga4_id', $signals, true);

        // Attach / Tracking Installation: require live site or published GTM evidence.
        // A G- ID stored on a linked Ads account alone must NOT mark every domain "connected".
        $present = $hasLiveGa4 || $hasLiveGtm;

        // Homepage unreachable: soft-allow only when a real G- is linked (not AW / portal GTM alone).
        if (! $present && in_array('homepage_fetch_failed', $signals, true) && $hasLinkedGa4) {
            $present = true;
            $signals[] = 'portal_fallback_after_fetch_fail';
        }

        // has_ga4 = live G- (or soft fallback). Linked-only IDs stay in measurement_ids for hints, not as connected.
        $hasGa4 = $hasLiveGa4
            || in_array('portal_fallback_after_fetch_fail', $signals, true);

        $confidence = 'none';
        if ($hasLiveGa4 && ($hasLiveGtm || $hasGtm)) {
            $confidence = 'high';
        } elseif ($hasLiveGa4 || $hasLiveGtm) {
            $confidence = 'medium';
        } elseif ($hasLinkedGa4) {
            $confidence = 'low';
        } elseif ($hasPortalGtm || $hasLiveGtagLoader || $awIds !== []) {
            $confidence = 'none';
        }

        $message = $present
            ? ($hasGa4
                ? 'GA4 detected (G-…) on the website. Audience exclusion can proceed.'
                : 'Live GTM found, but no GA4 measurement ID (G-…) yet. Add a GA4 Configuration tag in GTM, publish, then Detect again.')
            : 'GA4 not detected on the website. A Google Ads tag (AW-…), portal GTM ID, or Ads-linked G- ID alone is not enough — install GA4 (G-…) via GTM or direct, then retry.';

        if (! $present && ($hasPortalGtm || $hasGtm) && ! $hasGa4) {
            $message = 'GTM is linked in the portal, but no live GA4 (G-…) was found on the site or in the published container. Publish GTM with a GA4 tag, then Detect again.';
        } elseif (! $present && $hasLinkedGa4 && ! $hasLiveGa4) {
            $message = 'A G- ID is saved on a linked Ads account, but it was not found on this domain’s website. Install GA4 on the site (or in GTM), then Detect again.';
        } elseif (! $present && $awIds !== [] && ! $hasGa4) {
            $message = 'Google Ads tag (AW-…) found, but GA4 (G-…) is not installed. Audience membership needs GA4 Client ID — install GA4 first.';
        } elseif ($hasLiveGa4 && ! $hasGtm && ! $hasLiveGtm) {
            $message = 'GA4 (G-) detected without GTM. The GA4 audience route needs GTM as the delivery container. Use GTM + GA4 together, or use the Google Ads website audience route.';
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
     * Published GTM containers often embed GA4 measurement IDs that never appear in static homepage HTML.
     *
     * @return array{G?: list<string>, GTM?: list<string>, AW?: list<string>}
     */
    private function extractIdsFromGtmContainer(string $gtmId): array
    {
        $gtmId = strtoupper(trim($gtmId));
        if (! preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
            return [];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'ClickronixGa4Detector/1.0',
                    'Accept' => '*/*',
                ])
                ->get('https://www.googletagmanager.com/gtm.js', ['id' => $gtmId]);

            if (! $response->successful()) {
                return [];
            }

            return $this->extractIdsFromHtml(Str::limit((string) $response->body(), 800_000, ''));
        } catch (\Throwable) {
            return [];
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
