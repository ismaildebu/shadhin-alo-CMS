<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Support\HtmlSanitizer;
use Illuminate\Console\Command;

class SanitizeNews extends Command
{
    protected $signature = 'cms:sanitize-news {--dry-run : Only report how many articles would change}';

    protected $description = 'One-time clean-up: run the HTML sanitizer over articles saved before sanitizing was added.';

    public function handle(): int
    {
        $changed = 0;

        News::query()->chunkById(100, function ($articles) use (&$changed) {
            foreach ($articles as $news) {
                $clean = HtmlSanitizer::clean($news->description);

                if ($clean === $news->description) {
                    continue;
                }

                $changed++;

                if (! $this->option('dry-run')) {
                    // Query-builder update: no model events, no cache flush per row.
                    News::whereKey($news->id)->update(['description' => $clean]);
                }
            }
        });

        \App\Support\FrontendCache::flush();

        $this->info(($this->option('dry-run') ? 'Would change ' : 'Changed ').$changed.' article(s).');

        return self::SUCCESS;
    }
}
