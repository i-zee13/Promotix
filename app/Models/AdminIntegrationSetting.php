<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminIntegrationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'display_name',
        'provider',
        'enabled',
        'settings',
        'secret_payload',
        'key_version',
        'status',
        'last_rotated_at',
        'last_tested_at',
    ];

    protected $hidden = [
        'secret_payload',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'last_rotated_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
