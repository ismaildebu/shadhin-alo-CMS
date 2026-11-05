<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create();
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo-rel']);
    $this->other = Category::create(['name' => 'খেলা', 'slug' => 'khela-rel']);
});

function relNews(array $overrides = []): News
{
    return News::create(array_merge([
        'category_id' => test()->category->id,
        'title' => 'সম্পর্কিত খবর '.uniqid(),
        'slug' => 'rel-'.uniqid(),
        'description' => '<p>বিস্তারিত</p>',
        'status' => 'published',
        'published_at' => now(),
    ], $overrides));
}

it('offers published articles of the same category and hides drafts, trashed and itself', function () {
    relNews(['title' => 'খসড়া খবর', 'status' => 'draft', 'published_at' => null]);
    $trashed = relNews(['title' => 'মোছা খবর']);
    $trashed->delete();
    $self = relNews(['title' => 'আমার চলমান খবর']);
    $sibling = relNews(['title' => 'একই ক্যাটাগরির পুরোনো খবর', 'published_at' => now()->subWeek()]);
    relNews(['title' => 'অন্য ক্যাটাগরির খবর', 'category_id' => $this->other->id]);

    $items = $this->actingAs($this->editor)
        ->getJson(route('news.related-search', ['exclude' => $self->id, 'categories' => [$this->category->id]]))
        ->assertOk()
        ->json('items');

    $titles = array_column($items, 'title');

    expect($titles)
        ->toContain('একই ক্যাটাগরির পুরোনো খবর')
        ->not->toContain('খসড়া খবর')
        ->not->toContain('মোছা খবর')
        ->not->toContain('আমার চলমান খবর')
        ->not->toContain('অন্য ক্যাটাগরির খবর');
});

it('links to the article page with a site-relative path', function () {
    $news = relNews(['title' => 'লিংকের খবর', 'slug' => 'link-wala-khobor']);

    $item = $this->actingAs($this->editor)
        ->getJson(route('news.related-search', ['categories' => [$this->category->id]]))
        ->assertOk()
        ->json('items')[0];

    expect($item['url'])->toBe('/article/link-wala-khobor')
        ->and($item['title'])->toBe($news->title)
        ->and($item['date'])->not->toBe('');
});

it('searches published titles when the editor types', function () {
    $wanted = relNews(['title' => 'খুলনায় জোরদার তল্লাশি', 'category_id' => $this->other->id]);
    relNews(['title' => 'অপ্রাসঙ্গিক খবর', 'status' => 'draft', 'published_at' => null]);

    $titles = array_column($this->actingAs($this->editor)
        ->getJson(route('news.related-search', ['q' => 'তল্লাশি']))
        ->assertOk()
        ->json('items'), 'title');

    expect($titles)->toBe([$wanted->title]);
});

it('brings in articles that only share a tag', function () {
    $current = relNews(['title' => 'চলমান খবর']);
    $current->syncTagNames(['নির্বাচন']);

    $byTag = relNews(['title' => 'ট্যাগের খবর', 'category_id' => $this->other->id]);
    $byTag->syncTagNames(['নির্বাচন']);

    $titles = array_column($this->actingAs($this->editor)
        ->getJson(route('news.related-search', [
            'exclude' => $current->id,
            'categories' => [$this->category->id],
            'tags' => ['নির্বাচন'],
        ]))
        ->assertOk()
        ->json('items'), 'title');

    expect($titles)->toContain('ট্যাগের খবর');
});

it('falls back to the newest published articles when nothing matches', function () {
    $recent = relNews(['title' => 'সদ্য প্রকাশিত', 'category_id' => $this->other->id]);

    $titles = array_column($this->actingAs($this->editor)
        ->getJson(route('news.related-search'))
        ->assertOk()
        ->json('items'), 'title');

    expect($titles)->toBe([$recent->title]);
});

it('refuses the search to people without panel access', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('news.related-search'))
        ->assertForbidden();

    $this->getJson(route('news.related-search'))->assertForbidden();
});

it('keeps a related link in the body when the article is saved and shown', function () {
    $body = '<p>খুলনায় তল্লাশি চালানো হয়।</p><p><a href="/article/khulna-tollashi">খুলনায় তল্লাশি</a></p>';

    $this->actingAs($this->editor)
        ->post('/admin/news', [
            'category_id' => $this->category->id,
            'category_ids' => [$this->category->id],
            'type' => 'news',
            'title' => 'তল্লাশির প্রতিবেদন',
            'description' => $body,
            'status' => 'published',
        ])
        ->assertSessionHasNoErrors();

    $news = News::firstOrFail();

    expect($news->description)->toContain('href="/article/khulna-tollashi"');

    $this->get('/article/'.$news->slug)
        ->assertOk()
        ->assertSee('href="/article/khulna-tollashi"', false);
});

it('puts the crop and related-link buttons on the article form', function () {
    $news = relNews(['title' => 'ফর্মের খবর']);

    $this->actingAs($this->editor)
        ->get(route('news.edit', $news))
        ->assertOk()
        ->assertSee('ছবি ছেঁটে নিন')
        ->assertSee('সম্পর্কিত সংবাদের লিংক')
        ->assertSee(route('news.related-search'), false);

    $this->actingAs($this->editor)
        ->get(route('news.create'))
        ->assertOk()
        ->assertSee('ছবি ছেঁটে নিন')
        ->assertSee('সম্পর্কিত সংবাদের লিংক');
});
