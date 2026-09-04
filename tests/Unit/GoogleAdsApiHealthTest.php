<?php

namespace Tests\Unit;

use App\Models\GoogleConnection;
use App\Support\GoogleAdsApiHealth;
use Tests\TestCase;

class GoogleAdsApiHealthTest extends TestCase
{
    public function test_missing_connection_is_pending(): void
    {
        $this->assertSame('pending', GoogleAdsApiHealth::status(null, 0));
    }

    public function test_oauth_without_accounts_or_sync_is_pending(): void
    {
        $connection = new GoogleConnection([
            'health_status' => null,
            'last_sync_status' => null,
            'last_sync_message' => null,
        ]);

        $this->assertSame('pending', GoogleAdsApiHealth::status($connection, 0));
    }

    public function test_synced_accounts_are_healthy_even_when_metrics_save_zero_rows(): void
    {
        $connection = new GoogleConnection([
            'health_status' => 'error',
            'last_sync_status' => 'error',
            'last_sync_message' => 'Google Ads API returned no campaign rows for customer 123',
            'last_sync_at' => now(),
        ]);

        $this->assertSame('ok', GoogleAdsApiHealth::status($connection, 2));
    }

    public function test_token_failure_stays_error(): void
    {
        $connection = new GoogleConnection([
            'health_status' => 'error',
            'last_sync_status' => 'error',
            'last_sync_message' => 'Token refresh failed. Reconnect Google.',
            'last_sync_at' => now(),
        ]);

        $this->assertSame('error', GoogleAdsApiHealth::status($connection, 2));
    }

    public function test_pending_status_with_accounts_is_healthy(): void
    {
        $connection = new GoogleConnection([
            'health_status' => 'pending',
            'last_sync_status' => 'pending',
        ]);

        $this->assertSame('ok', GoogleAdsApiHealth::status($connection, 1));
    }
}
