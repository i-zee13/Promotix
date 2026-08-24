<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'requester_id',
        'assigned_to_id',
        'subject',
        'status',
        'priority',
        'category',
        'department',
        'source',
        'context',
        'ticket_number',
        'body',
        'sla_due_at',
        'escalated_at',
        'closed_at',
        'first_response_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'closed_at' => 'datetime',
            'first_response_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }
}
