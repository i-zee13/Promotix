<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainDetectionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'invalid_bot_action',
        'invalid_malicious_action',
        'suspicious_enabled',
        'suspicious_matrix',
        'session_recordings',
        'frequency_capping',
        'out_of_geo_enabled',
        'out_of_geo_countries',
        'out_of_geo_audience',
        'google_geo_block_enabled',
        'google_geo_block_audience',
        'allow_list_enabled',
        'allow_list_ips',
        'block_list_enabled',
        'block_list_ips',
        'audience_exclusion_event',
        'google_exclusion_rules',
    ];

    protected function casts(): array
    {
        return [
            'suspicious_enabled' => 'boolean',
            'suspicious_matrix' => 'array',
            'session_recordings' => 'boolean',
            'frequency_capping' => 'boolean',
            'out_of_geo_enabled' => 'boolean',
            'out_of_geo_countries' => 'array',
            'out_of_geo_audience' => 'array',
            'google_geo_block_enabled' => 'boolean',
            'google_geo_block_audience' => 'array',
            'allow_list_enabled' => 'boolean',
            'block_list_enabled' => 'boolean',
            'google_exclusion_rules' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}

