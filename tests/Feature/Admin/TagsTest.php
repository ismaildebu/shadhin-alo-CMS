<?php

use App\Models\Activity;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\News;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->editor()->create();
    $this->reporter = User::factory()->reporter()->create();
});

function tgPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'ট্যাগ পরীক্ষার সংবাদ',
        'description' => '<p>বিস্তারিত</p>',
        'status' => 'draft',
    ], $overrides);
}

function tgArticle(string $status = 'published', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => test()->category->id,
        'author_id' => test()->editor->id,
        'title' => "ট্যাগ সংবাদ {$n}",
        'slug' => "tg-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(10 - min($n, 9)) : null,
    ], $extra));
}

// ------------------------------------------------- assigning tags to articles

it('creates tags from the article form and attaches them', function () {
    $this->actingAs($this->editor)
        ->post('/admin/news', tgPayload(['tags' => ['রাজনীতি', 'নির্বাচন', 'ঢাকা']]))
        ->assertSessionHasNoErrors();

    $news = News::firstOrFail();

    expect($news->tags->pluck('name')->sort()->values()->all())->toBe(collect(['রাজনীতি', 'নির্বাচন', 'ঢাকা'])->sort()->values()->all())
        ->and(Tag::count())->toBe(3);
});

it('accepts comma-separated text when JavaScript is not available', function () {
    $this->actingAs($this->editor)
        ->post('/admin/news', tgPayload(['tag_input' => 'খেলা, ক্রিকেট ،  বিপিএল']))
        ->assertSessionHasNoErrors();

    expect(News::firstOrFail()->tags->pluck('name')->sort()->values()->all())
        ->toBe(collect(['খেলা', 'ক্রিকেট', 'বিপিএল'])->sort()->values()->all());
});

it('reuses an existing tag instead of creating a duplicate, whatever the case or spelling', function () {
    $this->actingAs($this->editor)->post('/admin/news', tgPayload(['tags' => ['Bangladesh', "জাতী\u{09DF}"]]));
    $this->actingAs($this->editor)->post('/admin/news', tgPayload(['title' => 'দ্বিতীয়', 'tags' => ['bangladesh', 'BANGLADESH', "জাতী\u{09AF}\u{09BC}"]]));

    expect(Tag::count())->toBe(2)
        ->and(Tag::where('slug', 'bangladesh')->first()->news()->count())->toBe(2);
});

it('collapses duplicates within one article', function () {
    $this->actingAs($this->editor)->post('/admin/news', tgPayload(['tags' => ['ঢাকা', 'ঢাকা', ' ঢাকা ', 'DHAKA', 'dhaka']]));

    expect(News::firstOrFail()->tags)->toHaveCount(2);
});

it('limits an article to ten tags and each tag to fifty characters', function () {
    $this->actingAs($this->editor)
        ->post('/admin/news', tgPayload(['tags' => array_map(fn ($i) => "ট্যাগ{$i}", range(1, 11))]))
        ->assertSessionHasErrors('tags');

    $this->actingAs($this->editor)
        ->post('/admin/news', tgPayload(['tags' => [str_repeat('অ', 51)]]))
        ->assertSessionHasErrors('tags.0');

    expect(News::count())->toBe(0)->and(Tag::count())->toBe(0);

    $this->actingAs($this->editor)
        ->post('/admin/news', tgPayload(['tags' => array_map(fn ($i) => "ট্যাগ{$i}", range(1, 10))]))
        ->assertSessionHasNoErrors();

    expect(News::firstOrFail()->tags)->toHaveCount(10);
});

it('stores tag names as plain text', function () {
    $this->actingAs($this->editor)->post('/admin/news', tgPayload(['tags' => ['<script>alert(1)</script>', '<b>মোটা</b>']]));

    $names = Tag::pluck('name')->all();

    expect($names)->each->not->toContain('<');
});

