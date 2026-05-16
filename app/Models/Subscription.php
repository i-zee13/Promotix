<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'is_trial',
        'amount_cents',
        'currency',
        'billing_interval',
        'started_at',
        'trial_ends_at',
        'current_period_ends_at',
        'grace_period_ends_at',
        'protection_paused_at',
        'cancelled_at',
        'metadata',
        'last_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'is_trial' => 'boolean',
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'protection_paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
