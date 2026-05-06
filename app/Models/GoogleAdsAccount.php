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
}

