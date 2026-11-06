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

// টিক করা ক্যাটাগরির নতুন প্রকাশিত খবর ফেসবুক পেজে পোস্ট (ফটোকার্ড + কমেন্টে লিংক)।
Schedule::command('facebook:publish')->everyMinute()->withoutOverlapping();

Schedule::command('videos:sync')->everyThirtyMinutes()->withoutOverlapping();

Schedule::command('news:import')->everyThirtyMinutes()->withoutOverlapping();

// খুলনা বিভাগের দশটি জেলার নিজস্ব পোর্টাল বেশি; সেগুলো ৩০ মিনিট পর পর পড়ার দরকার নেই,
// কিন্তু ১০ ঘণ্টার পুরোনো-সংবাদ ছাঁকনির ভেতরে থাকতে হবে — তাই দুই ঘণ্টা পর পর।
Schedule::command('news:import --group=khulna')->everyTwoHours()->withoutOverlapping();

// আলোচিত সার্চ (Google Trends BD) আলাদা সিস্টেম: ফিড প্রতি ঘণ্টায় ঘোরে, তাই ঘণ্টায়
// একবার চালানো হয়। নিজের ছাঁকনি ও চাল-সীমা, ওপরের সংগ্রহের নিয়মে হাত দেওয়া হয়নি।
Schedule::command('news:trends')->hourly()->withoutOverlapping();

// তৈরি ভিডিও, অডিও ও ফটোকার্ড এক দিন পর মুছে যায়; এরপর ক্যাশও পরিষ্কার হয়।
Schedule::command('media:prune')->dailyAt('03:10')->withoutOverlapping();

Schedule::command('cache:clear')->dailyAt('03:20');