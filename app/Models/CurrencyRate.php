<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = [
        'code',
        'country_code',
        'country_name',
        'name',
        'symbol',
        'units_per_usd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'units_per_usd' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
