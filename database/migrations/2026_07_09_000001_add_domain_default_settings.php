<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.bot_protection_enabled', 'label' => 'Enable bot protection by default', 'type' => 'boolean', 'value' => '1'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.paid_ads_protection_enabled', 'label' => 'Enable paid ads protection', 'type' => 'boolean', 'value' => '1'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.auto_block_enabled', 'label' => 'Auto-block enabled', 'type' => 'boolean', 'value' => '1'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.block_data_centers', 'label' => 'Block data centers', 'type' => 'boolean', 'value' => '1'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.block_proxies', 'label' => 'Block proxies', 'type' => 'boolean', 'value' => '0'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.allow_known_crawlers', 'label' => 'Allow known crawlers', 'type' => 'boolean', 'value' => '1'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.risk_score_threshold', 'label' => 'Risk score threshold', 'type' => 'integer', 'value' => '70'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.frequency_cap', 'label' => 'Frequency cap (clicks per minute)', 'type' => 'integer', 'value' => '1'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.requests_per_minute', 'label' => 'Requests per minute', 'type' => 'integer', 'value' => '200'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.tracker_script', 'label' => 'Default tracker script', 'type' => 'text', 'value' => '<script src="https://yourapp.com/tag/{domain_key}.js" class="pm_tag"></script>'],
            ['group' => 'domain_defaults', 'key' => 'domain_defaults.auto_verify_rules', 'label' => 'Auto-verify DNS rules', 'type' => 'json', 'value' => json_encode([
                ['host' => 'ad.domain.com', 'record' => '_domainkey.domain.com', 'expected' => '%dkim_regexp%'],
                ['host' => 'click.domain.com', 'record' => '_domainkey.domain.com', 'expected' => '%dkim_regexp%'],
            ])],
        ];

        foreach ($rows as $row) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['is_public' => false, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->where('group', 'domain_defaults')->delete();
    }
};
