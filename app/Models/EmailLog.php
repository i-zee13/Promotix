<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'template_key',
        'recipient',
        'status',
        'provider_message_id',
        'retry_count',
        'error',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'retry_count' => 'integer',
        ];
    }
}
