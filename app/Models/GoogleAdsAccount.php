<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_connection_id',
        'customer_id',
        'display_customer_id',
        'account_name',
        'time_zone',
        'manager_customer_id',
        'is_manager',
        'google_tag_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_manager' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleConnection::class, 'google_connection_id');
    }

    public function domainMappings(): HasMany
    {
        return $this->hasMany(DomainGoogleAdsMapping::class);
    }

    public function advertisedHosts(): HasMany
    {
        return $this->hasMany(GoogleAdsAdvertisedHost::class);
    }

    public function linkedDomains(): HasMany
    {
        return $this->hasMany(Domain::class, 'google_ads_account_id');
    }

    /** Accounts successfully loaded from Google Ads API (name + access confirmed). */
    public function scopeSynced($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('account_name')
            ->where('account_name', '!=', '');
    }

    public function displayLabel(): string
    {
        $name = trim((string) $this->account_name);
        if ($name !== '') {
            return $name;
        }

        return $this->display_customer_id ?: $this->customer_id;
    }
}

