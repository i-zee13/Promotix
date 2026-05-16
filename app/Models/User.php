<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'website_url',
        'password',
        'is_admin',
        'is_super_admin',
        'status',
        'role_id',
        'ui_preferences',
        'last_login_at',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function googleConnections(): HasMany
    {
        return $this->hasMany(GoogleConnection::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function roleChanges(): HasMany
    {
        return $this->hasMany(RoleChange::class)->orderByDesc('created_at');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->orderByDesc('created_at');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(UserInvite::class, 'invited_by_id');
    }

    /**
     * Currently-active subscription (active or trialing), most recent first.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();
    }

    /**
     * The plan attached to the user's active subscription, or null when on no plan.
     */
    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription()?->plan;
    }

    /**
     * Resolve a numeric feature limit for the user's current plan.
     * Returns `INF` when the plan grants unlimited usage.
     */
    public function planLimit(string $key, int|float $default = 0): int|float
    {
        $plan = $this->currentPlan();

        $featureRow = $plan?->planFeatures
            ->firstWhere('feature_key', $key);

        if ($featureRow) {
            if ($featureRow->is_unlimited) {
                return INF;
            }
            return (int) $featureRow->limit_value;
        }

        $limits = $plan?->feature_limits ?? [];
        if (array_key_exists($key, $limits)) {
            $value = $limits[$key];
            return $value === -1 || $value === 'unlimited' ? INF : (int) $value;
        }

        return $default;
    }

    public function domainLimit(): int|float
    {
        return $this->planLimit('domain_limit', 1);
    }

    public function domainsUsed(): int
    {
        return $this->domains()->count();
    }

    public function canAddDomain(): bool
    {
        $limit = $this->domainLimit();
        if ($limit === INF) {
            return true;
        }

        return $this->domainsUsed() < $limit;
    }

    /**
     * Check if user can access a given permission (by slug) or route name.
     * Super admins (is_admin) have access to everything.
     */
    public function canAccess(string $permissionSlugOrRouteName): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $role = $this->role;
        if (! $role) {
            return false;
        }

        $permission = Permission::where('slug', $permissionSlugOrRouteName)
            ->orWhere('route_name', $permissionSlugOrRouteName)
            ->first();

        if (! $permission) {
            return false;
        }

        return $role->permissions()->where('permissions.id', $permission->id)->exists();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'ui_preferences' => 'array',
        ];
    }
}
