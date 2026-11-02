<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Support\FrontendCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PruneGeneratedMedia extends Command
{
    protected $signature = 'media:prune';

    protected $description = 'এক দিনের পুরোনো তৈরি ভিডিও, অডিও ও ফটোকার্ড মুছে দেয়';

    public function handle(): int
    {
        $cutoff = now()->subDay()->getTimestamp();
        $deleted = ['video' => 0, 'build' => 0, 'audio' => 0, 'card' => 0];
        $audioNames = [];
        $cardNames = [];

        $videosDir = storage_path('app/videos');

        if (is_dir($videosDir)) {
            foreach (File::files($videosDir) as $file) {
                if ($file->getMTime() < $cutoff) {
                    File::delete($file->getPathname());
                    $deleted['video']++;
                }
            }
        }

        $buildDir = storage_path('app/video-build');

        if (is_dir($buildDir)) {
            foreach (File::directories($buildDir) as $dir) {
                if (File::lastModified($dir) < $cutoff) {
                    File::deleteDirectory($dir);
                    $deleted['build']++;
                }
            }
        }

        $disk = Storage::disk((string) config('tts.audio_disk', 'public'));
        $audioDir = trim((string) config('tts.audio_path', 'audio-news'), '/');

        foreach ($disk->files($audioDir) as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $audioNames[] = basename($path);
                $deleted['audio']++;
            }
        }

        $cardsDir = public_path('uploads/cards');

        if (is_dir($cardsDir)) {
            foreach (File::files($cardsDir) as $file) {
                if ($file->getMTime() < $cutoff) {
                    File::delete($file->getPathname());
                    $cardNames[] = $file->getFilename();
                    $deleted['card']++;
                }
            }
        }

        $cleared = $deleted['audio'] + $deleted['card'];

        if ($audioNames !== []) {
            News::query()
                ->where(function ($query) use ($audioNames) {
                    foreach ($audioNames as $name) {
                        $query->orWhere('audio_url', 'like', '%'.$name.'%');
                    }
                })
                ->update(['audio_url' => null, 'audio_duration' => null, 'tts_status' => 'pending']);
        }

        if ($cardNames !== []) {
            News::query()->whereIn('card_image', $cardNames)->update(['card_image' => null]);
        }

        if ($cleared > 0) {
            FrontendCache::flush();
        }

        $total = array_sum($deleted);

        if ($total === 0) {
            $this->info('মুছে ফেলার মতো পুরোনো ফাইল নেই।');

            return self::SUCCESS;
        }

        $this->table(
            ['ধরন', 'মোছা হয়েছে'],
            [
                ['ভিডিও', $deleted['video']],
                ['ভিডিও বিল্ড ফোল্ডার', $deleted['build']],
                ['অডিও', $deleted['audio']],
                ['ফটোকার্ড', $deleted['card']],
            ]
        );
        $this->info('মোট '.$total.'টি পুরোনো ফাইল মোছা হয়েছে।');

        return self::SUCCESS;
    }
}
