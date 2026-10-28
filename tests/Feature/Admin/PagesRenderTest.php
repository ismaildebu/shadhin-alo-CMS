<?php

use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\News;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->editor()->create();
    $this->reporter = User::factory()->reporter()->create();
});

function pgArticle(User $author, string $status = 'draft', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => test()->category->id,
        'author_id' => $author->id,
        'title' => "রেন্ডার সংবাদ {$n}",
        'slug' => "pg-news-{$n}",
        'description' => '<p>বিস্তারিত লেখা</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinute() : null,
    ], $extra));
}

it('renders the edit form with the current values and only the statuses the user may pick', function () {
    $news = pgArticle($this->reporter, 'draft', ['title' => 'সম্পাদনার শিরোনাম', 'shoulder' => 'শোল্ডার লেখা']);

    $reporterView = $this->actingAs($this->reporter)->get("/admin/news/{$news->id}/edit")
        ->assertOk()
        ->assertSee('সম্পাদনার শিরোনাম')
        ->assertSee('শোল্ডার লেখা')
        ->assertSee('value="pending"', false)
        ->assertDontSee('value="published"', false)
        ->assertDontSee('name="breaking"', false);

    $this->actingAs($this->editor)->get("/admin/news/{$news->id}/edit")
        ->assertOk()
        ->assertSee('value="published"', false)
        ->assertSee('value="scheduled"', false)
        ->assertSee('name="breaking"', false)
        ->assertSee('name="published_at"', false);
});

it('renders the create form per role', function () {
    $this->actingAs($this->reporter)->get('/admin/news/create')->assertOk()->assertDontSee('value="published"', false);
    $this->actingAs($this->editor)->get('/admin/news/create')->assertOk()->assertSee('value="published"', false);
});

it('renders the revisions page', function () {
    $news = pgArticle($this->editor);
    ArticleRevision::capture($news, $this->editor, 'প্রথম নোট');

    $this->actingAs($this->editor)->get("/admin/news/{$news->id}/revisions")
        ->assertOk()
        ->assertSee('প্রথম নোট')
        ->assertSee('পুনরুদ্ধার');
});

it('renders the news list with status filters, search and pagination', function () {
    foreach (range(1, 17) as $i) {
        pgArticle($this->editor, $i % 2 ? 'draft' : 'published', ['title' => "তালিকা সংবাদ {$i}"]);
    }

    $this->actingAs($this->editor)->get('/admin/news')
        ->assertOk()
        ->assertSee('page=2', false); // 17 articles, 15 per page: the pager must exist

    $this->actingAs($this->editor)->get('/admin/news?status=published')->assertOk()->assertDontSee('খসড়া</span>', false);
    $this->actingAs($this->editor)->get('/admin/news?q=১')->assertOk();
    $this->actingAs($this->editor)->get('/admin/news?status=bogus')->assertOk();
});

it('lists trashed articles for editors only', function () {
    $news = pgArticle($this->editor, 'draft', ['title' => 'ট্র্যাশের সংবাদ']);
    $news->delete();

    $this->actingAs($this->editor)->get('/admin/news?status=trashed')->assertOk()->assertSee('ট্র্যাশের সংবাদ');

    // a reporter asking for ?status=trashed just gets their normal list (no trash access)
    $mine = pgArticle($this->reporter, 'draft', ['title' => 'রিপোর্টারের খসড়া']);
    $this->actingAs($this->reporter)->get('/admin/news?status=trashed')->assertOk()
        ->assertDontSee('ট্র্যাশের সংবাদ')
        ->assertSee('রিপোর্টারের খসড়া');
});

it('shows the right workflow buttons in the list', function () {
    pgArticle($this->reporter, 'draft', ['title' => 'খসড়া এক']);
    pgArticle($this->reporter, 'pending', ['title' => 'অপেক্ষমাণ দুই']);

    $this->actingAs($this->reporter)->get('/admin/news')->assertOk()
        ->assertSee('পর্যালোচনায় পাঠান')
        ->assertSee('ফেরত নিন')
        ->assertDontSee('>প্রকাশ<', false);

    $this->actingAs($this->editor)->get('/admin/news')->assertOk()
        ->assertSee('>প্রকাশ<', false)
        ->assertSee('ফেরত পাঠান');
});

it('renders the user pages', function () {
    $user = User::factory()->reporter()->create(['name' => 'এডিট করার ইউজার', 'bio' => 'ছোট পরিচিতি']);

    $this->actingAs($this->admin)->get('/admin/users')->assertOk()->assertSee('এডিট করার ইউজার');
    $this->actingAs($this->admin)->get('/admin/users/create')->assertOk()->assertSee('name="role"', false);
    $this->actingAs($this->admin)->get("/admin/users/{$user->id}/edit")
        ->assertOk()
        ->assertSee('এডিট করার ইউজার')
        ->assertSee('ছোট পরিচিতি');
});

it('renders the dashboard with real numbers', function () {
    pgArticle($this->editor, 'published');
    pgArticle($this->editor, 'published');
    pgArticle($this->editor, 'draft');
    pgArticle($this->editor, 'pending');

    $response = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    $html = $response->getContent();

// published count 2 appears inside the "প্রকাশিত" card
expect($html)->toMatch(
    '/<div class="ad-stat-value">\s*2\s*<\/div>\s*<div class="ad-stat-label">প্রকাশিত<\/div>/u'
)->and($html)->toMatch(
    '/<div class="ad-stat-value">\s*4\s*<\/div>\s*<div class="ad-stat-label">মোট সংবাদ<\/div>/u'
);

    // a reporter only counts their own
    pgArticle($this->reporter, 'draft');
    $reporterHtml = $this->actingAs($this->reporter)->get('/dashboard')->getContent();
    expect($reporterHtml)->toMatch(
    '/<div class="ad-stat-value">\s*1\s*<\/div>\s*<div class="ad-stat-label">আমার সংবাদ<\/div>/u'
);
});

it('renders the public site with a scheduled-then-published article date', function () {
    $news = pgArticle($this->editor, 'published', ['title' => 'তারিখের সংবাদ', 'published_at' => now()->subDays(3)]);

    $this->get(route('frontend.news.show', $news->slug))
        ->assertOk()
        ->assertSee($news->published_at->format('d M Y'));
});
