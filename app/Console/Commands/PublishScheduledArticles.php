<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\News;
use Illuminate\Console\Command;

class PublishScheduledArticles extends Command
{
    protected $signature = 'articles:publish-scheduled';

    protected $description = 'Publish scheduled articles whose publish time has arrived (run every minute by the scheduler).';

    public function handle(): int
    {
        $count = 0;

        News::query()
            ->where('status', News::STATUS_SCHEDULED)
            ->where('published_at', '<=', now())
            ->chunkById(100, function ($articles) use (&$count) {
                foreach ($articles as $news) {
                    // Model save => the front-end cache is flushed by the model event.
                    $news->forceFill(['status' => News::STATUS_PUBLISHED])->save();

                    Activity::record('article.published_by_schedule', $news);
                    $count++;
                }
            });

        $this->info("Published {$count} scheduled article(s).");

        return self::SUCCESS;
    }
}
