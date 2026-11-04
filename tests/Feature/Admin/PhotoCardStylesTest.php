<?php

use App\Http\Controllers\Admin\NewsCardController;
use App\Models\Category;
use App\Models\News;
use App\Models\User;
use App\Support\Settings;

it('keeps the style list identical in PHP and in the canvas renderer', function () {
    preg_match_all('/^ {8}([a-z]+): function \(ctx, c\)/m', (string) file_get_contents(public_path('js/admin-photocard.js')), $base);
    preg_match_all('/^ {4}T\.([a-z]+) = function \(ctx, c\)/m', (string) file_get_contents(public_path('js/admin-photocard-more.js')), $more);

    $js = array_merge($base[1], $more[1]);
    $php = array_keys(NewsCardController::TEMPLATES);

    sort($js);
    sort($php);

    // বাদ পড়া চারটি (bar, center, side, slant) ফিরে এলে তালিকা এখানেই ধরা পড়বে।
    expect($js)->toBe($php)
        ->and($php)->toBe(['brand', 'breaking', 'column', 'cream', 'frame', 'overlay', 'quote', 'split', 'text', 'top']);
});

it('falls back to overlay when the saved default names a retired style', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);

    $news = News::create([
        'category_id' => $category->id, 'author_id' => $admin->id, 'title' => 'ফটোকার্ডের খবর',
        'slug' => 'pc-retired-default', 'description' => '<p>লেখা</p>',
        'status' => 'published', 'published_at' => now()->subMinute(),
    ]);

    Settings::set(['card_template' => 'bar']);

    $html = $this->actingAs($admin)->get("/admin/news/{$news->id}/card")->assertOk()->getContent();

    expect($html)->toMatch('/<option value="overlay"[^>]*selected/')
        ->and($html)->not->toContain('value="bar"')
        ->and($html)->not->toContain('value="slant"');
});
