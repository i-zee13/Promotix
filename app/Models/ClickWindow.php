<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickWindow extends Model
{
    protected $table = 'click_windows';

    protected $fillable = [
        'domain_id',
        'entity_type',
        'entity_id',
        'window_key',
        'click_count',
        'window_started_at',
        'last_click_at',
    ];

    protected function casts(): array
    {
        return [
            'click_count' => 'integer',
            'window_started_at' => 'datetime',
            'last_click_at' => 'datetime',
        ];
    }
}
