<?php

use App\Models\Category;
use App\Models\EpaperPage;
use App\Models\Media;
use App\Models\News;
use App\Support\EpaperLayout;

beforeEach(function () {
    $this->today = now()->timezone('Asia/Dhaka')->toDateString();
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo', 'serial' => 1]);
    $this->withImage = feNews($this->category, 'ছবিওয়ালা সংবাদ', 'news-photo.jpg');
    $this->plain = feNews($this->category, 'ছবিবিহীন সংবাদ');
});

function feNews(Category $category, string $title, ?string $image = null, string $status = 'published'): News
{
    static $n = 0;
    $n++;

    return News::create([
        'category_id' => $category->id,
        'title' => $title,
        'slug' => "fe-news-{$n}",
        'summary' => 'সংক্ষিপ্ত বর্ণনা',
        'description' => '<p>বিস্তারিত বর্ণনা</p>',
        'image' => $image,
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(120 - $n) : null,
    ]);
}

function fePage(string $date, array $columns, string $status = EpaperPage::STATUS_PUBLISHED): EpaperPage
{
    return EpaperPage::create([
        'edition_date' => $date,
        'status' => $status,
        'layout' => EpaperLayout::sanitize(['columns' => $columns]),
    ]);
}

function feBody(string $html): string
{
    $from = strpos($html, 'sa-epaper-print');

    return $from === false ? $html : substr($html, $from, strpos($html, 'sa-sidebar', $from) - $from);
}

// ------------------------------------------------------------- which page shows

it('shows today\'s published edition on the e-paper page', function () {
    fePage($this->today, [['width' => 50, 'blocks' => [['news_id' => $this->withImage->id]]]]);

    $this->get('/e-paper')->assertOk()->assertSee('ছবিওয়ালা সংবাদ', false);
});

it('falls back to the newest published edition when today has none', function () {
    fePage(date('Y-m-d', strtotime($this->today.' -2 days')), [
        ['width' => 50, 'blocks' => [['news_id' => $this->plain->id]]],
    ]);

    $this->get('/e-paper')->assertOk()->assertSee('ছবিবিহীন সংবাদ', false);
});

it('says so when nothing has been published yet', function () {
    $this->get('/e-paper')->assertStatus(404);
});

it('opens an edition by its date', function () {
    $date = date('Y-m-d', strtotime($this->today.' -6 days'));
    fePage($date, [['width' => 50, 'blocks' => [['news_id' => $this->plain->id]]]]);

    $this->get("/e-paper/{$date}")->assertOk()->assertSee('ছবিবিহীন সংবাদ', false);
});

it('will not show a draft edition to the public', function () {
    $page = fePage($this->today, [['width' => 50, 'blocks' => [['news_id' => $this->plain->id]]]], EpaperPage::STATUS_DRAFT);

    $this->get('/e-paper')->assertStatus(404);
    $this->get(route('frontend.epaper.show', $this->today))->assertStatus(404);

    $archive = newsList($this->get(route('frontend.epaper.archive'))->assertOk()->getContent());

    expect($archive)->not->toContain(route('frontend.epaper.show', $this->today, false))
        ->and($page->status)->toBe(EpaperPage::STATUS_DRAFT);
});

it('will not answer a date that has no edition', function () {
    $this->get('/e-paper/2019-01-01')->assertStatus(404);
});

it('ignores a date that is not a date', function () {
    $this->get('/e-paper/xyz')->assertStatus(404);
});

// ------------------------------------------------------------- how the page looks

it('gives every column the width the builder saved', function () {
    fePage($this->today, [
        ['width' => 62, 'blocks' => [['news_id' => $this->withImage->id]]],
        ['width' => 23, 'blocks' => [['news_id' => $this->plain->id]]],
    ]);

    $html = feBody($this->get('/e-paper')->assertOk()->getContent());

    expect($html)->toContain('flex-grow:62')
        ->and($html)->toContain('flex-grow:23')
        ->and(substr_count($html, 'sa-epaper-col'))->toBe(2);
});

