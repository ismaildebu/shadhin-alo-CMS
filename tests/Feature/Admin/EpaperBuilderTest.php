<?php

use App\Models\Category;
use App\Models\EpaperPage;
use App\Models\News;
use App\Models\User;
use App\Support\EpaperLayout;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->reporter = User::factory()->reporter()->create();
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo', 'serial' => 1]);
    $this->first = ebNews($this->category, 'প্রথম সংবাদ');
    $this->second = ebNews($this->category, 'দ্বিতীয় সংবাদ');
});

function ebNews(Category $category, string $title, string $status = 'published'): News
{
    static $n = 0;
    $n++;

    return News::create([
        'category_id' => $category->id,
        'title' => $title,
        'slug' => "ep-news-{$n}",
        'summary' => 'সংক্ষিপ্ত বর্ণনা',
        'description' => '<p>বিস্তারিত বর্ণনা</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(90 - $n) : null,
    ]);
}

function ebPage(string $date = '2026-12-26'): EpaperPage
{
    return EpaperPage::create([
        'edition_date' => $date,
        'layout' => EpaperLayout::blank(),
    ]);
}

function ebJson(array $columns): string
{
    return json_encode(['columns' => $columns], JSON_THROW_ON_ERROR);
}

// ------------------------------------------------------------- access

it('hides the e-paper module from users without the permission', function () {
    $this->actingAs($this->reporter)->get('/admin/epaper')->assertForbidden();
    $this->actingAs($this->reporter)->get('/admin/epaper/news')->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get('/admin/epaper')->assertRedirect('/login');
});

// ------------------------------------------------------------- opening an edition

it('opens a blank three column page for a new date', function () {
    $this->actingAs($this->admin)
        ->post('/admin/epaper', ['edition_date' => '2026-12-25'])
        ->assertSessionHasNoErrors();

    $page = EpaperPage::whereDate('edition_date', '2026-12-25')->first();

    expect($page)->not->toBeNull()
        ->and($page->status)->toBe(EpaperPage::STATUS_DRAFT)
        ->and($page->created_by)->toBe($this->admin->id)
        ->and($page->plan()['columns'])->toHaveCount(3)
        ->and($page->blockCount())->toBe(0);
});

it('opens the edition that already exists instead of a second one', function () {
    $page = ebPage('2026-12-25');

    $this->actingAs($this->admin)
        ->post('/admin/epaper', ['edition_date' => '2026-12-25'])
        ->assertRedirect(route('epaper.edit', $page));

    expect(EpaperPage::whereDate('edition_date', '2026-12-25')->count())->toBe(1);
});

it('needs a date in the form the form promises', function () {
    $this->actingAs($this->admin)
        ->post('/admin/epaper', ['edition_date' => '25/12/2026'])
        ->assertSessionHasErrors('edition_date');

    expect(EpaperPage::count())->toBe(0);
});

// ------------------------------------------------------------- saving a layout

it('stores column widths, block order and image modes', function () {
    $page = ebPage();

    $this->actingAs($this->admin)
        ->put("/admin/epaper/{$page->id}", [
            'status' => EpaperPage::STATUS_PUBLISHED,
            'layout' => ebJson([
                ['width' => 45, 'blocks' => [
                    ['news_id' => $this->second->id],
                    ['news_id' => $this->first->id, 'image' => 'none'],
                ]],
                ['width' => 25, 'blocks' => [
                    ['news_id' => $this->first->id, 'image' => 'custom', 'image_height' => 240],
                ]],
            ]),
        ])
        ->assertSessionHasNoErrors();

    $columns = $page->refresh()->plan()['columns'];

    expect($page->status)->toBe(EpaperPage::STATUS_PUBLISHED)
        ->and($columns[0]['width'])->toBe(45.0)
        ->and($columns[0]['blocks'][0]['news_id'])->toBe($this->second->id)
        ->and($columns[0]['blocks'][1]['image'])->toBe('none')
        ->and($columns[1]['width'])->toBe(25.0)
        ->and($columns[1]['blocks'][0]['image_height'])->toBe(240)
        ->and($page->blockCount())->toBe(3);
});

it('keeps the ratio of dragged widths when it saves again', function () {
    $page = ebPage();

    $layout = ebJson([
        ['width' => 60, 'blocks' => [['news_id' => $this->first->id]]],
        ['width' => 18, 'blocks' => []],
    ]);

    $this->actingAs($this->admin)->put("/admin/epaper/{$page->id}", ['status' => 'draft', 'layout' => $layout]);

    $again = $page->refresh()->plan();

    // ডিফল্ট ৩ কলাম থেকে টেনে ৬০/১৮ করা হলো; আবার সেভ করলেও ওজন অটুট থাকে
    expect($again['columns'][0]['width'])->toBe(60.0)
        ->and($again['columns'][1]['width'])->toBe(18.0);

    $this->actingAs($this->admin)->put("/admin/epaper/{$page->id}", ['status' => 'draft', 'layout' => json_encode($again)]);

    expect($page->refresh()->plan())->toBe($again);
});

