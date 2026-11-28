<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservations:send-reminders')->hourly();
Schedule::command('purchases:send-renewal-reminders')->daily();
Schedule::command('twitter:sync-mentions')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('twitter:publish-scheduled')->everyMinute()->withoutOverlapping();
