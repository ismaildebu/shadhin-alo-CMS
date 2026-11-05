<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;
use App\Support\BanglaTime;
use App\Support\Settings;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo-page']);
    $this->author = User::factory()->editor()->create(['name' => 'রফিকুল ইসলাম']);
});

function spNews(array $extra = []): News
{
    static $n = 0;
    $n++;

    $news = News::create(array_merge([
        'category_id' => test()->category->id,
        'author_id' => test()->author->id,
        'reporter' => 'রফিকুল ইসলাম',
        'title' => "সিঙ্গেল পেজের খবর {$n}",
        'slug' => "sp-news-{$n}",
        'summary' => 'সংক্ষিপ্ত সারাংশ।',
        'description' => '<p>বিস্তারিত লেখা।</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHours(3),
    ], $extra));

    // তৈরির মুহূর্তে updated_at, published_at-এর অনেক পরে বসে; পাঠককে সেটি আপডেট না দেখাতে
    // ফিক্সচারেই দুটো এক করে নিই (না হলে "আপডেট নেই" টেস্ট ভুল খবর তৈরি করে)।
    $news->updated_at = $news->published_at;
    $news->saveQuietly();

    return $news;
}

function spPage(News $news): string
{
    return test()->get(route('frontend.news.show', $news->slug))->assertOk()->getContent();
}

it('puts the site logo where the writer photo is when the writer has none', function () {
    $html = spPage(spNews());

    expect($html)->toContain('sa-author-mark is-logo');
    expect($html)->toContain('/images/logo.png');
    expect($html)->not->toContain('aria-hidden="true">র');
});

it('keeps the writer photo when the writer has one', function () {
    $this->author->forceFill(['avatar' => 'avatar-test.png'])->save();

    $html = spPage(spNews());

    expect($html)->toContain('/uploads/avatars/avatar-test.png');
    expect($html)->not->toContain('sa-author-mark is-logo');
});

it('falls back to the logo in the author box as well', function () {
    $html = spPage(spNews());

    expect($html)->toContain('sa-author-photo is-logo');
});

it('writes the publication date and time in Bengali', function () {
    $news = spNews();

    $times = spTimes(spPage($news));

    expect($times['প্রকাশ'])->toBe(BanglaTime::dateTime($news->display_date));

    // ইংরেজি অঙ্ক বা ল্যাটিন মাস মানে বাংলা হয়নি
    expect($times['প্রকাশ'])->not->toMatch('/[0-9A-Za-z]/');
});

it('shows the update date and time after a later edit', function () {
    $news = spNews();
    $news->updated_at = now()->subHour();
    $news->saveQuietly();

    $times = spTimes(spPage($news->fresh()));

    expect($times['সর্বশেষ আপডেট'])->toBe(BanglaTime::dateTime($news->updated_at));
    expect($times['সর্বশেষ আপডেট'])->not->toMatch('/[0-9A-Za-z]/');
});

it('does not call a same-minute save an update', function () {
    $times = spTimes(spPage(spNews()));

    expect($times)->not->toHaveKey('সর্বশেষ আপডেট');
});

it('offers a card button with the card data when no card was made', function () {
    $news = spNews(['title' => 'কার্ডের খবর', 'slug' => 'sp-card-news']);

    $html = spPage($news);

    expect($html)->toContain('data-sa-card');
    expect($html)->toContain('/js/article-photocard.js');
    expect($html)->toContain('/js/admin-photocard.js');

    $card = spCardPayload($html);

    expect($card['data']['title'])->toBe('কার্ডের খবর');
    expect($card['data']['dateBn'])->toBe(BanglaTime::date($news->display_date));
    expect($card['options'])->toHaveKeys(['template', 'size', 'accent']);
});

it('links to the stored card when an editor already made one', function () {
    $news = spNews();
    News::whereKey($news->id)->update(['card_image' => 'card-test.jpg']);

    $html = spPage($news->fresh());

    expect($html)->toContain('/uploads/cards/card-test.jpg');
    expect($html)->not->toContain('data-sa-card-data');
});

it('drops the card button and the renderer when the toggle is off', function () {
    Settings::set(['article_show_photocard' => '0']);

    $html = spPage(spNews());

    expect($html)->not->toContain('data-sa-card');
    expect($html)->not->toContain('/js/article-photocard.js');
});

/**
 * খবরের নিজের তারিখের ট্যাগ: "প্রকাশ" ও "সর্বশেষ আপডেট" লেবেল => দেখানো লেখা।
 *
 * পুরো পাতায় 'd M Y' খোঁজা চলে না — সাইডবারের সর্বশেষ/সম্পর্কিত কার্ডের <time>
 * এখনও ইংরেজি তারিখ দেখায়, সেটা এই দাবির বাইরে।
 *
 * @return array<string, string>
 */
function spTimes(string $html): array
{
    preg_match_all('#<time\b[^>]*>\s*(প্রকাশ|সর্বশেষ আপডেট):\s*([^<]*)<#u', $html, $matches);

    $times = [];

    foreach ($matches[1] as $i => $label) {
        $times[$label] = trim($matches[2][$i]);
    }

    if ($times === []) {
        throw new RuntimeException('পাতায় প্রকাশের তারিখ পাওয়া যায়নি।');
    }

    return $times;
}

/**
 * পেজে বসানো কার্ডের JSON ডেটা।
 *
 * @return array{data: array<string, mixed>, options: array<string, mixed>}
 */
function spCardPayload(string $html): array
{
    if (! preg_match('#<script type="application/json" data-sa-card-data>\s*(.*?)\s*</script>#s', $html, $m)) {
        throw new RuntimeException('পাতায় কার্ডের JSON ডেটা পাওয়া যায়নি।');
    }

    $decoded = json_decode($m[1], true);

    if (! is_array($decoded) || ! isset($decoded['data'], $decoded['options'])) {
        throw new RuntimeException('কার্ডের JSON ডেটা পড়া যায়নি: '.substr($m[1], 0, 120));
    }

    return $decoded;
}
