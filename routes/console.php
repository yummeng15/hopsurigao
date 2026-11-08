<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('weather:update')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Demo rolling schedules
|--------------------------------------------------------------------------
|
| When DEMO_TOUR_OPERATOR_ID is set (Railway Operator #1 for the capstone
| demos), extend the next 12 months of daily tour dates weekly.
| The seeder is idempotent: it adds missing dates only and does not
| duplicate package_id + tour_date rows or rewrite booked dates.
|
*/
$demoOperatorId = (int) env('DEMO_TOUR_OPERATOR_ID', 0);

if ($demoOperatorId > 0) {
    Schedule::command("demo:seed-tour-packages --operator={$demoOperatorId}")
        ->weeklyOn(1, '02:15')
        ->withoutOverlapping()
        ->runInBackground();
}

/*
|--------------------------------------------------------------------------
| Rolling daily schedules for all eligible published packages
|--------------------------------------------------------------------------
|
| Idempotent: adds missing package_id + tour_date rows with cloned
| departure times and pickups. Does not rewrite booked dates.
|
*/
Schedule::command('tours:ensure-rolling-schedules --days=365')
    ->weeklyOn(1, '02:45')
    ->withoutOverlapping()
    ->runInBackground();
