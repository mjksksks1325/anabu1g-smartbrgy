<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('restrictions:expire')->hourly()->withoutOverlapping();
Schedule::command('certificates:purge-expired')->dailyAt('02:00')->withoutOverlapping();
