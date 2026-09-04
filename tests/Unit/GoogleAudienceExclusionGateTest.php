<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\User;
use App\Services\CrossDomainExclusionSyncService;
use App\Services\GoogleAudienceExclusionService;
use App\Support\CrossDomainIntel;
use App\Support\GoogleIpBlockFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleAudienceExclusionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_queue_when_google_ads_is_not_connected(): void
    {
        $domain = new Domain([
            'source' => Domain::SOURCE_MANUAL,
            'google_ads_account_id' => null,
        ]);
        $domain->setRelation('googleAdsMappings', collect());

        $queued = (new GoogleAudienceExclusionService())->queueBlockedIpIfEligible(
            $domain,
            '74.112.117.2',
            'abnormal_rate_limit',
            isPaidTraffic: true,
        );

        $this->assertFalse($queued);
        $this->assertFalse($domain->hasGoogleAdsConnection());
    }

    public function test_skips_queue_for_allowlisted_google_ips(): void
    {
        $domain = new Domain([
            'source' => Domain::SOURCE_MANUAL,
            'google_ads_account_id' => 12,
        ]);

        $queued = (new GoogleAudienceExclusionService())->queueBlockedIpIfEligible(
            $domain,
            '66.249.89.3',
            'automation',
            isPaidTraffic: true,
        );

        $this->assertTrue($domain->hasGoogleAdsConnection());
        $this->assertFalse($queued);
    }

    public function test_exclusion_manager_row_filter_keeps_detection_and_cross_domain(): void
    {
        $this->assertTrue(GoogleAudienceExclusionService::isExclusionManagerRow('vpn'));
        $this->assertTrue(GoogleAudienceExclusionService::isExclusionManagerRow('cross_domain'));
        $this->assertTrue(GoogleAudienceExclusionService::isExclusionManagerRow('abnormal_rate_limit'));
        $this->assertFalse(GoogleAudienceExclusionService::isExclusionManagerRow('manual'));
        $this->assertFalse(GoogleAudienceExclusionService::isExclusionManagerRow('manual_bulk'));
        $this->assertFalse(GoogleAudienceExclusionService::isExclusionManagerRow('vpn', 'manual_bulk'));
        $this->assertFalse(GoogleAudienceExclusionService::isExclusionManagerRow(''));
    }

    public function test_queue_ip_stores_normalized_cidr_form(): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            $this->markTestSkipped('google_ads_ip_exclusions table missing');
        }

        Bus::fake();

        $user = User::factory()->create();
        $domain = Domain::query()->create([
            'user_id' => $user->id,
            'hostname' => 'example-exclusion.test',
            'domain_key' => 'dk_example_exclusion_'.uniqid(),
            'secret_key' => 'sk_example_exclusion_'.uniqid(),
            'authentication_key' => 'ak_example_exclusion_'.uniqid(),
            'source' => Domain::SOURCE_MANUAL,
        ]);
        $settings = DomainDetectionSetting::query()->create([
            'domain_id' => $domain->id,
            'invalid_bot_action' => 'block',
            'invalid_malicious_action' => 'block',
            'suspicious_enabled' => true,
            'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
        ]);

        (new GoogleAudienceExclusionService())->queueIp(
            $domain,
            '203.0.113.44',
            'cross_domain',
            $settings,
        );

        $stored = GoogleIpBlockFormatter::normalize('203.0.113.44');
        $this->assertSame('203.0.113.44/32', $stored);

        $row = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domain->id)
            ->where('ip', $stored)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('cross_domain', $row->threat_group);
        $this->assertSame('pending', $row->sync_status);
        $this->assertTrue(GoogleAudienceExclusionService::isExclusionManagerRow($row->threat_group, $row->exclusion_mode));
    }

    public function test_cross_domain_sync_queues_matching_ips_into_exclusion_manager(): void
    {
        if (! Schema::hasTable('google_ads_ip_exclusions')) {
            $this->markTestSkipped('google_ads_ip_exclusions table missing');
        }

        Bus::fake();

        $user = User::factory()->create();
        $domain = Domain::query()->create([
            'user_id' => $user->id,
            'hostname' => 'shop-a.test',
            'domain_key' => 'dk_shop_a_'.uniqid(),
            'secret_key' => 'sk_shop_a_'.uniqid(),
            'authentication_key' => 'ak_shop_a_'.uniqid(),
            'source' => Domain::SOURCE_MANUAL,
        ]);
        Domain::query()->create([
            'user_id' => $user->id,
            'hostname' => 'shop-b.test',
            'domain_key' => 'dk_shop_b_'.uniqid(),
            'secret_key' => 'sk_shop_b_'.uniqid(),
            'authentication_key' => 'ak_shop_b_'.uniqid(),
            'source' => Domain::SOURCE_MANUAL,
        ]);
        $settings = DomainDetectionSetting::query()->create([
            'domain_id' => $domain->id,
            'invalid_bot_action' => 'block',
            'invalid_malicious_action' => 'block',
            'suspicious_enabled' => true,
            'audience_exclusion_event' => 'exclude_all_threat_groups_auto',
            'google_exclusion_rules' => [
                'cross_domain_enabled' => true,
                'cross_domain_mode' => 'all',
            ],
        ]);

        $intel = $this->createMock(CrossDomainIntel::class);
        $intel->method('buildForDomainIds')->willReturn([
            [
                'ip' => '198.51.100.10',
                'domain_similarity_label' => 'High',
                'domains' => ['shop-a.test', 'shop-b.test'],
            ],
            [
                'ip' => '198.51.100.11',
                'domain_similarity_label' => 'Low',
                'domains' => ['shop-a.test', 'shop-b.test'],
            ],
        ]);
        $intel->method('filterIpsByMode')->willReturnCallback(function (array $rows, string $mode): array {
            if ($mode === 'domain_similarity') {
                return collect($rows)
                    ->filter(fn ($row) => ($row['domain_similarity_label'] ?? '') === 'High')
                    ->pluck('ip')
                    ->all();
            }

            return collect($rows)->pluck('ip')->all();
        });

        $service = new CrossDomainExclusionSyncService($intel, new GoogleAudienceExclusionService);
        $result = $service->syncForDomain($domain, $settings, [
            'cross_domain_enabled' => true,
            'cross_domain_mode' => 'all',
        ]);

        $this->assertSame(2, $result['matched']);
        $this->assertSame(2, $result['queued']);

        $ips = DB::table('google_ads_ip_exclusions')
            ->where('domain_id', $domain->id)
            ->where('threat_group', 'cross_domain')
            ->pluck('ip')
            ->all();

        $this->assertContains('198.51.100.10/32', $ips);
        $this->assertContains('198.51.100.11/32', $ips);

        $similarity = $service->syncForDomain($domain, $settings, [
            'cross_domain_enabled' => true,
            'cross_domain_mode' => 'domain_similarity',
        ]);
        $this->assertSame(1, $similarity['matched']);
    }
}
