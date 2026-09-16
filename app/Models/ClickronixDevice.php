<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClickronixDevice extends Model
{
    protected $table = 'clickronix_devices';

    protected $fillable = [
        'domain_id',
        'device_id',
        'device_token',
        'fingerprint_id',
        'device_confidence',
        'ga4_client_id',
        'last_gclid',
        'last_gbraid',
        'last_wbraid',
        'first_paid_click_at',
        'last_paid_click_at',
        'paid_click_count',
        'ip_count',
        'ip_change_count',
        'invalid_click_count',
        'valid_click_count',
        'conversion_count',
        'last_conversion_at',
        'last_conversion_type',
        'automation_detected',
        'proxy',
        'vpn',
        'datacenter',
        'risk_score',
        'risk_reason',
        'risk_label',
        'exclusion_candidate',
        'ga4_exclusion_sent',
        'ga4_exclusion_sent_at',
        'last_ip',
        'ip_history',
        'meta',
    ];

    protected $casts = [
        'device_confidence' => 'float',
        'first_paid_click_at' => 'datetime',
        'last_paid_click_at' => 'datetime',
        'last_conversion_at' => 'datetime',
        'ga4_exclusion_sent_at' => 'datetime',
        'automation_detected' => 'boolean',
        'proxy' => 'boolean',
        'vpn' => 'boolean',
        'datacenter' => 'boolean',
        'exclusion_candidate' => 'boolean',
        'ga4_exclusion_sent' => 'boolean',
        'ip_history' => 'array',
        'meta' => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
