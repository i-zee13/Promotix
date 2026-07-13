<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\GoogleAdsLocationExclusionSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncGoogleAdsLocationExclusionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $domainId,
        public int $rowId,
    ) {}

    public function handle(GoogleAdsLocationExclusionSyncService $sync): void
    {
        $domain = Domain::query()->find($this->domainId);
        if (! $domain) {
            return;
        }

        try {
            $sync->syncRow($domain, $this->rowId);
        } catch (\Throwable $e) {
            Log::error('SyncGoogleAdsLocationExclusionJob failed', [
                'domain_id' => $this->domainId,
                'row_id' => $this->rowId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
