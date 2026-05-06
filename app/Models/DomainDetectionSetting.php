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
        'allow_list_enabled',
        'allow_list_ips',
        'audience_exclusion_event',
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
            'allow_list_enabled' => 'boolean',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}

