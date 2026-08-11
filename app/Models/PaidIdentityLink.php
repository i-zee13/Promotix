<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidIdentityLink extends Model
{
    protected $table = 'paid_identity_links';

    protected $fillable = [
        'paid_identity_id',
        'link_type',
        'link_value',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(PaidIdentity::class, 'paid_identity_id');
    }
}