it('replaces the tags on update and removes them when none are sent', function () {
    $news = tgArticle('draft');
    $news->syncTagNames(['পুরনো', 'রাখব']);

    $this->actingAs($this->editor)
        ->put("/admin/news/{$news->id}", tgPayload(['tags' => ['রাখব', 'নতুন']]))
        ->assertSessionHasNoErrors();
    expect($news->fresh()->tags->pluck('name')->sort()->values()->all())->toBe(collect(['রাখব', 'নতুন'])->sort()->values()->all());

    // the form always sends tags_present, even when every chip was removed
    $this->actingAs($this->editor)
        ->put("/admin/news/{$news->id}", tgPayload(['tags_present' => 1]))
        ->assertSessionHasNoErrors();
    expect($news->fresh()->tags)->toHaveCount(0);
});

it('leaves the tags alone when an update does not mention them at all', function () {
    $news = tgArticle('draft');
    $news->syncTagNames(['থাকবে']);

    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", tgPayload(['title' => 'শুধু শিরোনাম বদল']))
        ->assertSessionHasNoErrors();

    expect($news->fresh()->tags->pluck('name')->all())->toBe(['থাকবে']);
});

it('lets a reporter tag their own article but not anyone else\'s', function () {
    $mine = tgArticle('draft', ['author_id' => $this->reporter->id]);
    $theirs = tgArticle('draft', ['author_id' => $this->editor->id]);

    $this->actingAs($this->reporter)->put("/admin/news/{$mine->id}", tgPayload(['tags' => ['আমার-ট্যাগ']]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->reporter)->put("/admin/news/{$theirs->id}", tgPayload(['tags' => ['অনধিকার']]))
        ->assertForbidden();

    expect($mine->fresh()->tags->pluck('name')->all())->toBe(['আমার-ট্যাগ'])
        ->and(Tag::where('name', 'অনধিকার')->exists())->toBeFalse();
});

it('keeps tags in the revision history and restores them with an old version', function () {
    $this->actingAs($this->editor)->post('/admin/news', tgPayload(['title' => 'সংস্করণ', 'tags' => ['প্রথম-ট্যাগ']]));
    $news = News::firstOrFail();

    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", tgPayload(['title' => 'সংস্করণ', 'tags' => ['দ্বিতীয়-ট্যাগ']]));
    expect($news->fresh()->tags->pluck('name')->all())->toBe(['দ্বিতীয়-ট্যাগ']);

    $first = ArticleRevision::where('news_id', $news->id)->orderBy('id')->first();
    expect($first->snapshot['tags'])->toBe(['প্রথম-ট্যাগ']);

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$first->id}/restore");

    expect($news->fresh()->tags->pluck('name')->all())->toBe(['প্রথম-ট্যাগ']);
});

it('restores an old revision that predates tags without touching the current tags', function () {
    $news = tgArticle('draft');
    $news->syncTagNames(['বর্তমান']);
    $legacy = ArticleRevision::create([
        'news_id' => $news->id,
        'snapshot' => ['title' => 'পুরনো শিরোনাম', 'description' => '<p>x</p>', 'category_id' => $this->category->id],
        'created_at' => now(),
    ]);

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$legacy->id}/restore");

    expect($news->fresh()->title)->toBe('পুরনো শিরোনাম')
        ->and($news->fresh()->tags->pluck('name')->all())->toBe(['বর্তমান']);
});

it('shows the current tags in the edit form', function () {
    $news = tgArticle('draft');
    $news->syncTagNames(['ফর্মের-ট্যাগ']);

    $this->actingAs($this->editor)->get("/admin/news/{$news->id}/edit")
        ->assertOk()
        ->assertSee('name="tags[]" value="ফর্মের-ট্যাগ"', false)
        ->assertSee('id="tag-field"', false)
        ->assertSee('name="tags_present"', false)
        ->assertSee('js/admin-tag-input.js', false);
});

it('keeps the typed tags when the form fails validation', function () {
    $this->actingAs($this->editor)->from('/admin/news/create')
        ->post('/admin/news', tgPayload(['title' => '', 'tags' => ['রয়ে-যাবে']]))
        ->assertSessionHasErrors('title');

    $this->actingAs($this->editor)->get('/admin/news/create')
        ->assertSee('রয়ে-যাবে');
});

// ---------------------------------------------------------------- autocomplete

