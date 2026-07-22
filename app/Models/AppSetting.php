<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'label',
        'description',
        'type',
        'value',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function getTypedValueAttribute(): mixed
    {
        return self::cast($this->value, $this->type);
    }

    public static function cast(?string $raw, string $type): mixed
    {
        return match ($type) {
            'boolean'  => (bool) ((int) ($raw ?? 0)),
            'integer'  => (int) ($raw ?? 0),
            'float'    => (float) ($raw ?? 0),
            'json'     => json_decode($raw ?? 'null', true),
            default    => $raw,
        };
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $cached = Cache::remember("app_setting:{$key}", now()->addMinutes(5), function () use ($key) {
            $row = self::query()->where('key', $key)->first(['value', 'type']);

            return $row ? ['value' => $row->value, 'type' => $row->type] : null;
        });

        if (! $cached) {
            return $default;
        }

        $casted = self::cast($cached['value'], $cached['type']);

        if ($casted === null || $casted === '' || $casted === false) {
            // For booleans we still want explicit false through; only fall back when truly unset.
            if ($cached['type'] === 'boolean') {
                return $casted;
            }
            if ($casted === null || $casted === '') {
                return $default;
            }
        }

        return $casted;
    }

    public static function set(string $key, mixed $value, ?string $type = null, ?string $group = null, ?string $label = null): void
    {
        $row = self::query()->where('key', $key)->first();

        if (! $row) {
            $group ??= str_contains($key, '.') ? explode('.', $key, 2)[0] : 'general';
            $type ??= 'string';
            $row = new self([
                'group' => $group,
                'key' => $key,
                'label' => $label ?? $key,
                'type' => $type,
                'value' => '',
                'is_public' => true,
            ]);
        }

        $serialized = match ($row->type) {
            'boolean' => $value ? '1' : '0',
            'json'    => json_encode($value),
            default   => is_scalar($value) || $value === null ? (string) $value : json_encode($value),
        };

        $row->value = $serialized;
        $row->save();
        Cache::forget("app_setting:{$key}");
    }

    public static function flushCache(): void
    {
        foreach (self::query()->pluck('key') as $key) {
            Cache::forget("app_setting:{$key}");
        }
    }
}
