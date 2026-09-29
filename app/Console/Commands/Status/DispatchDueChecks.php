<?php

namespace App\Console\Commands\Status;

use App\Jobs\Status\CheckService;
use App\Models\Status\StatusService;
use App\Services\Status\MaintenanceManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Every-minute entry point: sync maintenance windows, then dispatch
 * one queued CheckService job per due service.
 */
class DispatchDueChecks extends Command
{
    protected $signature = 'status:dispatch-due';

    protected $description = 'Dispatch monitoring jobs for services whose next_check_at has passed.';

    public function handle(MaintenanceManager $maintenance): int
    {
        $windows = $maintenance->syncStates();

        Cache::put('status:monitor:heartbeat', now()->timestamp, 600);

        $dispatched = 0;

        StatusService::due()->orderBy('next_check_at')->chunk(100, function ($services) use (&$dispatched): void {
            foreach ($services as $service) {
                CheckService::dispatch($service->id);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} check(s). Maintenance started: {$windows['started']}, ended: {$windows['ended']}.");

        return self::SUCCESS;
    }
}
