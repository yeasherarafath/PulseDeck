<?php

namespace App\Console\Commands\Status;

use App\Jobs\Status\CalculateDailyStats as CalculateDailyStatsJob;
use App\Models\Status\StatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateDailyStats extends Command
{
    protected $signature = 'status:calculate-daily {--date= : Single date (Y-m-d) to aggregate; omit to aggregate a trailing window ending today.} {--days=3 : Trailing days to aggregate when --date is omitted (covers yesterday plus today for self-heal).}';

    protected $description = 'Queue daily uptime aggregation for every active service.';

    public function handle(): int
    {
        $count = 0;

        $dates = $this->targetDates();

        StatusService::active()->chunk(100, function ($services) use (&$count, $dates): void {
            foreach ($services as $service) {
                foreach ($dates as $date) {
                    CalculateDailyStatsJob::dispatch($service->id, $date);
                    $count++;
                }
            }
        });

        $this->info("Queued daily stats for {$count} job(s) (".implode(', ', $dates).').');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function targetDates(): array
    {
        $single = $this->option('date');

        if (is_string($single) && trim($single) !== '') {
            return [Carbon::parse(trim($single))->toDateString()];
        }

        // Nightly runs at 00:10 would only see the first minutes of "today",
        // so always include yesterday (now complete) plus today (partial) and
        // one extra day so a single missed run heals itself next time.
        $days = max(1, min(90, (int) $this->option('days', 3)));

        $dates = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = today()->subDays($i)->toDateString();
        }

        return $dates;
    }
}
