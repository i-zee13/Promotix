<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredSessionRecordings extends Command
{
    protected $signature = 'session-recordings:purge {--dry-run : Count only}';

    protected $description = 'Delete session recordings older than each domain retention policy (SR-06)';

    public function handle(): int
    {
        if (! Schema::hasTable('visit_session_recordings')) {
            $this->warn('visit_session_recordings table missing.');

            return self::SUCCESS;
        }

        $defaultDays = 30;
        $settings = Schema::hasTable('domain_detection_settings')
            ? DB::table('domain_detection_settings')->get(['domain_id', 'recording_retention_days'])
            : collect();

        $byDomain = $settings->keyBy('domain_id');
        $deleted = 0;

        $domainIds = DB::table('visit_session_recordings')->distinct()->pluck('domain_id');
        foreach ($domainIds as $domainId) {
            $days = (int) ($byDomain->get($domainId)?->recording_retention_days ?? $defaultDays);
            $days = max(1, min(3650, $days));
            $cutoff = now('UTC')->subDays($days);

            $query = DB::table('visit_session_recordings')
                ->where('domain_id', $domainId)
                ->where('created_at', '<', $cutoff);

            $count = (int) $query->count();
            if ($count === 0) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Domain {$domainId}: would delete {$count} (retention {$days}d)");
            } else {
                $query->delete();
                $this->line("Domain {$domainId}: deleted {$count} (retention {$days}d)");
            }
            $deleted += $count;
        }

        $this->info(($this->option('dry-run') ? 'Would delete' : 'Deleted') . " {$deleted} recordings.");

        return self::SUCCESS;
    }
}