it('suggests existing tags, most used first', function () {
    foreach (['রাজনীতি', 'রাজধানী', 'রাজশাহী'] as $name) {
        Tag::create(['name' => $name, 'slug' => Tag::slugFor($name)]);
    }
    $busy = tgArticle();
    $busy->syncTagNames(['রাজধানী']);

    $names = $this->actingAs($this->reporter)->getJson('/admin/tags/suggest?q=রাজ')
        ->assertOk()->json('tags');

    expect($names)->toHaveCount(3)->and($names[0])->toBe('রাজধানী');

    $this->actingAs($this->reporter)->getJson('/admin/tags/suggest?q=রাজশা')->assertJson(['tags' => ['রাজশাহী']]);
});

it('returns nothing for an empty query and requires login', function () {
    Tag::create(['name' => 'ক', 'slug' => 'ক']);

    $this->actingAs($this->reporter)->getJson('/admin/tags/suggest?q=')->assertOk()->assertExactJson(['tags' => []]);
    $this->actingAs($this->reporter)->getJson('/admin/tags/suggest')->assertOk()->assertExactJson(['tags' => []]);

    auth()->logout();
    $this->getJson('/admin/tags/suggest?q=ক')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/admin/tags/suggest?q=ক')->assertForbidden();
});

it('treats % and _ in a suggestion query literally', function () {
    Tag::create(['name' => 'abc', 'slug' => 'abc']);

    $this->actingAs($this->editor)->getJson('/admin/tags/suggest?q=%')->assertOk()->assertExactJson(['tags' => []]);
});

// ------------------------------------------------------------------ public page

it('shows a tag page with its published articles only', function () {
    $live = tgArticle('published', ['title' => 'প্রকাশিত ট্যাগ-সংবাদ']);
    $draft = tgArticle('draft', ['title' => 'খসড়া ট্যাগ-সংবাদ']);
    $other = tgArticle('published', ['title' => 'অন্য ট্যাগের সংবাদ']);
    $live->syncTagNames(['বাংলাদেশ']);
    $draft->syncTagNames(['বাংলাদেশ']);
    $other->syncTagNames(['আন্তর্জাতিক']);

    $this->get(route('frontend.tag.show', 'বাংলাদেশ'))
        ->assertOk()
        ->assertSee('প্রকাশিত ট্যাগ-সংবাদ')
        ->assertDontSee('খসড়া ট্যাগ-সংবাদ')
        ->assertDontSee('অন্য ট্যাগের সংবাদ')
        ->assertSee('<title>ট্যাগ: বাংলাদেশ | স্বাধীন আলো</title>', false);
});

it('finds a tag whichever way the Bengali letters are spelled in the URL', function () {
    $news = tgArticle();
    $news->syncTagNames(["জাতী\u{09DF}"]);

    $this->get('/tag/'.rawurlencode("জাতী\u{09DF}"))->assertOk();
    $this->get('/tag/'.rawurlencode("জাতী\u{09AF}\u{09BC}"))->assertOk();
});

it('returns 404 for unknown tags and for tags with nothing public (never revealing draft-only tags)', function () {
    $this->get('/tag/does-not-exist')->assertNotFound();

    $draft = tgArticle('draft');
    $draft->syncTagNames(['গোপন-খসড়ার-ট্যাগ']);
    $this->get(route('frontend.tag.show', 'গোপন-খসড়ার-ট্যাগ'))->assertNotFound();

    $trashed = tgArticle('published');
    $trashed->syncTagNames(['মুছে-ফেলা']);
    $trashed->delete();
    $this->get(route('frontend.tag.show', 'মুছে-ফেলা'))->assertNotFound();
});

it('paginates a tag page, newest first', function () {
    foreach (range(1, 13) as $i) {
        tgArticle('published', ['title' => "পেজ-ট্যাগ সংবাদ {$i}", 'published_at' => now()->subHours(20 - $i)])
            ->syncTagNames(['পেজিনেশন']);
    }

    $this->get(route('frontend.tag.show', 'পেজিনেশন'))->assertOk()
        ->assertSee('পেজ-ট্যাগ সংবাদ 13')->assertSee('page=2', false)->assertDontSee('পেজ-ট্যাগ সংবাদ 1<', false);
    $this->get(route('frontend.tag.show', 'পেজিনেশন').'?page=2')->assertOk()->assertSee('পেজ-ট্যাগ সংবাদ 1');
});

