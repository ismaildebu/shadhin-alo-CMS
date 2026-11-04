<?php

use App\Models\Category;
use App\Models\News;
use App\Support\Settings;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo', 'serial' => 1]);
});

function fsNews(Category $category, string $title, string $status = 'published', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => $category->id,
        'title' => $title,
        'slug' => "fs-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(30 - $n) : null,
    ], $extra));
}

// ------------------------------------------------------------- the search bar

it('puts a search bar pointing at the search page in the menu', function () {
    $this->get('/')->assertOk()
        ->assertSee('<form class="sa-search sa-search-bar"', false)
        ->assertSee('action="'.route('frontend.search').'"', false)
        ->assertSee('name="q"', false)
        // সরু ডেস্কটপে আইকনে ভাঁজ হয়, তাই ট্যাপ করার মতো লেবেলটি লাগে
        ->assertSee('class="sa-search-open" for="saSiteSearch"', false);
});

it('keeps the box open when the results page already holds a word', function () {
    $this->get('/search?q=বন্যা')->assertOk()
        ->assertSee('<form class="sa-search sa-search-bar sa-search-filled"', false);
});

it('colours the search bar from the menu design settings', function () {
    $default = $this->get('/')->assertOk()->getContent();
    expect($default)->toContain('.navbar-custom .sa-search-bar .sa-search-input{padding:8px 13px')
        ->and($default)->toContain('background:rgba(255,255,255,.13)');

    Settings::set(['menu_bg' => '#ffffff', 'menu_text' => '#111827']);

    $light = $this->get('/')->assertOk()->getContent();
    expect($light)->toContain('background:rgba(0,0,0,.06)')
        ->and($light)->toContain('color:#111827;background:rgba(0,0,0,.06)');
});

// -------------------------------------------------------------- the results

it('finds published articles by title', function () {
    fsNews($this->category, 'সম্ভাবনা পাল্টেছে');
    fsNews($this->category, 'অন্য একটি সংবাদ');

    $html = newsList($this->get('/search?q=সম্ভাবনা')->assertOk()->getContent());

    expect($html)->toContain('সম্ভাবনা পাল্টেছে')->not->toContain('অন্য একটি সংবাদ');
});

it('finds articles by their summary too', function () {
    fsNews($this->category, 'শিরোনামে নেই', 'published', ['summary' => 'গ্রামে সংঘটিত হয়েছে দুর্ঘটনা']);

    expect(newsList($this->get('/search?q=দুর্ঘটনা')->assertOk()->getContent()))
        ->toContain('শিরোনামে নেই');
});

it('leaves out drafts, future and trashed articles', function () {
    fsNews($this->category, 'নির্বাচন প্রকাশিত');
    fsNews($this->category, 'নির্বাচন খসড়া', 'draft');
    fsNews($this->category, 'নির্বাচন ভবিষ্যৎ', 'scheduled', ['published_at' => now()->addDay()]);
    fsNews($this->category, 'নির্বাচন ট্র্যাশ')->delete();

    $html = newsList($this->get('/search?q=নির্বাচন')->assertOk()->getContent());

    expect($html)->toContain('নির্বাচন প্রকাশিত')
        ->not->toContain('নির্বাচন খসড়া')
        ->not->toContain('নির্বাচন ভবিষ্যৎ')
        ->not->toContain('নির্বাচন ট্র্যাশ');
});

it('shows a hint when nothing matches and asks for a word when the box is empty', function () {
    $this->get('/search?q=আইফোন')->assertOk()->assertSee('কোনো সংবাদ পাওয়া যায়নি');

    $this->get('/search')->assertOk()->assertSee('উপরের বক্সে কিছু লিখে দিন');
});

it('keeps the typed word in the box on the results page and escapes it', function () {
    $this->get('/search?q=<script>alert(1)</script>')->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('treats the percent and underscore in a word as plain characters', function () {
    fsNews($this->category, '৫০% মূল্যস্ফীতি');
    fsNews($this->category, 'স্বাভাবিক সংবাদ');

    // %25 is the encoded percent the browser would send
    expect(newsList($this->get('/search?q=৫০%25')->assertOk()->getContent()))
        ->toContain('৫০% মূল্যস্ফীতি')
        ->not->toContain('স্বাভাবিক সংবাদ');
});

it('paginates the results and keeps the word in every page link', function () {
    foreach (range(1, 13) as $i) {
        fsNews($this->category, "বন্যা খবর {$i}", 'published', ['published_at' => now()->subHours(20 - $i)]);
    }

    $first = newsList($this->get('/search?q=বন্যা')->assertOk()->getContent());
    expect($first)->toContain('বন্যা খবর 13')->toContain('q=%E0%A6%AC%E0%A6%A8%E0%A7%8D%E0%A6%AF%E0%A6%BE')->toContain('page=2');

    $second = newsList($this->get('/search?q=বন্যা&page=2')->assertOk()->getContent());
    expect($second)->toContain('বন্যা খবর 1')->not->toContain('বন্যা খবর 13');
});
