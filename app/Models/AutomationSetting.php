<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AutomationSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public static function tableReady(): bool
    {
        return Schema::hasTable('automation_settings');
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (! self::tableReady()) {
            return $default;
        }

        $row = Cache::remember("automation_setting:{$key}", now()->addMinutes(5), function () use ($key) {
            return self::query()->where('setting_key', $key)->first();
        });

        if (! $row) {
            return $default;
        }

        return $row->setting_value ?? $default;
    }

    public static function isEnabled(string $key, bool $default = false): bool
    {
        if (! self::tableReady()) {
            return $default;
        }

        $row = self::query()->where('setting_key', $key)->first();

        return $row ? (bool) $row->is_enabled : $default;
    }

    public static function intValue(string $key, int $default = 0): int
    {
        return (int) self::getValue($key, $default);
    }

    public static function upsert(string $key, ?string $value, bool $enabled): void
    {
        self::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'is_enabled' => $enabled]
        );
        Cache::forget("automation_setting:{$key}");
    }
}
