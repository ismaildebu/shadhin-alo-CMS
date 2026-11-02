<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Support\NewsVideo;
use Illuminate\Console\Command;
use Throwable;

class NewsVideoCommand extends Command
{
    protected $signature = 'news:video
        {news : নিউজের আইডি বা স্লাগ}
        {--out= : আউটপুট MP4 ফাইলের পাথ}
        {--music= : নিজের ব্যাকগ্রাউন্ড মিউজিক ফাইল (MP3/AAC)}
        {--keep : HTML, PNG ও ক্লিপ ফাইল রেখে দিন}';

    protected $description = 'একটি নিউজ থেকে ফেসবুক রিলস (৯:১৬) ভিডিও তৈরি করে';

    public function handle(): int
    {
        $key = trim((string) $this->argument('news'));

        $news = ctype_digit($key)
            ? News::query()->find((int) $key)
            : News::query()->where('slug', $key)->first();

        if ($news === null) {
            $this->error('নিউজ পাওয়া যায়নি: '.$key);

            return self::FAILURE;
        }

        try {
            $result = NewsVideo::make(
                $news,
                $this->option('out') ?: null,
                (bool) $this->option('keep'),
                fn (string $message) => $this->line('  '.$message),
                $this->option('music') ?: null,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('ভিডিও তৈরি হয়েছে: '.$result['path']);
        $this->line('দৈর্ঘ্য: '.$result['duration'].' সেকেন্ড | স্লাইড: '.$result['slides'].' | সাইজ: '.self::size($result['path']));

        if ($result['build_dir']) {
            $this->line('স্লাইড ফাইল: '.$result['build_dir']);
        }

        return self::SUCCESS;
    }

    private static function size(string $path): string
    {
        $bytes = (int) @filesize($path);

        return $bytes > 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024).' KB';
    }
}
