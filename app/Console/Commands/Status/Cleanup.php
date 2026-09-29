<?php

namespace App\Console\Commands\Status;

use App\Jobs\Status\CleanupOldChecks;
use Illuminate\Console\Command;

class Cleanup extends Command
{
    protected $signature = 'status:cleanup';

    protected $description = 'Delete expired raw checks, daily stats, and audit logs per retention settings.';

    public function handle(): int
    {
        CleanupOldChecks::dispatchSync();

        $this->info('Retention cleanup completed.');

        return self::SUCCESS;
    }
}
