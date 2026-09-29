<?php

namespace App\Console\Commands\Status;

use App\Jobs\Status\CalculateDailyStats as CalculateDailyStatsJob;
use App\Models\Status\StatusService;
use Illuminate\Console\Command;

class CalculateDailyStats extends Command
{
    protected $signature = 'status:calculate-daily {--date= : Date (Y-m-d) to aggregate, defaults to today.}';

    protected $description = 'Queue daily uptime aggregation for every active service.';

    public function handle(): int
    {
        $count = 0;

        StatusService::active()->chunk(100, function ($services) use (&$count): void {
            foreach ($services as $service) {
                CalculateDailyStatsJob::dispatch($service->id, $this->option('date'));
                $count++;
            }
        });

        $this->info("Queued daily stats for {$count} service(s).");

        return self::SUCCESS;
    }
}