it('clamps wild widths and image heights instead of storing them', function () {
    $page = ebPage();

    $this->actingAs($this->admin)
        ->put("/admin/epaper/{$page->id}", [
            'status' => 'draft',
            'layout' => ebJson([
                ['width' => 400, 'blocks' => [['news_id' => $this->first->id, 'image' => 'custom', 'image_height' => 4000]]],
                ['width' => 1, 'blocks' => []],
            ]),
        ])
        ->assertSessionHasNoErrors();

    $columns = $page->refresh()->plan()['columns'];

    expect($columns[0]['width'])->toBe(EpaperLayout::MAX_WIDTH)
        ->and($columns[0]['blocks'][0]['image_height'])->toBe(EpaperLayout::MAX_IMAGE_HEIGHT)
        ->and($columns[1]['width'])->toBe(EpaperLayout::MIN_WIDTH);
});

it('refuses a page with more columns than it can hold', function () {
    $page = ebPage();
    $stored = $page->plan();

    $this->actingAs($this->admin)
        ->put("/admin/epaper/{$page->id}", [
            'status' => 'draft',
            'layout' => ebJson(array_fill(0, 7, ['width' => 14, 'blocks' => []])),
        ])
        ->assertSessionHasErrors('layout');

    expect($page->refresh()->plan())->toBe($stored);
});

it('refuses layout text that is not readable', function () {
    $page = ebPage();

    foreach (['{oops', '[]', '{"columns":[]}'] as $broken) {
        $this->actingAs($this->admin)
            ->put("/admin/epaper/{$page->id}", ['status' => 'draft', 'layout' => $broken])
            ->assertSessionHasErrors('layout');
    }

    expect($page->refresh()->blockCount())->toBe(0);
});

it('refuses a block whose news is not published', function () {
    $page = ebPage();
    $draft = ebNews($this->category, 'খসড়া সংবাদ', 'draft');

    $this->actingAs($this->admin)
        ->put("/admin/epaper/{$page->id}", [
            'status' => 'published',
            'layout' => ebJson([['width' => 50, 'blocks' => [['news_id' => $draft->id]]]]),
        ])
        ->assertSessionHasErrors('layout');

    expect($page->refresh()->status)->toBe(EpaperPage::STATUS_DRAFT)
        ->and($page->blockCount())->toBe(0);
});

it('refuses an unknown status', function () {
    $page = ebPage();

    $this->actingAs($this->admin)
        ->put("/admin/epaper/{$page->id}", [
            'status' => 'archived',
            'layout' => ebJson([['width' => 40, 'blocks' => []]]),
        ])
        ->assertSessionHasErrors('status');
});

// ------------------------------------------------------------- the builder page

it('shows the saved columns and blocks in the builder', function () {
    $page = ebPage();
    $page->update(['layout' => EpaperLayout::sanitize([
        'columns' => [
            ['width' => 55, 'blocks' => [['news_id' => $this->first->id, 'image' => 'custom', 'image_height' => 300]]],
            ['width' => 30, 'blocks' => []],
        ],
    ])]);

    $html = $this->actingAs($this->admin)->get(route('epaper.edit', $page))->assertOk()->getContent();

    expect($html)->toContain('data-news="'.$this->first->id.'"')
        ->and($html)->toContain('data-image="custom"')
        ->and($html)->toContain('data-height="300"')
        ->and($html)->toContain('flex-grow:55')
        ->and($html)->toContain('ep-divider')
        ->and($html)->toContain('id="ep-stage"')
        ->and($html)->toContain('id="ep-block-tpl"');
});

it('keeps a deleted news item visible in the builder instead of breaking the page', function () {
    $page = ebPage();
    $gone = ebNews($this->category, 'মুছে যাওয়া সংবাদ');
    $page->update(['layout' => EpaperLayout::sanitize([
        'columns' => [['width' => 40, 'blocks' => [['news_id' => $gone->id]]]],
    ])]);
    $gone->delete();

    $this->actingAs($this->admin)->get(route('epaper.edit', $page))->assertOk();
});

// ------------------------------------------------------------- the news picker

it('gives the picker only published news, newest first', function () {
    $older = ebNews($this->category, 'আরও পুরোনো সংবাদ');
    $older->update(['published_at' => now()->subDays(3)]);
    $draft = ebNews($this->category, 'খসড়া সংবাদ', 'draft');

    $json = $this->actingAs($this->admin)->get(route('epaper.news'))->assertOk()->json();
    $ids = collect($json['items'])->pluck('id');

    expect($ids)->toContain($this->first->id)
        ->and($ids)->toContain($older->id)
        ->and($ids)->not->toContain($draft->id)
        ->and($json['items'][0]['title'])->toBe('দ্বিতীয় সংবাদ');
});

it('searches the picker by title', function () {
    $json = $this->actingAs($this->admin)
        ->get(route('epaper.news', ['q' => 'দ্বিতীয়']))
        ->assertOk()
        ->json();

    expect($json['items'])->toHaveCount(1)
        ->and($json['items'][0]['title'])->toBe('দ্বিতীয় সংবাদ');
});

it('deletes an edition', function () {
    $page = ebPage();

    $this->actingAs($this->admin)
        ->delete("/admin/epaper/{$page->id}")
        ->assertRedirect(route('epaper.index'));

    expect(EpaperPage::count())->toBe(0);
});