it('shows the tags, as links, on the article page', function () {
    $news = tgArticle();
    $news->syncTagNames(['লিংকের-ট্যাগ']);

    $this->get(route('frontend.news.show', $news->slug))
        ->assertOk()
        ->assertSee('লিংকের-ট্যাগ')
        ->assertSee(route('frontend.tag.show', Tag::first()->slug), false);
});

it('escapes tag names everywhere on the public site', function () {
    $news = tgArticle();
    $tag = Tag::create(['name' => '<script>alert(9)</script>', 'slug' => 'evil']);
    $news->tags()->attach($tag);

    $this->get(route('frontend.news.show', $news->slug))->assertOk()->assertDontSee('<script>alert(9)</script>', false);
    $this->get('/tag/evil')->assertOk()->assertDontSee('<script>alert(9)</script>', false);
    $this->get('/')->assertOk()->assertDontSee('<script>alert(9)</script>', false);
});

// -------------------------------------------------------------- popular tags

it('shows popular tags from recent published articles on the home page', function () {
    foreach (range(1, 3) as $i) {
        tgArticle('published', ['published_at' => now()->subDays(2)])->syncTagNames(['সবচেয়ে-জনপ্রিয়']);
    }
    tgArticle('published', ['published_at' => now()->subDays(2)])->syncTagNames(['কম-জনপ্রিয়']);

    $html = $this->get('/')->assertOk()->assertSee('জনপ্রিয় ট্যাগ')->getContent();

    expect(strpos($html, 'সবচেয়ে-জনপ্রিয়'))->toBeLessThan(strpos($html, 'কম-জনপ্রিয়'));
});

it('leaves out tags that only belong to old, draft or deleted articles', function () {
    tgArticle('published', ['published_at' => now()->subDays(60)])->syncTagNames(['পুরনো-দিনের']);
    tgArticle('draft')->syncTagNames(['খসড়ার-ট্যাগ']);
    $gone = tgArticle('published');
    $gone->syncTagNames(['মুছে-গেছে']);
    $gone->delete();

    $this->get('/')->assertOk()
        ->assertDontSee('পুরনো-দিনের')->assertDontSee('খসড়ার-ট্যাগ')->assertDontSee('মুছে-গেছে');
});

it('updates the popular tags immediately (cache is flushed by tag changes)', function () {
    $news = tgArticle('published', ['published_at' => now()->subDay()]);

    $this->get('/')->assertOk()->assertDontSee('তাৎক্ষণিক-ট্যাগ');

    $news->syncTagNames(['তাৎক্ষণিক-ট্যাগ']);

    $this->get('/')->assertSee('তাৎক্ষণিক-ট্যাগ');
});

// ----------------------------------------------------------- admin management

it('keeps tag management away from reporters and plain users', function () {
    $tag = Tag::create(['name' => 'টেস্ট', 'slug' => 'টেস্ট']);
    $other = Tag::create(['name' => 'অন্য', 'slug' => 'অন্য']);

    foreach ([$this->reporter, User::factory()->create()] as $user) {
        $this->actingAs($user)->get('/admin/tags')->assertForbidden();
        $this->actingAs($user)->get("/admin/tags/{$tag->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/admin/tags/{$tag->id}", ['name' => 'হ্যাক'])->assertForbidden();
        $this->actingAs($user)->post("/admin/tags/{$tag->id}/merge", ['into' => $other->id])->assertForbidden();
        $this->actingAs($user)->delete("/admin/tags/{$tag->id}")->assertForbidden();
    }

    expect(Tag::count())->toBe(2)->and($tag->fresh()->name)->toBe('টেস্ট');
});

it('lets editors and admins list, search and sort tags', function () {
    foreach (['আলফা', 'বিটা', 'গামা'] as $name) {
        Tag::create(['name' => $name, 'slug' => $name]);
    }
    tgArticle()->syncTagNames(['গামা']);

    foreach ([$this->editor, $this->admin] as $user) {
        $this->actingAs($user)->get('/admin/tags')->assertOk()->assertSee('আলফা')->assertSee('গামা');
    }

    $html = $this->actingAs($this->admin)->get('/admin/tags')->getContent();
    expect(strpos($html, 'গামা'))->toBeLessThan(strpos($html, 'আলফা')); // used tags first

    $search = $this->actingAs($this->admin)->get('/admin/tags?q=বিটা')->assertOk();
    $search->assertSee('বিটা')->assertDontSee('আলফা')->assertDontSee('গামা');
    $this->actingAs($this->admin)->get('/admin/tags?sort=name')->assertOk();
});

