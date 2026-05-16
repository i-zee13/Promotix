<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\IpLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()
            ->where('email', 'admin@example.com')
            ->orWhere('is_admin', true)
            ->orderBy('id')
            ->first();

        if (! $user) {
            $this->command?->warn('No admin user found. Run AdminUserSeeder first.');
            return;
        }

        $domain = Domain::query()->updateOrCreate(
            ['user_id' => $user->id, 'hostname' => 'demo.promotix.test'],
            [
                'status' => 'active',
                'domain_key' => 'demo-'.Str::lower(Str::random(16)),
                'secret_key' => 'sec-'.Str::lower(Str::random(32)),
                'authentication_key' => 'auth-'.Str::lower(Str::random(32)),
                'tag_connected' => true,
                'paid_marketing_connected' => true,
                'bot_mitigation_connected' => true,
                'monitoring_only_mode' => false,
                'last_seen_at' => now(),
            ]
        );

        $campaigns = ['Brand Defense', 'Search - US', 'Retargeting', 'Competitor Guard'];
        $threatGroups = ['Invalid Visits', 'Bot Clicks', 'Proxy Traffic', 'Repeated IPs'];

        if (Schema::hasTable('visits')) {
            if (Schema::hasTable('detection_logs')) {
                DB::table('detection_logs')->where('domain_id', $domain->id)->delete();
            }
            DB::table('visits')->where('domain_id', $domain->id)->delete();

            for ($i = 0; $i < 180; $i++) {
                $visitedAt = Carbon::now()->subDays(fake()->numberBetween(0, 6))->subMinutes(fake()->numberBetween(0, 900));
                $invalid = fake()->boolean(32);
                $campaign = fake()->randomElement($campaigns);

                $visitId = DB::table('visits')->insertGetId([
                    'domain_id' => $domain->id,
                    'session_id' => (string) Str::uuid(),
                    'ip' => fake()->ipv4(),
                    'country' => fake()->randomElement(['US', 'GB', 'PK', 'AE', 'DE']),
                    'device' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
                    'browser' => fake()->randomElement(['Chrome', 'Safari', 'Edge', 'Firefox']),
                    'os' => fake()->randomElement(['Windows', 'macOS', 'iOS', 'Android']),
                    'url' => fake()->randomElement(['/pricing', '/landing/google-ads', '/demo', '/checkout']),
                    'referrer' => fake()->url(),
                    'utm_source' => fake()->randomElement(['google', 'meta', 'bing']),
                    'utm_medium' => 'cpc',
                    'utm_campaign' => $campaign,
                    'utm_term' => fake()->randomElement(['bot protection', 'ad fraud', 'click fraud']),
                    'is_paid_traffic' => true,
                    'is_invalid_traffic' => $invalid,
                    'visited_at' => $visitedAt,
                    'created_at' => $visitedAt,
                    'updated_at' => $visitedAt,
                ]);

                if ($invalid && Schema::hasTable('detection_logs')) {
                    DB::table('detection_logs')->insert([
                        'domain_id' => $domain->id,
                        'visit_id' => $visitId,
                        'ip' => fake()->ipv4(),
                        'threat_score' => fake()->numberBetween(55, 98),
                        'threat_group' => fake()->randomElement($threatGroups),
                        'action_taken' => fake()->randomElement(['block', 'flag']),
                        'reasons' => json_encode(['demo_faker_data']),
                        'detected_at' => $visitedAt,
                        'created_at' => $visitedAt,
                        'updated_at' => $visitedAt,
                    ]);
                }
            }
        }

        if (Schema::hasTable('paid_marketing_visits') && Schema::hasTable('paid_marketing_clicks')) {
            $paidVisitId = DB::table('paid_marketing_visits')->insertGetId([
                'domain_id' => $domain->id,
                'ip' => fake()->ipv4(),
                'visits' => 24,
                'campaign' => 'Brand Defense',
                'last_click_at' => now(),
                'threat_group' => 'Repeated IPs',
                'threat_type' => 'suspicious',
                'country' => 'US',
                'platform' => 'Google Ads',
                'last_path' => '/pricing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($campaigns as $campaign) {
                DB::table('paid_marketing_clicks')->insert([
                    'paid_marketing_visit_id' => $paidVisitId,
                    'clicked_at' => now()->subHours(fake()->numberBetween(1, 48)),
                    'ip' => fake()->ipv4(),
                    'country' => fake()->randomElement(['US', 'GB', 'AE']),
                    'last_click_at' => now(),
                    'threat_group' => fake()->randomElement($threatGroups),
                    'campaign' => $campaign,
                    'campaignr' => $campaign,
                    'browser_name' => fake()->randomElement(['Chrome', 'Safari', 'Edge']),
                    'browser_version' => (string) fake()->numberBetween(90, 125),
                    'os' => fake()->randomElement(['Windows', 'macOS', 'Android']),
                    'paid_id' => 'gclid_'.Str::lower(Str::random(10)),
                    'path' => fake()->randomElement(['/pricing', '/demo', '/checkout']),
                    'keyword' => fake()->randomElement(['click fraud', 'invalid traffic']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        for ($i = 0; $i < 24; $i++) {
            IpLog::query()->updateOrCreate(
                ['ip' => "198.51.100.{$i}"],
                [
                    'user_agent' => fake()->userAgent(),
                    'is_blocked' => $i % 2 === 0,
                    'hits' => fake()->numberBetween(4, 80),
                    'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
                    'last_path' => fake()->randomElement(['/pricing', '/landing/google-ads', '/checkout']),
                    'last_referrer' => fake()->url(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command?->info("Dashboard demo data seeded for {$user->email}.");
    }
}
