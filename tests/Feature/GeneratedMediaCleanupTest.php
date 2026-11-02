<?php

use App\Models\Category;
use App\Models\News;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'media-prune-'.Str::lower(Str::random(8));
    $this->base = $base;

    $this->app->useStoragePath($base.DIRECTORY_SEPARATOR.'storage');
    $this->app->usePublicPath($base.DIRECTORY_SEPARATOR.'public');
    config(['filesystems.disks.public.root' => $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public']);

    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $tag = Str::lower(Str::random(8));

    $this->media = [
        'card_old' => public_path('uploads/cards/test-card-old-'.$tag.'.jpg'),
        'card_fresh' => public_path('uploads/cards/test-card-fresh-'.$tag.'.jpg'),
        'news_image' => public_path('uploads/news/test-news-'.$tag.'.jpg'),
        'audio_old' => Storage::disk('public')->path('audio-news/test-audio-old-'.$tag.'.mp3'),
        'audio_fresh' => Storage::disk('public')->path('audio-news/test-audio-fresh-'.$tag.'.mp3'),
        'video_old' => storage_path('app/videos/test-video-old-'.$tag.'.mp4'),
        'video_fresh' => storage_path('app/videos/test-video-fresh-'.$tag.'.mp4'),
        'build_old' => storage_path('app/video-build/test-build-old-'.$tag),
    ];

    foreach ($this->media as $key => $path) {
        if ($key === 'build_old') {
            File::ensureDirectoryExists($path);
            File::put($path.'/slide.png', 'x');
        } else {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, 'x');
        }

        touch($path, str_contains($key, '_old') ? now()->subDays(2)->getTimestamp() : now()->getTimestamp());
    }

    $this->news = News::create([
        'category_id' => $this->category->id,
        'title' => 'ক্লিনআপ পরীক্ষার সংবাদ',
        'slug' => 'cleanup-test-'.$tag,
        'description' => '<p>পরীক্ষা</p>',
        'image' => basename($this->media['news_image']),
    ]);

    News::whereKey($this->news->id)->update([
        'card_image' => basename($this->media['card_old']),
        'audio_url' => '/storage/audio-news/'.basename($this->media['audio_old']),
        'audio_duration' => 12,
        'tts_status' => 'completed',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

test('এক দিনের পুরোনো ভিডিও, অডিও ও ফটোকার্ড মুছে যায়', function () {
    $this->artisan('media:prune')->assertSuccessful();

    expect(file_exists($this->media['card_old']))->toBeFalse();
    expect(file_exists($this->media['audio_old']))->toBeFalse();
    expect(file_exists($this->media['video_old']))->toBeFalse();
    expect(is_dir($this->media['build_old']))->toBeFalse();

    expect(file_exists($this->media['card_fresh']))->toBeTrue();
    expect(file_exists($this->media['audio_fresh']))->toBeTrue();
    expect(file_exists($this->media['video_fresh']))->toBeTrue();
    expect(file_exists($this->media['news_image']))->toBeTrue();

    $news = $this->news->fresh();
    expect($news->card_image)->toBeNull();
    expect($news->audio_url)->toBeNull();
    expect($news->audio_duration)->toBeNull();
    expect($news->tts_status)->toBe('pending');
});

test('মিডিয়া ক্লিনআপ ও ক্যাশ ক্লিয়ার প্রতিদিন নির্ধারিত সময়ে চলে', function () {
    $events = collect(app(Schedule::class)->events());
    $prune = $events->first(fn ($event) => str_contains((string) $event->command, 'media:prune'));
    $cache = $events->first(fn ($event) => str_contains((string) $event->command, 'cache:clear'));

    expect($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('10 3 * * *');
    expect($cache)->not->toBeNull()
        ->and($cache->expression)->toBe('20 3 * * *');
});
