<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Services\Mail\AppMailer;
use App\Support\PageAnalyticsAggregator;
use App\Support\UserTimezone;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendMonthlyAnalyticsReport extends Command
{
    protected $signature = 'analytics:send-monthly-report {--user= : Limit to one user id} {--dry-run : Print recipients without sending}';

    protected $description = 'Email monthly Analytics summary reports to opted-in workspace owners';

    public function handle(PageAnalyticsAggregator $aggregator): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('visits')) {
            $this->warn('Required tables missing; skipping monthly analytics reports.');

            return self::SUCCESS;
        }

        $userId = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query()->whereNotNull('email');
        if ($userId) {
            $query->whereKey($userId);
        }

        $sent = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(50, function ($users) use ($aggregator, $dryRun, &$sent, &$skipped): void {
            foreach ($users as $user) {
                $prefs = (array) ($user->ui_preferences ?? []);
                $notify = (array) ($prefs['notifications'] ?? []);
                if (! (bool) ($notify['monthly_report_email'] ?? false)) {
                    $skipped++;

                    continue;
                }

                $domainIds = Domain::query()
                    ->where('user_id', $user->id)
                    ->pluck('id')
                    ->all();

                if ($domainIds === []) {
                    $skipped++;

                    continue;
                }

                $tz = UserTimezone::forUser($user);
                $to = Carbon::now($tz)->subMonth()->endOfMonth();
                $from = $to->copy()->startOfMonth();

                $payload = $aggregator->build($domainIds, $from, $to);
                $subject = sprintf('Clickronix Analytics Report — %s', $from->format('F Y'));
                $html = view('emails.monthly-analytics-report', [
                    'user' => $user,
                    'from' => $from,
                    'to' => $to,
                    'payload' => $payload,
                ])->render();

                if ($dryRun) {
                    $this->line("Would send to {$user->email} ({$from->toDateString()} → {$to->toDateString()})");
                    $sent++;

                    continue;
                }

                if (AppMailer::sendRaw($user->email, $subject, $html, 'monthly_analytics_report')) {
                    $sent++;
                    $this->info("Sent monthly report to {$user->email}");
                } else {
                    $this->error("Failed sending to {$user->email}");
                }
            }
        });

        $this->info("Monthly analytics reports complete. sent={$sent} skipped={$skipped}");

        return self::SUCCESS;
    }
}