it('renames a tag but keeps its slug, so public links do not break', function () {
    $tag = Tag::create(['name' => 'পুরনো নাম', 'slug' => 'puroni']);

    $this->actingAs($this->editor)->put("/admin/tags/{$tag->id}", ['name' => 'নতুন নাম'])->assertSessionHasNoErrors();

    $tag->refresh();
    expect($tag->name)->toBe('নতুন নাম')->and($tag->slug)->toBe('puroni')
        ->and(Activity::where('action', 'tag.updated')->exists())->toBeTrue();
});

it('changes the slug only when asked, and refuses a slug another tag uses', function () {
    $a = Tag::create(['name' => 'এ', 'slug' => 'a-tag']);
    $b = Tag::create(['name' => 'বি', 'slug' => 'b-tag']);

    $this->actingAs($this->editor)->put("/admin/tags/{$a->id}", ['name' => 'এ', 'slug' => 'b-tag'])->assertSessionHasErrors('slug');
    $this->actingAs($this->editor)->put("/admin/tags/{$a->id}", ['name' => 'এ', 'slug' => 'a-tag'])->assertSessionHasNoErrors(); // its own slug is fine
    $this->actingAs($this->editor)->put("/admin/tags/{$a->id}", ['name' => 'এ', 'slug' => 'Brand New!'])->assertSessionHasNoErrors();

    expect($a->fresh()->slug)->toBe('brand-new');
});

