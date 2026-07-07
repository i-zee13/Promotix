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
            ['group' => 'branding', 'key' => 'branding.logo_url', 'label' => 'Logo URL', 'type' => 'string', 'value' => '/images/logo.png', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.favicon_url', 'label' => 'Favicon URL', 'type' => 'string', 'value' => '/favicon.ico', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.font_family', 'label' => 'Font family', 'type' => 'string', 'value' => 'Inter', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.font_size_base', 'label' => 'Base font size (px)', 'type' => 'integer', 'value' => '16', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_primary', 'label' => 'Primary color', 'type' => 'string', 'value' => '#6400B2', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_secondary', 'label' => 'Secondary color', 'type' => 'string', 'value' => '#6706B3', 'is_public' => true],
            ['group' => 'branding', 'key' => 'branding.color_background', 'label' => 'Background color', 'type' => 'string', 'value' => '#0D0D0D', 'is_public' => true],
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
            'branding.logo_url',
            'branding.favicon_url',
            'branding.font_family',
            'branding.font_size_base',
            'branding.color_primary',
            'branding.color_secondary',
            'branding.color_background',
        ])->delete();
    }
};
