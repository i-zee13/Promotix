<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                if (! Schema::hasColumn('visits', 'google_campaign_id')) {
                    $table->string('google_campaign_id', 32)->nullable()->after('utm_campaign')->index();
                }
                if (! Schema::hasColumn('visits', 'campaign_name')) {
                    $table->string('campaign_name')->nullable()->after('google_campaign_id')->index();
                }
            });
        }

        foreach (['paid_marketing_visits', 'paid_marketing_clicks'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'google_campaign_id')) {
                    $table->string('google_campaign_id', 32)->nullable()->after('campaign')->index();
                }
                if (! Schema::hasColumn($tableName, 'campaign_name')) {
                    $table->string('campaign_name')->nullable()->after('google_campaign_id')->index();
                }
            });
        }

        $this->backfillCampaignNames();
    }

    public function down(): void
    {
        foreach (['visits', 'paid_marketing_visits', 'paid_marketing_clicks'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['campaign_name', 'google_campaign_id'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function backfillCampaignNames(): void
    {
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'campaign_name')) {
            DB::table('visits')
                ->whereNotNull('utm_campaign')
                ->where('utm_campaign', '!=', '')
                ->where(function ($query): void {
                    $query->whereNull('campaign_name')->orWhere('campaign_name', '');
                })
                ->update(['campaign_name' => DB::raw('utm_campaign')]);
        }

        foreach (['paid_marketing_visits', 'paid_marketing_clicks'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'campaign_name')) {
                continue;
            }

            DB::table($tableName)
                ->whereNotNull('campaign')
                ->where('campaign', '!=', '')
                ->where(function ($query): void {
                    $query->whereNull('campaign_name')->orWhere('campaign_name', '');
                })
                ->update(['campaign_name' => DB::raw('campaign')]);
        }

        if (! Schema::hasTable('google_ads_campaign_daily_metrics')) {
            return;
        }

        $singletonCampaigns = DB::table('google_ads_campaign_daily_metrics')
            ->select(
                'domain_id',
                DB::raw('MAX(campaign_id) as campaign_id'),
                DB::raw('MAX(campaign_name) as campaign_name'),
            )
            ->groupBy('domain_id')
            ->havingRaw('COUNT(DISTINCT campaign_id) = 1')
            ->get();

        foreach ($singletonCampaigns as $row) {
            $domainId = (int) $row->domain_id;
            $campaignId = (string) $row->campaign_id;
            $campaignName = (string) $row->campaign_name;

            if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'campaign_name')) {
                DB::table('visits')
                    ->where('domain_id', $domainId)
                    ->where(function ($query): void {
                        $query->where('is_paid_traffic', true);
                        if (Schema::hasColumn('visits', 'gclid')) {
                            $query->orWhere(function ($gclid): void {
                                $gclid->whereNotNull('gclid')->where('gclid', '!=', '');
                            });
                        }
                    })
                    ->where(function ($query): void {
                        $query->whereNull('campaign_name')->orWhere('campaign_name', '');
                    })
                    ->update([
                        'google_campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                    ]);
            }

            if (Schema::hasTable('paid_marketing_visits') && Schema::hasColumn('paid_marketing_visits', 'campaign_name')) {
                DB::table('paid_marketing_visits')
                    ->where('domain_id', $domainId)
                    ->where(function ($query): void {
                        $query->whereNull('campaign_name')->orWhere('campaign_name', '');
                    })
                    ->update([
                        'google_campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                        'campaign' => $campaignName,
                    ]);
            }

            if (Schema::hasTable('paid_marketing_clicks') && Schema::hasColumn('paid_marketing_clicks', 'campaign_name')) {
                $clickIds = DB::table('paid_marketing_clicks as pc')
                    ->join('paid_marketing_visits as pv', 'pv.id', '=', 'pc.paid_marketing_visit_id')
                    ->where('pv.domain_id', $domainId)
                    ->where(function ($query): void {
                        $query->whereNull('pc.campaign_name')->orWhere('pc.campaign_name', '');
                    })
                    ->pluck('pc.id');

                if ($clickIds->isNotEmpty()) {
                    DB::table('paid_marketing_clicks')
                        ->whereIn('id', $clickIds)
                        ->update([
                            'google_campaign_id' => $campaignId,
                            'campaign_name' => $campaignName,
                            'campaign' => $campaignName,
                        ]);
                }
            }
        }
    }
};
