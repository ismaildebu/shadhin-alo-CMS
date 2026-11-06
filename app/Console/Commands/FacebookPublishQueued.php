<?php

namespace App\Console\Commands;

use App\Support\FacebookShare;
use Illuminate\Console\Command;

class FacebookPublishQueued extends Command
{
    protected $signature = 'facebook:publish {--limit=10 : একবারে সর্বোচ্চ কতটি খবর পাঠানো হবে}';

    protected $description = 'টিক করা ক্যাটাগরির অপেক্ষমাণ খবর ফেসবুক পেজে পোস্ট করে (ফটোকার্ড + কমেন্টে লিংক)।';

    public function handle(): int
    {
        if (! FacebookShare::ready()) {
            $this->line('ফেসবুক পোস্ট চালু নেই (সেটিংসের সুইচ বা .env-এর টোকেন) — কিছু পাঠানো হয়নি।');

            return self::SUCCESS;
        }

        $counts = FacebookShare::sendQueued((int) $this->option('limit'));

        $this->info(sprintf(
            'পোস্ট হয়েছে %dটি, ব্যর্থ %dটি, কার্ড বা লাইনের অপেক্ষায় %dটি।',
            $counts['posted'],
            $counts['failed'],
            $counts['waiting'],
        ));

        return self::SUCCESS;
    }
}
