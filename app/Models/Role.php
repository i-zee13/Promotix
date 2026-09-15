<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'portal',
        'color',
        'is_temporary',
        'expires_at',
        'base_role_id',
        'page_access',
        'abilities',
        'scope',
    ];

    protected function casts(): array
    {
        return [
            'is_temporary' => 'boolean',
            'expires_at' => 'datetime',
            'page_access' => 'array',
            'abilities' => 'array',
            'scope' => 'array',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function baseRole(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_role_id');
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function isUserPortal(): bool
    {
        return ($this->portal ?? 'user') === 'user';
    }

    public function isAdminPortal(): bool
    {
        return ($this->portal ?? 'user') === 'admin';
    }

    public function snapshotForAudit(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'portal' => $this->portal,
            'color' => $this->color,
            'is_temporary' => (bool) $this->is_temporary,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'base_role_id' => $this->base_role_id,
            'page_access' => $this->page_access ?? [],
            'abilities' => $this->abilities ?? [],
            'scope' => $this->scope ?? [],
            'permission_ids' => $this->permissions()->pluck('permissions.id')->all(),
        ];
    }
}
