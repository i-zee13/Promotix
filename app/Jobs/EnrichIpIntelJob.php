<?php

namespace App\Jobs;

use App\Models\IpLog;
use App\Services\IpIntel\IpFraudReconciler;
use App\Services\IpIntel\IpIntelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnrichIpIntelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public int $ipLogId)
    {
    }

    public function handle(IpIntelService $intel, IpFraudReconciler $reconciler): void
    {
        $log = IpLog::find($this->ipLogId);
        if (! $log) {
            return;
        }

        if ($intel->isFresh($log)) {
            $log->intel_status = 'skipped';
            $log->save();

            return;
        }

        $log = $intel->enrich($log);

        if ($log->intel_status === 'ok') {
            $reconciler->reconcileForIp($log);
        }
    }
}
