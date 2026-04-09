<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PaidMarketingClick;

class PaidMarketingVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'ip',
        'visits',
        'campaign',
        'last_click_at',
        'threat_group',
        'threat_type',
        'country',
        'platform',
        'last_path',
    ];

    protected function casts(): array
    {
        return [
            'last_click_at' => 'datetime',
            'visits' => 'integer',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(PaidMarketingClick::class, 'paid_marketing_visit_id');
    }
}

