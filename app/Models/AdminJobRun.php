<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminJobRun extends Model
{
    protected $fillable = [
        'admin_automation_job_id',
        'status',
        'attempt',
        'output_log',
        'error_message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AdminAutomationJob::class, 'admin_automation_job_id');
    }
}
