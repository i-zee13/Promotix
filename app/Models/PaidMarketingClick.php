<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidMarketingClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'paid_marketing_visit_id',
        'clicked_at',
        'ip',
        'country',
        'last_click_at',
        'threat_group',
        'campaign',
        'campaignr',
        'browser_name',
        'browser_version',
        'os',
        'paid_id',
        'path',
        'keyword',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'last_click_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PaidMarketingVisit::class, 'paid_marketing_visit_id');
    }
}

