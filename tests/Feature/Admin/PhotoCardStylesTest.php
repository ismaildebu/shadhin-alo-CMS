<?php

use App\Http\Controllers\Admin\NewsCardController;
use App\Models\Category;
use App\Models\News;
use App\Models\User;
use App\Support\BanglaTime;
use App\Support\Settings;

it('keeps the style list identical in PHP and in the canvas renderer', function () {
    $src = (string) file_get_contents(public_path('js/admin-photocard.js'));

    preg_match_all('/^ {8}([a-z]+): function \(ctx, c\)/m', $src, $js);

    $php = array_keys(NewsCardController::TEMPLATES);

    sort($js[1]);
    sort($php);

    // বাদ দেওয়া ডিজাইন (bar, center, side, slant, top, quote, cream, column, brand, frame, breaking) ফিরে এলে এখানেই ধরা পড়বে।
    expect($js[1])->toBe($php)
        ->and($php)->toBe(['overlay', 'paper', 'split', 'text']);

    // সারাংশ paper, split, text — তিনটিতেই আঁকা হয় (overlay-এ শুধু শিরোনাম)।
    preg_match_all('/c\.o\.excerpt && c\.d\.excerpt/', $src, $ex);

    expect($ex[0])->toHaveCount(3);
});

it('falls back to the modern style when the saved default names a retired one', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);

    $news = News::create([
        'category_id' => $category->id, 'author_id' => $admin->id, 'title' => 'ফটোকার্ডের খবর',
        'slug' => 'pc-retired-default', 'description' => '<p>লেখা</p>',
        'status' => 'published', 'published_at' => now()->subMinute(),
    ]);

    Settings::set(['card_template' => 'brand']);

    $html = $this->actingAs($admin)->get("/admin/news/{$news->id}/card")->assertOk()->getContent();

    expect($html)->toMatch('/<option value="paper"[^>]*selected/')
        ->and($html)->not->toContain('value="brand"')
        ->and($html)->not->toContain('value="slant"');
});

it('sends the Bangla date with the card data and the batch queue', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo-bn-date']);

    $news = News::create([
        'category_id' => $category->id, 'author_id' => $admin->id, 'title' => 'বাংলা তারিখের কার্ড',
        'slug' => 'pc-bangla-date', 'description' => '<p>লেখা</p>',
        'status' => 'published', 'published_at' => now()->subMinute(),
    ]);

    $expect = BanglaTime::date($news->display_date);

    $card = $this->actingAs($admin)->get("/admin/news/{$news->id}/card")->assertOk()->getContent();
    $queue = $this->actingAs($admin)->get('/admin/photocards/queue')->assertOk()->json();

    expect($card)->toContain('"dateBn"')
        ->and($queue['items'][0]['dateBn'])->toBe($expect)
        ->and($queue['items'][0])->not->toHaveKey('caption');
});

it('lists only the modern styles in the defaults form of the card centre', function () {
    $admin = User::factory()->admin()->create();

    $html = $this->actingAs($admin)->get('/admin/photocards')->assertOk()->getContent();

    foreach (array_keys(NewsCardController::TEMPLATES) as $key) {
        expect($html)->toContain('value="'.$key.'"');
    }

    expect($html)->not->toContain('value="quote"')
        ->and($html)->not->toContain('admin-photocard-more.js');
});
