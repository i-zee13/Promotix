<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainGoogleAdsMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'google_ads_account_id',
        'protection_type',
        'audience_exclusion_enabled',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'audience_exclusion_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsAccount::class, 'google_ads_account_id');
    }
}

