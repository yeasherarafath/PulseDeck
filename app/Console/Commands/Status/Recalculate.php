<?php

namespace App\Console\Commands\Status;

use App\Enums\Status\ServiceStatus;
use App\Models\Status\StatusService;
use App\Services\Status\MaintenanceManager;
use Illuminate\Console\Command;

/**
 * Recomputes current_status from the latest stored check per service.
 * Recovery tool for imports, backfills, and incident drills.
 */
class Recalculate extends Command
{
    protected $signature = 'status:recalculate {--service= : Service slug or ID to recalculate (default: all).}';

    protected $description = 'Recompute service statuses from their latest checks.';

    public function handle(MaintenanceManager $maintenance): int
    {
        $query = StatusService::query();

        if ($this->option('service')) {
            $query->where('slug', $this->option('service'))->orWhere('id', $this->option('service'));
        }

        $count = 0;

        $query->chunk(100, function ($services) use ($maintenance, &$count): void {
            foreach ($services as $service) {
                $latest = $service->checks()->orderByDesc('checked_at')->orderByDesc('id')->first();

                if (! $latest) {
                    continue;
                }

                if ($maintenance->isUnderMaintenance($service)) {
                    $service->forceFill(['current_status' => ServiceStatus::Maintenance])->save();
                } else {
                    $service->forceFill(['current_status' => $latest->status->toServiceStatus()])->save();
                }

                $count++;
            }
        });

        $this->info("Recalculated {$count} service(s).");

        return self::SUCCESS;
    }
}
