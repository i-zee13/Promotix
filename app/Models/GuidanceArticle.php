<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceArticle extends Model
{
    protected $fillable = [
        'title',
        'question_variants',
        'answer',
        'steps',
        'related_page',
        'department',
        'keywords',
        'role_visibility',
        'package_visibility',
        'is_published',
        'last_updated_by',
    ];

    protected function casts(): array
    {
        return [
            'question_variants' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
