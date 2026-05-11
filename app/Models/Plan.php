<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'saas_product_id',
        'name',
        'slug',
        'tier',
        'price_cents',
        'currency',
        'billing_interval',
        'is_custom',
        'is_active',
        'trial_days',
        'feature_limits',
        'feature_flags',
    ];

    protected $appends = ['formatted_price'];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
            'feature_limits' => 'array',
            'feature_flags' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SaasProduct::class, 'saas_product_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_custom) {
            return 'Custom';
        }

        return strtoupper($this->currency).' '.number_format($this->price_cents / 100, 2);
    }
}
