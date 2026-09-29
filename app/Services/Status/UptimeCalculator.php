<?php

namespace App\Services\Status;

use App\Enums\Status\CheckResultStatus;
use App\Models\Status\StatusService;

/**
 * Uptime from raw checks. Maintenance checks are excluded from the
 * denominator so scheduled work never counts as downtime.
 */
class UptimeCalculator
{
    /**
     * @return array{uptime: float, total: int, successful: int}
     */
    public function forService(StatusService $service, int $days = 90): array
    {
        $since = now()->subDays($days);

        $query = $service->checks()
            ->where('checked_at', '>=', $since)
            ->whereNot('status', CheckResultStatus::Maintenance);

        $total = (clone $query)->count();

        if ($total === 0) {
            return ['uptime' => 100.0, 'total' => 0, 'successful' => 0];
        }

        $successful = (clone $query)->where('success', true)->count();

        return [
            'uptime' => round($successful / $total * 100, 2),
            'total' => $total,
            'successful' => $successful,
        ];
    }

    public function averageResponseTime(StatusService $service, int $days = 1): ?int
    {
        $avg = $service->checks()
            ->where('checked_at', '>=', now()->subDays($days))
            ->whereNotNull('response_time')
            ->avg('response_time');

        return $avg === null ? null : (int) round($avg);
    }
}
