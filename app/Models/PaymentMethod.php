<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'brand',
        'last_four',
        'exp_month',
        'exp_year',
        'is_primary',
        'is_temporary',
        'stripe_payment_method_id',
        'verification_status',
        'verification_charge_id',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_temporary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedLabel(): string
    {
        $brand = $this->brand ?: 'Card';

        return trim($brand.' •••• '.($this->last_four ?: '0000'));
    }
}
