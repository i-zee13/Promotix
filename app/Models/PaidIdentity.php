<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaidIdentity extends Model
{
    protected $table = 'paid_identities';

    protected $fillable = [
        'public_id',
        'domain_id',
        'visitor_id',
        'browser_id',
        'device_id',
        'fingerprint_id',
        'identity_confidence',
        'confidence_band',
        'known_fraud',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'identity_confidence' => 'float',
            'known_fraud' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(PaidIdentityLink::class, 'paid_identity_id');
    }
}
