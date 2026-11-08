<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schoolos:snapshot-usage')->dailyAt('01:15');
Schedule::command('schoolos:verify-domains')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('schoolos:validate-backup --trigger=scheduled')->weeklyOn(1, '02:30');
Schedule::command('schoolos:cache-warm')->hourly();
Schedule::command('schoolos:cost-report --alert')->dailyAt('06:00');
Schedule::command('schoolos:refresh-report-store')->dailyAt('02:00');
Schedule::command('schoolos:model-workload --persist')->weeklyOn(7, '03:00');
Schedule::command('schoolos:billing:process')->hourly()->withoutOverlapping();
Schedule::command('schoolos:billing:reconcile')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('schoolos:records:remind')->dailyAt('07:30')->withoutOverlapping();
Schedule::command('schoolos:lms:announce')->everyTenMinutes()->withoutOverlapping();
Schedule::command('schoolos:announcements:publish')->everyMinute()->withoutOverlapping();
Schedule::command('schoolos:fees:remind')->dailyAt('08:00')->withoutOverlapping();
