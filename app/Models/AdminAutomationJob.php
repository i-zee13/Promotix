<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminAutomationJob extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'schedule_cron',
        'schedule_label',
        'queue',
        'status',
        'config',
        'last_ran_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_ran_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AdminJobRun::class);
    }
}
