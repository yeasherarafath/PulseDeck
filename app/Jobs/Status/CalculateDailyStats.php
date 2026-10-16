<?php

namespace App\Jobs\Status;

use App\Enums\Status\CheckResultStatus;
use App\Models\Status\StatusDailyStat;
use App\Models\Status\StatusService;
use App\Services\Status\PublicStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Aggregates one service + date into status_daily_stats (idempotent).
 */
class CalculateDailyStats implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $serviceId,
        public ?string $date = null,
    ) {
        //
    }

    public function handle(): void
    {
        $service = StatusService::find($this->serviceId);

        if (! $service) {
            return;
        }

        $date = $this->date ? Carbon::parse($this->date)->toDateString() : today()->toDateString();

        $checks = $service->checks()
            ->whereDate('checked_at', $date)
            ->whereNot('status', CheckResultStatus::Maintenance);

        $total = (clone $checks)->count();
        $successful = (clone $checks)->where('success', true)->count();
        $failed = $total - $successful;

        $timings = (clone $checks)->whereNotNull('response_time');

        $attributes = [
            'total_checks' => $total,
            'successful_checks' => $successful,
            'failed_checks' => $failed,
            'uptime_percentage' => $total > 0 ? round($successful / $total * 100, 2) : 100,
            'avg_response_time' => $timings->avg('response_time') !== null ? (int) round($timings->avg('response_time')) : null,
            'min_response_time' => (clone $checks)->whereNotNull('response_time')->min('response_time'),
            'max_response_time' => (clone $checks)->whereNotNull('response_time')->max('response_time'),
        ];

        // whereDate keeps the lookup stable whether the driver stores DATE
        // as "Y-m-d" (MySQL) or "Y-m-d H:i:s" (SQLite), so re-aggregating
        // the same day is a safe update, never a duplicate insert.
        $existing = StatusDailyStat::where('service_id', $service->id)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->fill($attributes)->save();
        } else {
            StatusDailyStat::create(array_merge(
                ['service_id' => $service->id, 'date' => $date],
                $attributes,
            ));
        }

        PublicStatusService::flush($service->id);
    }
}
