<?php

use App\Models\Category;
use App\Models\News;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo', 'serial' => 1]);
});

function makeNews(array $attrs = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => test()->category->id,
        'title' => "সংবাদ শিরোনাম {$n}",
        'slug' => "news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ], $attrs));
}

it('renders the home page with categories and articles', function () {
    makeNews(['title' => 'প্রধান খবর', 'is_lead' => true]);

    $this->get('/')->assertOk()->assertSee('প্রধান খবর')->assertSee('জাতীয়');
});

it('shows a published article and hides drafts', function () {
    $published = makeNews(['title' => 'প্রকাশিত খবর']);
    $draft = makeNews(['status' => 'draft']);

    $this->get(route('frontend.news.show', $published->slug))->assertOk()->assertSee('প্রকাশিত খবর');
    $this->get(route('frontend.news.show', $draft->slug))->assertNotFound();
});

it('does not run category/breaking-news queries for every view', function () {
    makeNews(['breaking' => true]);

    DB::enableQueryLog();
    $this->get('/')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');

    // Previously the '*' composer ran these two queries once per rendered Blade file.
    $breakingQueries = $queries->filter(fn ($q) => str_contains($q, '"breaking"'))->count();

    expect($breakingQueries)->toBeLessThanOrEqual(2);
});

it('shows new articles immediately even though the home page blocks are cached', function () {
    $this->get('/')->assertOk()->assertDontSee('একদম নতুন খবর');

    makeNews(['title' => 'একদম নতুন খবর', 'is_lead' => true]);

    $this->get('/')->assertOk()->assertSee('একদম নতুন খবর');
});

it('stops showing an article right after it is unpublished', function () {
    $news = makeNews(['title' => 'সাময়িক খবর', 'is_lead' => true]);
    $this->get('/')->assertSee('সাময়িক খবর');

    $news->update(['status' => 'draft']);

    $this->get('/')->assertDontSee('সাময়িক খবর');
});
