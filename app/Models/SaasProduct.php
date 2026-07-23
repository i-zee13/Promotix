<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class SaasProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'settings'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function gatesCustomerPortal(): bool
    {
        return (bool) data_get($this->settings, 'gates_customer_portal', false);
    }

    /**
     * The SaaS product that gates the customer portal (ClickRonix).
     * Returns the row even when inactive so middleware can block access.
     */
    public static function portalProduct(): ?self
    {
        return Cache::remember('saas_product:portal', now()->addMinutes(5), function () {
            return self::query()
                ->orderBy('id')
                ->get()
                ->first(fn (self $product) => $product->gatesCustomerPortal());
        });
    }

    public static function flushPortalCache(): void
    {
        Cache::forget('saas_product:portal');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushPortalCache());
        static::deleted(fn () => self::flushPortalCache());
    }
}
