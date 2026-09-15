<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissionAudit extends Model
{
    protected $fillable = [
        'actor_id',
        'role_id',
        'action',
        'portal',
        'scope',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
