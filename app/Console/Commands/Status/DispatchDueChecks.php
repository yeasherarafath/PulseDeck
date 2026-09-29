<?php

namespace App\Console\Commands\Status;

use App\Enums\Status\ServiceStatus;
use App\Jobs\Status\CheckService;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
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

        $stale = $this->markStaleServices();

        $this->info("Dispatched {$dispatched} check(s). Maintenance started: {$windows['started']}, ended: {$windows['ended']}. Stale: {$stale}.");

        return self::SUCCESS;
    }

    /**
     * Services with no check inside interval × stale_after_multiplier are
     * honestly Unknown (worker/scheduler outage) — not left showing a
     * days-old green. Silent state hygiene: no checks, events, or mails.
     */
    private function markStaleServices(): int
    {
        $multiplier = max(1, (int) StatusSetting::get('stale_after_multiplier', 3));
        $stale = 0;

        StatusService::active()
            ->where('current_status', '!=', ServiceStatus::Unknown->value)
            ->whereNotNull('last_checked_at')
            ->chunk(100, function ($services) use ($multiplier, &$stale): void {
                foreach ($services as $service) {
                    $grace = max(60, $service->check_interval) * $multiplier;

                    if ($service->last_checked_at->lt(now()->subSeconds($grace))) {
                        $service->forceFill(['current_status' => ServiceStatus::Unknown])->save();
                        $stale++;
                    }
                }
            });

        return $stale;
    }
}
