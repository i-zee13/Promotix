<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectAdsIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'account_label',
        'account_id',
        'tag_id',
        'is_active',
        'settings',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
