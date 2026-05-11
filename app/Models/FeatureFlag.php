<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'description', 'enabled', 'plan_scope'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'plan_scope' => 'array',
        ];
    }
}
