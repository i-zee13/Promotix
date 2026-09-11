<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_connection_id',
        'customer_id',
        'display_customer_id',
        'account_name',
        'time_zone',
        'currency_code',
        'manager_customer_id',
        'is_manager',
        'google_tag_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_manager' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleConnection::class, 'google_connection_id');
    }

    public function domainMappings(): HasMany
    {
        return $this->hasMany(DomainGoogleAdsMapping::class);
    }

    public function advertisedHosts(): HasMany
    {
        return $this->hasMany(GoogleAdsAdvertisedHost::class);
    }

    public function linkedDomains(): HasMany
    {
        return $this->hasMany(Domain::class, 'google_ads_account_id');
    }

    /** Accounts successfully loaded from Google Ads API (name + access confirmed). */
    public function scopeSynced($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('account_name')
            ->where('account_name', '!=', '');
    }

    public function displayLabel(): string
    {
        $name = trim((string) $this->account_name);
        if ($name !== '') {
            return $name;
        }

        $formatted = $this->formattedCustomerId();

        return $formatted !== '' ? $formatted : (string) ($this->display_customer_id ?: $this->customer_id);
    }

    /** Spec: Customer ID is XXX-XXX-XXXX — never AW-… */
    public static function formatCustomerId(?string $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^AW-/i', $value)) {
            $value = substr($value, 3);
        }
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6);
        }

        return $digits !== '' ? $digits : '';
    }

    public function formattedCustomerId(): string
    {
        $display = trim((string) $this->display_customer_id);
        if ($display !== '' && ! preg_match('/^AW-/i', $display)) {
            return self::formatCustomerId($display);
        }

        return self::formatCustomerId((string) $this->customer_id);
    }

    /**
     * Unique Ads accounts for filter dropdowns (label + Customer ID / currency subheading).
     *
     * @return list<array{id: string, label: string, sub: string, currency_code: string, currency_label: string, is_manager: bool}>
     */
    public static function filterOptionsForUser(\App\Models\User $user): array
    {
        return self::query()
            ->whereHas('connection', fn ($q) => $q->where('user_id', $user->id))
            ->synced()
            ->orderBy('account_name')
            ->orderBy('customer_id')
            ->get(['id', 'account_name', 'customer_id', 'display_customer_id', 'is_manager', 'manager_customer_id', 'currency_code'])
            ->unique('id')
            ->values()
            ->map(function (self $account): array {
                $cid = $account->formattedCustomerId();
                $currencyCode = \App\Support\AccountCurrency::normalize((string) ($account->currency_code ?: 'USD'));
                $subParts = [];
                if ($cid !== '') {
                    $subParts[] = 'Customer ID '.$cid;
                }
                $subParts[] = 'Currency '.$currencyCode;
                if ($account->is_manager) {
                    $subParts[] = 'Manager account';
                } elseif (filled($account->manager_customer_id)) {
                    $mcc = self::formatCustomerId((string) $account->manager_customer_id);
                    if ($mcc !== '') {
                        $subParts[] = 'Under MCC '.$mcc;
                    }
                }

                return [
                    'id' => (string) $account->id,
                    'label' => $account->displayLabel(),
                    'sub' => implode(' · ', $subParts),
                    'currency_code' => $currencyCode,
                    'currency_label' => \App\Support\AccountCurrency::label($currencyCode),
                    'is_manager' => (bool) $account->is_manager,
                ];
            })
            ->all();
    }

    /** Spec: Google Tag ID is AW-… — separate from Customer ID */
    public function resolvedGoogleTagId(): string
    {
        $tag = trim((string) $this->google_tag_id);
        if ($tag !== '' && preg_match('/^AW-/i', $tag)) {
            return strtoupper(substr($tag, 0, 3)).substr($tag, 3);
        }
        $display = trim((string) $this->display_customer_id);
        if ($display !== '' && preg_match('/^AW-/i', $display)) {
            return strtoupper(substr($display, 0, 3)).substr($display, 3);
        }
        $digits = preg_replace('/\D+/', '', (string) $this->customer_id) ?: '';

        return $digits !== '' ? 'AW-'.$digits : '';
    }
}

