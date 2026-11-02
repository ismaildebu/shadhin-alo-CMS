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

Schedule::command('news:import')->everyThirtyMinutes()->withoutOverlapping();

// তৈরি ভিডিও, অডিও ও ফটোকার্ড এক দিন পর মুছে যায়; এরপর ক্যাশও পরিষ্কার হয়।
Schedule::command('media:prune')->dailyAt('03:10')->withoutOverlapping();

Schedule::command('cache:clear')->dailyAt('03:20');