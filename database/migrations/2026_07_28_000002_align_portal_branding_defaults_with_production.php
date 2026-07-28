<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align new branding token defaults with the existing production palette
 * (only when rows still use the first-pass purple-tinted defaults).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        $alignments = [
            'branding.color_surface' => ['from' => ['#1E1033', '#1e1033', '#151515'], 'to' => '#212121'],
            'branding.color_text_muted' => ['from' => ['#B8A4D4', '#b8a4d4'], 'to' => '#A9A9A9'],
            'branding.color_outline' => ['from' => ['#4A2D6E', '#4a2d6e'], 'to' => '#3D3D3D'],
        ];

        foreach ($alignments as $key => $cfg) {
            $row = DB::table('app_settings')->where('key', $key)->first();
            if (! $row) {
                continue;
            }

            $current = strtoupper((string) $row->value);
            $from = array_map('strtoupper', $cfg['from']);

            if (in_array($current, $from, true)) {
                DB::table('app_settings')->where('key', $key)->update([
                    'value' => $cfg['to'],
                    'updated_at' => now(),
                ]);
            }
        }

        if (class_exists(\App\Models\AppSetting::class)) {
            \App\Models\AppSetting::flushCache();
        }
    }

    public function down(): void
    {
        // Non-destructive alignment; no rollback needed.
    }
};