it('validates the tag name', function () {
    $tag = Tag::create(['name' => 'এ', 'slug' => 'a']);

    $this->actingAs($this->editor)->put("/admin/tags/{$tag->id}", ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($this->editor)->put("/admin/tags/{$tag->id}", ['name' => '   '])->assertSessionHasErrors('name');
    $this->actingAs($this->editor)->put("/admin/tags/{$tag->id}", ['name' => str_repeat('অ', 51)])->assertSessionHasErrors('name');
});

it('merges a tag into another: articles move over, duplicates are not created, the old tag is gone', function () {
    $from = Tag::create(['name' => 'বাংলাদেশে', 'slug' => 'বাংলাদেশে']);
    $into = Tag::create(['name' => 'বাংলাদেশ', 'slug' => 'বাংলাদেশ']);

    $onlyFrom = tgArticle();
    $both = tgArticle();
    $onlyInto = tgArticle();
    $onlyFrom->tags()->attach($from);
    $both->tags()->attach([$from->id, $into->id]);
    $onlyInto->tags()->attach($into);

    $this->actingAs($this->editor)->post("/admin/tags/{$from->id}/merge", ['into' => $into->id])
        ->assertRedirect(route('tags.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '"বাংলাদেশে" ট্যাগটি "বাংলাদেশ"-এ মার্জ হয়েছে।');

    expect(Tag::find($from->id))->toBeNull()
        ->and($into->news()->count())->toBe(3)
        ->and($onlyFrom->fresh()->tags->pluck('name')->all())->toBe(['বাংলাদেশ'])
        ->and($both->fresh()->tags)->toHaveCount(1)
        ->and(\DB::table('article_tag')->count())->toBe(3);

    $log = Activity::where('action', 'tag.merged')->firstOrFail();
    expect($log->properties)->toMatchArray(['merged_name' => 'বাংলাদেশে', 'merged_slug' => 'বাংলাদেশে', 'into' => 'বাংলাদেশ', 'articles_moved' => 1]);
});

it('merges trashed and unpublished articles too', function () {
    $from = Tag::create(['name' => 'ক', 'slug' => 'ক']);
    $into = Tag::create(['name' => 'খ', 'slug' => 'খ']);
    $trashed = tgArticle();
    $trashed->tags()->attach($from);
    $trashed->delete();

    $this->actingAs($this->editor)->post("/admin/tags/{$from->id}/merge", ['into' => $into->id]);

    expect($into->news()->withTrashed()->count())->toBe(1);
});

it('refuses to merge a tag into itself or into a tag that does not exist', function () {
    $tag = Tag::create(['name' => 'ক', 'slug' => 'ক']);
    tgArticle()->tags()->attach($tag);

    $this->actingAs($this->editor)->post("/admin/tags/{$tag->id}/merge", ['into' => $tag->id])->assertSessionHasErrors('into');
    $this->actingAs($this->editor)->post("/admin/tags/{$tag->id}/merge", ['into' => 9999])->assertSessionHasErrors('into');
    $this->actingAs($this->editor)->post("/admin/tags/{$tag->id}/merge", [])->assertSessionHasErrors('into');

    expect(Tag::find($tag->id))->not->toBeNull()->and($tag->news()->count())->toBe(1);
});

it('deletes a tag and detaches it from articles, keeping the articles', function () {
    $tag = Tag::create(['name' => 'মুছব', 'slug' => 'মুছব']);
    $news = tgArticle();
    $news->tags()->attach($tag);

    $this->actingAs($this->editor)->delete("/admin/tags/{$tag->id}")->assertRedirect(route('tags.index'));

    expect(Tag::count())->toBe(0)->and(News::find($news->id))->not->toBeNull()
        ->and($news->fresh()->tags)->toHaveCount(0)
        ->and(\DB::table('article_tag')->count())->toBe(0)
        ->and(Activity::where('action', 'tag.deleted')->first()->properties['articles'])->toBe(1);
});

it('removes tag links when an article is deleted for good', function () {
    $news = tgArticle();
    $news->syncTagNames(['স্থায়ী']);

    $news->forceDelete();

    expect(\DB::table('article_tag')->count())->toBe(0)->and(Tag::count())->toBe(1);
});

it('renders the tag admin pages', function () {
    $tag = Tag::create(['name' => 'পেজ-টেস্ট', 'slug' => 'page-test']);
    Tag::create(['name' => 'অন্য-ট্যাগ', 'slug' => 'other']);

    $this->actingAs($this->editor)->get('/admin/tags')->assertOk()->assertSee('মার্জ')->assertSee('অন্য-ট্যাগ');
    $this->actingAs($this->editor)->get("/admin/tags/{$tag->id}/edit")->assertOk()->assertSee('page-test');
    $this->actingAs($this->editor)->get('/dashboard')->assertSee('/admin/tags', false);
    $this->actingAs($this->reporter)->get('/dashboard')->assertDontSee('/admin/tags', false);
});

// ------------------------------------------------- gaps found by mutation testing

it('caps tags per article in the model too, not only in the form request', function () {
    $names = array_map(fn ($i) => "মডেল-ট্যাগ{$i}", range(1, 15));

    expect(Tag::idsForNames($names))->toHaveCount(Tag::MAX_PER_ARTICLE);

    $news = tgArticle('draft');
    $news->syncTagNames($names);

    expect($news->fresh()->tags)->toHaveCount(Tag::MAX_PER_ARTICLE);
});

it('re-ranks popular tags at once when an EXISTING tag is attached to EXISTING articles (no model event fires)', function () {
    // every article and both tags exist before the page is first rendered
    foreach ([['ক', 1], ['খ', 2]] as [$name, $uses]) {
        for ($i = 0; $i < $uses; $i++) {
            tgArticle('published', ['published_at' => now()->subDay()])->syncTagNames([$name]);
        }
    }
    $spare = collect(range(1, 3))->map(fn () => tgArticle('published', ['published_at' => now()->subDay()]));

    // URLs carry the Bengali slug percent-encoded: find the links by their real href
    $pos = fn (string $html, string $slug) => strpos($html, 'href="'.route('frontend.tag.show', $slug).'"');

    $html = $this->get('/')->assertOk()->getContent();      // builds (and caches) the popular-tags list
    expect($pos($html, 'ক'))->not->toBeFalse()->and($pos($html, 'খ'))->not->toBeFalse()
        ->and($pos($html, 'খ'))->toBeLessThan($pos($html, 'ক'));

    // Only pivot rows change from here on: no News is saved, no Tag is created.
    $spare->each(fn ($article) => $article->syncTagNames(['ক']));

    $html = $this->get('/')->assertOk()->getContent();
    expect($pos($html, 'ক'))->toBeLessThan($pos($html, 'খ'));
});
