<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'google_email',
        'google_sub',
        'refresh_token',
        'access_token',
        'scopes',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adsAccounts(): HasMany
    {
        return $this->hasMany(GoogleAdsAccount::class);
    }
}

