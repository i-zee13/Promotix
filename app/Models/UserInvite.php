<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvite extends Model
{
    protected $fillable = [
        'invited_by_id',
        'team_owner_id',
        'email',
        'name',
        'role_id',
        'plan_id',
        'page_slugs',
        'domain_ids',
        'token',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
            'page_slugs' => 'array',
            'domain_ids' => 'array',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function teamOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_owner_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