it('leaves out the picture when a block is set to no image', function () {
    fePage($this->today, [
        ['width' => 50, 'blocks' => [
            ['news_id' => $this->withImage->id, 'image' => EpaperLayout::IMAGE_NONE],
            ['news_id' => $this->plain->id],
        ]],
    ]);

    $html = feBody($this->get('/e-paper')->assertOk()->getContent());

    expect($html)->not->toContain(Media::thumbUrl($this->withImage->image, 800))
        ->and($html)->toContain('ছবিওয়ালা সংবাদ')
        ->and(substr_count($html, '<img class="sa-epaper-img"'))->toBe(0);
});

it('draws the dragged height on the picture', function () {
    fePage($this->today, [
        ['width' => 50, 'blocks' => [
            ['news_id' => $this->withImage->id, 'image' => EpaperLayout::IMAGE_CUSTOM, 'image_height' => 260],
        ]],
    ]);

    $html = feBody($this->get('/e-paper')->assertOk()->getContent());

    expect($html)->toContain(Media::thumbUrl($this->withImage->image, 800))
        ->and($html)->toContain('style="height:260px"');
});

it('leaves a normal picture to its own shape', function () {
    fePage($this->today, [['width' => 50, 'blocks' => [['news_id' => $this->withImage->id]]]]);

    $html = feBody($this->get('/e-paper')->assertOk()->getContent());

    expect($html)->toContain(Media::thumbUrl($this->withImage->image, 800))
        ->and($html)->not->toContain('style="height:');
});

it('links each block to its own news page', function () {
    fePage($this->today, [['width' => 50, 'blocks' => [['news_id' => $this->plain->id]]]]);

    $this->get('/e-paper')->assertOk()->assertSee(route('frontend.news.show', $this->plain->slug), false);
});

it('drops a block whose news went unpublished, without breaking the page', function () {
    $page = fePage($this->today, [
        ['width' => 50, 'blocks' => [
            ['news_id' => $this->withImage->id],
            ['news_id' => $this->plain->id],
        ]],
    ]);

    $this->plain->update(['status' => 'draft']);

    $html = feBody($this->get('/e-paper')->assertOk()->getContent());

    expect($html)->not->toContain('ছবিবিহীন সংবাদ')
        ->and($html)->toContain('ছবিওয়ালা সংবাদ');

    // পাতার লেআউট অটুট থাকে — খবরটি প্রকাশিত হলে ব্লকটি ফিরে আসবে
    expect(EpaperLayout::newsIds($page->refresh()->plan()))->toContain($this->plain->id);

    $this->plain->update(['status' => 'published']);

    expect(feBody($this->get('/e-paper')->assertOk()->getContent()))->toContain('ছবিবিহীন সংবাদ');
});

it('shows an empty page message when the edition has no blocks', function () {
    fePage($this->today, [['width' => 40, 'blocks' => []]]);

    $this->get('/e-paper')->assertOk()->assertSee('এই পাতায় এখনো কোনো সংবাদ বসানো হয়নি।', false);
});

// ------------------------------------------------------------- the archive

it('lists published editions newest first with how many news each holds', function () {
    $yesterday = date('Y-m-d', strtotime($this->today.' -1 day'));

    fePage($yesterday, [
        ['width' => 50, 'blocks' => [['news_id' => $this->withImage->id], ['news_id' => $this->plain->id]]],
    ]);
    fePage($this->today, [['width' => 50, 'blocks' => [['news_id' => $this->plain->id]]]]);

    $html = $this->get('/e-paper/archive')->assertOk()->getContent();

    $todayLink = route('frontend.epaper.show', $this->today);
    $olderLink = route('frontend.epaper.show', $yesterday);

    expect($html)->toContain('2টি সংবাদ')
        ->and($html)->toContain('1টি সংবাদ')
        ->and(strpos($html, $todayLink))->toBeLessThan(strpos($html, $olderLink));
});

it('links each archive row to its edition', function () {
    fePage($this->today, [['width' => 50, 'blocks' => [['news_id' => $this->plain->id]]]]);

    $this->get('/e-paper/archive')->assertOk()->assertSee(route('frontend.epaper.show', $this->today), false);
});
