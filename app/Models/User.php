<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

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
        'company_address',
        'support_email',
        'password',
        'avatar_path',
        'google_avatar_url',
        'is_admin',
        'is_super_admin',
        'status',
        'role_id',
        'ui_preferences',
        'timezone',
        'timezone_source',
        'reporting_timezone',
        'workspace_geo_settings',
        'last_login_at',
        'stripe_customer_id',
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

    /**
     * Whether the user's current plan grants a boolean feature flag.
     * Missing keys default to true (legacy plans keep access until Super Admin opts out).
     */
    public function planFeatureEnabled(string $key, bool $defaultWhenMissing = true): bool
    {
        if ($this->bypassesPlanLimits()) {
            return true;
        }

        return \App\Support\DetectionPlanFeatures::enabled($this, $key, $defaultWhenMissing);
    }

    public function domainLimit(): int|float
    {
        if ($this->bypassesPlanLimits()) {
            return INF;
        }

        return $this->planLimit('domain_limit', 1);
    }

    public function domainsUsed(): int
    {
        return $this->domains()->manual()->count();
    }

    public function canAddDomain(): bool
    {
        if ($this->bypassesPlanLimits()) {
            return true;
        }

        $limit = $this->domainLimit();
        if ($limit === INF) {
            return true;
        }

        return $this->domainsUsed() < $limit;
    }

    /**
     * Default route after login or email verification.
     */
    public function homeRouteName(): string
    {
        if ($this->is_super_admin ?? false) {
            return 'super-admin.dashboard';
        }

        if ($this->bypassesOnboarding()) {
            return 'dashboard';
        }

        if (! $this->hasVerifiedEmail()) {
            return 'verification.notice';
        }

        if (! $this->activeSubscription()) {
            return 'onboarding.plan';
        }

        if ($this->needsCardOnboarding()) {
            return 'onboarding.payment';
        }

        return 'dashboard';
    }

    public function hasPaymentMethodOnFile(): bool
    {
        return $this->paymentMethods()->exists();
    }

    /**
     * Card step is required only for self-serve checkout.
     * Admin-created / admin-assigned / admin-verified billing skips it.
     */
    public function needsCardOnboarding(): bool
    {
        if ($this->hasPaymentMethodOnFile()) {
            return false;
        }

        return ! $this->hasAdminSatisfiedBilling();
    }

    /**
     * True when an admin already provisioned or verified this user's active plan.
     */
    public function hasAdminSatisfiedBilling(): bool
    {
        $subscription = $this->activeSubscription();
        if (! $subscription) {
            return false;
        }

        $source = strtolower(trim((string) data_get($subscription->metadata ?? [], 'source', '')));
        if (in_array($source, [
            'super_admin_create_user',
            'super_admin_assign_plan',
            'super_admin_payment_verify',
        ], true)) {
            return true;
        }

        if ($subscription->last_payment_id) {
            $payment = $this->payments()
                ->whereKey($subscription->last_payment_id)
                ->where('status', 'paid')
                ->whereNotNull('verified_by_id')
                ->exists();
            if ($payment) {
                return true;
            }
        }

        return $this->payments()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'paid')
            ->whereNotNull('verified_by_id')
            ->exists();
    }

    /**
     * Admins are not restricted by subscription plan limits.
     */
    public function bypassesPlanLimits(): bool
    {
        return (bool) ($this->is_admin || $this->is_super_admin);
    }

    /**
     * Admins skip email/plan onboarding gates (no plan selection required).
     */
    public function bypassesOnboarding(): bool
    {
        return $this->bypassesPlanLimits();
    }

    /**
     * Check if user can access a given permission (by slug) or route name.
     * Super admins (is_admin) have access to everything.
     */
    public function canAccess(string $permissionSlugOrRouteName): bool
    {
        if ($this->is_admin || ($this->is_super_admin ?? false)) {
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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'ui_preferences' => 'array',
            'workspace_geo_settings' => 'array',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(UserApiKey::class)->latest('id');
    }

    /**
     * Prefer uploaded avatar; otherwise Google/Gmail profile picture.
     */
    public function avatarUrl(): ?string
    {
        if (filled($this->avatar_path) && Storage::disk('public')->exists((string) $this->avatar_path)) {
            return route('profile.avatar.show', [
                'user' => $this->getKey(),
                'v' => $this->updated_at?->getTimestamp(),
            ]);
        }

        $google = trim((string) ($this->google_avatar_url ?? ''));

        return $google !== '' ? $google : null;
    }

    public function avatarInitial(): string
    {
        $source = trim((string) ($this->name ?: $this->email ?: '?'));

        return strtoupper(mb_substr($source, 0, 1));
    }
}
