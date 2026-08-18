<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\PaidMarketingVisit;
use App\Services\DomainDataPurger;

class Domain extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_GOOGLE_ADS = 'google_ads';

    protected $fillable = [
        'user_id',
        'hostname',
        'source',
        'google_ads_account_id',
        'ads_synced_at',
        'status',
        'domain_key',
        'secret_key',
        'authentication_key',
        'gtm_container_id',
        'tracking_params',
        'tag_connected',
        'paid_marketing_connected',
        'bot_mitigation_connected',
        'monitoring_only_mode',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'tag_connected' => 'boolean',
            'paid_marketing_connected' => 'boolean',
            'bot_mitigation_connected' => 'boolean',
            'monitoring_only_mode' => 'boolean',
            'tracking_params' => 'array',
            'last_seen_at' => 'datetime',
            'ads_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Domain $domain): void {
            app(DomainDataPurger::class)->purge($domain);
        });
    }

    public function isManual(): bool
    {
        return ($this->source ?? self::SOURCE_MANUAL) === self::SOURCE_MANUAL;
    }

    /**
     * Paid marketing pool only: synced from Google Ads API or linked to an Ads account.
     * Manual-only domains (tag/bot setup) are excluded unless they overlap via sync/link.
     */
    public function hasPaidAdvertisingFromAds(): bool
    {
        if (! $this->isManual()) {
            return false;
        }

        return $this->hasGoogleAdsConnection();
    }

    /**
     * True when this domain is linked to a Google Ads account (direct FK or mapping).
     */
    public function hasGoogleAdsConnection(): bool
    {
        if ($this->google_ads_account_id !== null) {
            return true;
        }

        if ($this->relationLoaded('googleAdsMappings')) {
            return $this->googleAdsMappings->isNotEmpty();
        }

        try {
            return $this->googleAdsMappings()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function scopeManual($query)
    {
        return $query->where('source', self::SOURCE_MANUAL);
    }

    /** Manual domains linked to Google Ads (paid marketing dashboard). */
    public function scopeForPaidMarketing($query)
    {
        return $query->manual()->where(function ($q) {
            $q->whereNotNull('google_ads_account_id')
                ->orWhereHas('googleAdsMappings');
        });
    }

    /** All manual domains for site management. */
    public function scopeForPaidMarketingSetup($query)
    {
        return $query->manual();
    }

    public function scopeForBotProtection($query)
    {
        return $query->manual();
    }

    public function googleAdsAccount(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAccount::class, 'google_ads_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidMarketingVisits(): HasMany
    {
        return $this->hasMany(PaidMarketingVisit::class);
    }

    public function googleAdsMappings(): HasMany
    {
        return $this->hasMany(DomainGoogleAdsMapping::class);
    }

    public function googleAdsCampaignDailyMetrics(): HasMany
    {
        return $this->hasMany(GoogleAdsCampaignDailyMetric::class);
    }

    public function detectionSetting(): HasOne
    {
        return $this->hasOne(DomainDetectionSetting::class);
    }
}

