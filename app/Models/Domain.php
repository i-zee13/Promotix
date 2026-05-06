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

    protected $fillable = [
        'user_id',
        'hostname',
        'domain_key',
        'secret_key',
        'authentication_key',
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
            'last_seen_at' => 'datetime',
        ];
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

