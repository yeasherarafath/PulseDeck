<?php

namespace App\Services\Status;

use App\Enums\Status\MaintenanceStatus;
use App\Events\Status\MaintenanceEnded;
use App\Events\Status\MaintenanceStarted;
use App\Models\Status\StatusMaintenance;
use App\Models\Status\StatusService;

/**
 * Maintenance windows: services inside an active window report
 * Maintenance instead of an outage, and are excluded from uptime.
 */
class MaintenanceManager
{
    public function isUnderMaintenance(StatusService $service): bool
    {
        return StatusMaintenance::query()
            ->whereIn('status', [MaintenanceStatus::Scheduled, MaintenanceStatus::Active])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas('services', fn ($query) => $query->where('status_services.id', $service->id))
            ->exists();
    }

    /**
     * Flip scheduled→active and active→completed windows, firing events.
     *
     * @return array{started: int, ended: int}
     */
    public function syncStates(): array
    {
        $started = 0;
        $ended = 0;

        StatusMaintenance::where('status', MaintenanceStatus::Scheduled)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->each(function (StatusMaintenance $maintenance) use (&$started): void {
                $maintenance->forceFill(['status' => MaintenanceStatus::Active])->save();
                $started++;
                MaintenanceStarted::dispatch($maintenance);
            });

        StatusMaintenance::where('status', MaintenanceStatus::Active)
            ->where('ends_at', '<', now())
            ->each(function (StatusMaintenance $maintenance) use (&$ended): void {
                $maintenance->forceFill(['status' => MaintenanceStatus::Completed])->save();
                $ended++;
                MaintenanceEnded::dispatch($maintenance);
            });

        return ['started' => $started, 'ended' => $ended];
    }
}
