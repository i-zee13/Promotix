<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsAccount;
use App\Services\GoogleAdsAccountTimezoneService;
use App\Support\UserTimezone;
use Illuminate\Console\Command;

class SyncGoogleAdsAccountTimezones extends Command
{
    protected $signature = 'google-ads:sync-timezones
        {--user= : Only accounts for this user ID}
        {--account= : Only this google_ads_accounts.id}
        {--all : Refresh even when time_zone is already set}';

    protected $description = 'Fetch and save Google Ads account timezones (missing by default)';

    public function handle(GoogleAdsAccountTimezoneService $timezoneService): int
    {
        $refreshAll = (bool) $this->option('all');
        $userId = trim((string) $this->option('user'));
        $accountId = trim((string) $this->option('account'));

        $query = GoogleAdsAccount::query()
            ->where('is_active', true)
            ->where('is_manager', false)
            ->with('connection')
            ->orderBy('id');

        if (! $refreshAll) {
            $query->where(function ($q) {
                $q->whereNull('time_zone')->orWhere('time_zone', '');
            });
        }

        if ($userId !== '') {
            $query->whereHas('connection', fn ($q) => $q->where('user_id', (int) $userId));
        }

        if ($accountId !== '') {
            $query->where('id', (int) $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info($refreshAll
                ? 'No active client Google Ads accounts found for the given filters.'
                : 'All matching accounts already have a timezone. Use --all to force refresh.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        if ((string) config('services.google_ads.developer_token') === '') {
            $this->error('Missing GOOGLE_ADS_DEVELOPER_TOKEN in .env');

            return self::FAILURE;
        }

        foreach ($accounts as $account) {
            $label = $account->displayLabel() . ' (' . ($account->display_customer_id ?: $account->customer_id) . ')';

            if (! $account->connection) {
                $this->warn("Skipped {$label}: no Google connection.");
                $skipped++;

                continue;
            }

            if (! $account->connection->refresh_token) {
                $this->error("✗ {$label}: OAuth refresh token missing — reconnect Google in Integrations.");
                $failed++;

                continue;
            }

            $before = $account->time_zone;
            $timezone = $timezoneService->refreshForAccount($account);
            $account->refresh();

            if (UserTimezone::isValid($timezone)) {
                $synced++;
                $note = $before && $before !== $timezone ? " (was {$before})" : '';
                $this->line("✓ {$label} → {$timezone}{$note}");
            } else {
                $failed++;
                $this->error("✗ {$label}: could not fetch timezone (check OAuth token & GOOGLE_ADS_DEVELOPER_TOKEN).");
            }
        }

        $this->newLine();
        $this->info("Done. Synced: {$synced}, failed: {$failed}, skipped: {$skipped}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
