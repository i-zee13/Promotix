<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSession extends Model
{
    protected $fillable = [
        'channel',
        'user_id',
        'tenant_id',
        'department',
        'status',
        'transcript',
        'last_activity_at',
        'ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
