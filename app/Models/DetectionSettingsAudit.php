<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectionSettingsAudit extends Model
{
    protected $fillable = [
        'domain_id',
        'user_id',
        'scope',
        'action',
        'field',
        'previous_value',
        'new_value',
    ];

    protected $casts = [
        'previous_value' => 'array',
        'new_value' => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
