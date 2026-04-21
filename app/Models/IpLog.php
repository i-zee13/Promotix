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
        'iphub_block',
        'iphub_proxy_type',
        'iphub_block_reason',
        'ipdetails_abuser_score',
        'ipdetails_raw',
        'abuse_confidence_score',
        'abuse_total_reports',
        'abuse_is_tor',
        'intel_country_code',
        'intel_country_name',
        'intel_isp',
        'intel_checked_at',
        'intel_status',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'last_seen_at' => 'datetime',
            'iphub_proxy_type' => 'array',
            'ipdetails_raw' => 'array',
            'abuse_is_tor' => 'boolean',
            'intel_checked_at' => 'datetime',
        ];
    }
}

