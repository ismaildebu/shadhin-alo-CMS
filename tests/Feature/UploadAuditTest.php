<?php

use App\Models\Category;
use App\Models\News;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'upload-audit-'.Str::lower(Str::random(8));
    $this->base = $base;

    $this->app->usePublicPath($base.DIRECTORY_SEPARATOR.'public');

    $tag = Str::lower(Str::random(8));
    $this->used = public_path('uploads/news/used-'.$tag.'.jpg');
    $this->orphan = public_path('uploads/news/orphan-'.$tag.'.jpg');

    foreach ([$this->used, $this->orphan] as $path) {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'x');
    }

    $category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo-'.$tag]);

    News::create([
        'category_id' => $category->id,
        'title' => 'অডিট পরীক্ষার সংবাদ',
        'slug' => 'upload-audit-'.$tag,
        'description' => '<p>পরীক্ষা</p>',
        'image' => basename($this->used),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

test('রেফারেন্সহীন ফাইল তালিকায় ওঠে, ব্যবহৃতটি ওঠে না', function () {
    Artisan::call('news:audit-uploads', ['--list' => true]);
    $output = Artisan::output();

    expect($output)->toContain(basename($this->orphan))
        ->and($output)->not->toContain(basename($this->used));
});

test('কমান্ড কিছুই মুছে ফেলে না', function () {
    Artisan::call('news:audit-uploads', ['--list' => true]);

    expect(File::exists($this->orphan))->toBeTrue()
        ->and(File::exists($this->used))->toBeTrue();
});
