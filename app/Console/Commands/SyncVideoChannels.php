<?php

namespace App\Console\Commands;

use App\Models\VideoChannel;
use App\Services\YoutubeChannelSync;
use Illuminate\Console\Command;

class SyncVideoChannels extends Command
{
    protected $signature = 'videos:sync';

    protected $description = 'যোগ করা ইউটিউব চ্যানেলের নতুন ভিডিও গ্যালারিতে আনে';

    public function handle(YoutubeChannelSync $youtube): int
    {
        $total = 0;

        foreach (VideoChannel::all() as $channel) {
            $added = $youtube->sync($channel);
            $total += $added;

            $this->line($channel->name.': '.$added.'টি নতুন ভিডিও'.($channel->last_error ? ' (সমস্যা: '.$channel->last_error.')' : ''));
        }

        $this->info('মোট '.$total.'টি নতুন ভিডিও যোগ হয়েছে।');

        return self::SUCCESS;
    }
}