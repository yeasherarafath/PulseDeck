<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('status:dispatch-due')->everyMinute();
Schedule::command('status:calculate-daily')->dailyAt('00:10');
Schedule::command('status:cleanup')->dailyAt('01:00');
