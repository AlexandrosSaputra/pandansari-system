<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('forecast:sync-sales')
    ->dailyAt('00:15')
    ->timezone(config('forecast.timezone'))
    ->withoutOverlapping(30);
