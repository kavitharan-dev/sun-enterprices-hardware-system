<?php

use App\Jobs\CheckLowStockJob;
use App\Jobs\RetryFailedSmsJob;
use App\Jobs\RunScheduledBusinessAlertsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckLowStockJob)
    ->dailyAt('08:00')
    ->timezone('Asia/Colombo')
    ->withoutOverlapping();

Schedule::job(new RetryFailedSmsJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::job(new RunScheduledBusinessAlertsJob)
    ->dailyAt('08:30')
    ->timezone('Asia/Colombo')
    ->withoutOverlapping();

// Hourly rather than nightly: a daily backup can lose a whole trading day of
// sales, which the shop cannot reconstruct.
Schedule::command('app:backup-database')
    ->hourly()
    ->timezone('Asia/Colombo')
    ->withoutOverlapping();
