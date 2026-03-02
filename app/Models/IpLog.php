<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip',
        'user_agent',
        'is_blocked',
        'hits',
        'last_seen_at',
        'last_path',
        'last_referrer',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'last_seen_at' => 'datetime',
    ];
}

