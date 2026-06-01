<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\PaidMarketingVisit;

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
        if (($this->source ?? self::SOURCE_MANUAL) === self::SOURCE_GOOGLE_ADS) {
            return true;
        }

        if ($this->google_ads_account_id !== null) {
            return true;
        }

        return $this->googleAdsMappings()->exists();
    }

    public function scopeManual($query)
    {
        return $query->where('source', self::SOURCE_MANUAL);
    }

    public function scopeForPaidMarketing($query)
    {
        return $query->where(function ($q) {
            $q->where('source', self::SOURCE_GOOGLE_ADS)
                ->orWhereNotNull('google_ads_account_id')
                ->orWhereHas('googleAdsMappings');
        });
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

    public function detectionSetting(): HasOne
    {
        return $this->hasOne(DomainDetectionSetting::class);
    }
}

