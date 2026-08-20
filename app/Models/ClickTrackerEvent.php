<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickTrackerEvent extends Model
{
    protected $fillable = [
        'cxtrk_id',
        'domain_id',
        'landing_visit_id',
        'click_id',
        'click_id_type',
        'landing_url',
        'ip',
        'user_agent',
        'cx_account',
        'cx_campaign',
        'cx_adgroup',
        'cx_creative',
        'cx_keyword',
        'cx_registry',
        'tracked_at',
        'joined_at',
    ];

    protected $casts = [
        'cx_registry' => 'array',
        'tracked_at' => 'datetime',
        'joined_at' => 'datetime',
    ];
}
