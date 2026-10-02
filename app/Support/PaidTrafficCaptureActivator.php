<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\PaidMarketingClick;
use App\Models\PaidMarketingVisit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After a domain is linked to Google Ads, promote historical gclid/gbraid/wbraid
 * visits (captured while Ads was unlinked) into Paid Marketing UI rows.
 */
final class PaidTrafficCaptureActivator
{
    /**
     * @return array{visits_marked: int, clicks_created: int}
     */
    public function activateDomain(Domain $domain): array
    {
        if (! $domain->hasGoogleAdsConnection() || ! Schema::hasTable('visits')) {
            return ['visits_marked' => 0, 'clicks_created' => 0];
        }

        $marked = 0;
        if (Schema::hasColumn('visits', 'is_paid_traffic')) {
            $query = DB::table('visits')
                ->where('domain_id', $domain->id)
                ->where(function ($q): void {
                    $q->where('is_paid_traffic', false)->orWhereNull('is_paid_traffic');
                });
            GoogleClickAttribution::applyHasClickIdFilter($query);
            $marked = $query->update([
                'is_paid_traffic' => true,
                'updated_at' => UserTimezone::nowUtc(),
            ]);
        }

        $clicksCreated = 0;
        if (! Schema::hasTable('paid_marketing_visits') || ! Schema::hasTable('paid_marketing_clicks')) {
            return ['visits_marked' => $marked, 'clicks_created' => 0];
        }

        $select = ['id', 'ip', 'visited_at', 'country', 'url', 'device', 'browser', 'os', 'utm_campaign', 'utm_term'];
        foreach (['gclid', 'gbraid', 'wbraid', 'google_campaign_id', 'campaign_name', 'threat_group', 'browser_version', 'click_source'] as $col) {
            if (Schema::hasColumn('visits', $col)) {
                $select[] = $col;
            }
        }

        $visits = DB::table('visits')
            ->where('domain_id', $domain->id)
            ->where(function ($q): void {
                GoogleClickAttribution::applyHasClickIdFilter($q);
            })
            ->orderBy('visited_at')
            ->get($select);

        foreach ($visits as $visit) {
            $paidId = (string) ($visit->gclid ?? $visit->gbraid ?? $visit->wbraid ?? '');
            if ($paidId === '') {
                continue;
            }

            $ip = (string) ($visit->ip ?? '');
            if ($ip === '') {
                continue;
            }

            if ($this->paidClickIdExists((int) $domain->id, $paidId)) {
                continue;
            }

            $pmVisit = PaidMarketingVisit::firstOrNew([
                'domain_id' => $domain->id,
                'ip' => $ip,
            ]);
            if (! $pmVisit->exists) {
                $pmVisit->visits = 0;
            }
            $pmVisit->visits = ((int) ($pmVisit->visits ?? 0)) + 1;
            $pmVisit->last_click_at = $visit->visited_at;
            $pmVisit->last_path = $visit->url ?? null;
            $pmVisit->campaign = $visit->campaign_name ?? $visit->utm_campaign ?? null;
            $pmVisit->platform = $visit->device ?? null;
            $pmVisit->country = $visit->country ?? null;
            $pmVisit->threat_group = $visit->threat_group ?? null;
            if (Schema::hasColumn('paid_marketing_visits', 'google_campaign_id')) {
                $pmVisit->google_campaign_id = $visit->google_campaign_id ?? null;
            }
            if (Schema::hasColumn('paid_marketing_visits', 'campaign_name')) {
                $pmVisit->campaign_name = $visit->campaign_name ?? $visit->utm_campaign ?? null;
            }
            $pmVisit->save();

            $payload = [
                'paid_marketing_visit_id' => $pmVisit->id,
                'clicked_at' => $visit->visited_at,
                'ip' => $ip,
                'country' => $visit->country ?? null,
                'last_click_at' => $visit->visited_at,
                'threat_group' => $visit->threat_group ?? null,
                'campaign' => $visit->campaign_name ?? $visit->utm_campaign ?? null,
                'paid_id' => $paidId,
                'path' => $visit->url ?? null,
                'keyword' => $visit->utm_term ?? null,
                'browser_name' => $visit->browser ?? null,
                'browser_version' => $visit->browser_version ?? null,
                'os' => $visit->os ?? null,
            ];
            if (Schema::hasColumn('paid_marketing_clicks', 'google_campaign_id')) {
                $payload['google_campaign_id'] = $visit->google_campaign_id ?? null;
            }
            if (Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $payload['campaign_name'] = $visit->campaign_name ?? $visit->utm_campaign ?? null;
            }
            if (Schema::hasColumn('paid_marketing_clicks', 'click_source')) {
                $payload['click_source'] = $visit->click_source ?? 'tag';
            }

            PaidMarketingClick::create($payload);
            $clicksCreated++;
        }

        return ['visits_marked' => $marked, 'clicks_created' => $clicksCreated];
    }

    private function paidClickIdExists(int $domainId, string $paidId): bool
    {
        if ($paidId === '' || ! Schema::hasTable('paid_marketing_clicks') || ! Schema::hasTable('paid_marketing_visits')) {
            return false;
        }

        return DB::table('paid_marketing_clicks as pc')
            ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
            ->where('pv.domain_id', $domainId)
            ->where('pc.paid_id', $paidId)
            ->exists();
    }
}
