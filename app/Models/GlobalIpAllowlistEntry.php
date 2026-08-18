<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalIpAllowlistEntry extends Model
{
    protected $fillable = [
        'kind',
        'provider',
        'value',
        'label',
        'enabled',
        'notes',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isProvider(): bool
    {
        return $this->kind === 'provider';
    }
}
