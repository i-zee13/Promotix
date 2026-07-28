<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        $now = now();
        $rows = [
            ['group' => 'branding', 'key' => 'branding.color_surface', 'label' => 'Surface / card color', 'type' => 'string', 'value' => '#212121', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_text', 'label' => 'Text color', 'type' => 'string', 'value' => '#FFFFFF', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_text_muted', 'label' => 'Muted text color', 'type' => 'string', 'value' => '#A9A9A9', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_outline', 'label' => 'Outline / border color', 'type' => 'string', 'value' => '#3D3D3D', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_cta', 'label' => 'Button background (CTA)', 'type' => 'string', 'value' => '#FFFFFF', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_cta_text', 'label' => 'Button text color', 'type' => 'string', 'value' => '#111111', 'is_public' => true],
        ];

        foreach ($rows as $row) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->whereIn('key', [
            'branding.color_surface',
            'branding.color_text',
            'branding.color_text_muted',
            'branding.color_outline',
            'branding.color_cta',
            'branding.color_cta_text',
        ])->delete();
    }
};
