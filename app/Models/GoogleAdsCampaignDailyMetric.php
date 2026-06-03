<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsCampaignDailyMetric extends Model
{
    protected $fillable = [
        'domain_id',
        'google_ads_account_id',
        'campaign_id',
        'campaign_name',
        'status',
        'metric_date',
        'clicks',
        'impressions',
        'cost',
        'cpc',
        'conversions',
        'phone_calls',
        'ctr',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'cost' => 'decimal:2',
            'cpc' => 'decimal:2',
            'conversions' => 'decimal:2',
            'ctr' => 'decimal:2',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function googleAdsAccount(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAccount::class);
    }
}
