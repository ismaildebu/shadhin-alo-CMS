<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs a cron entry in production:  * * * * * php /path/to/artisan schedule:run
// During local development run:      php artisan schedule:work
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping();

Schedule::command('videos:sync')->everyThirtyMinutes()->withoutOverlapping();