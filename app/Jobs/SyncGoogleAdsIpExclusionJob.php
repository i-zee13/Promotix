<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\GoogleAdsIpExclusionSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncGoogleAdsIpExclusionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $domainId,
        public string $ip,
    ) {
    }

    public function handle(GoogleAdsIpExclusionSyncService $sync): void
    {
        $domain = Domain::query()->find($this->domainId);
        if (! $domain) {
            return;
        }

        try {
            $sync->syncRow($domain, $this->ip);
        } catch (\Throwable $e) {
            Log::error('SyncGoogleAdsIpExclusionJob failed', [
                'domain_id' => $this->domainId,
                'ip' => $this->ip,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
