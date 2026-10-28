<?php

use App\Models\Activity;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\News;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $this->reporter = User::factory()->reporter()->create(['name' => 'রিপোর্টার এক']);
    $this->other = User::factory()->reporter()->create(['name' => 'রিপোর্টার দুই']);
    $this->editor = User::factory()->editor()->create();
    $this->admin = User::factory()->admin()->create();
});

function wfArticle(User $author, string $status = 'draft', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => test()->category->id,
        'author_id' => $author->id,
        'title' => "ওয়ার্কফ্লো সংবাদ {$n}",
        'slug' => "wf-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinute() : null,
    ], $extra));
}

function wfPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'নতুন প্রতিবেদন',
        'description' => '<p>প্রতিবেদনের বিস্তারিত</p>',
        'status' => 'draft',
    ], $overrides);
}

// ---------------------------------------------------------------- reporter

it('lets a reporter save a draft and sets them as the author', function () {
    $this->actingAs($this->reporter)->post('/admin/news', wfPayload())->assertSessionHasNoErrors();

    $news = News::firstOrFail();

    expect($news->status)->toBe('draft')
        ->and($news->author_id)->toBe($this->reporter->id)
        ->and($news->reporter)->toBe('রিপোর্টার এক')
        ->and($news->published_at)->toBeNull();
});

it('stops a reporter from creating an article directly as published or scheduled', function (string $status) {
    $this->actingAs($this->reporter)
        ->post('/admin/news', wfPayload(['status' => $status, 'published_at' => now()->addDay()->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('status');

    expect(News::count())->toBe(0);
})->with(['published', 'scheduled', 'archived']);

it('ignores editorial flags sent by a reporter', function () {
    $this->actingAs($this->reporter)->post('/admin/news', wfPayload(['breaking' => 1, 'featured' => 1, 'is_lead' => 1]));

    $news = News::firstOrFail();

    expect($news->breaking)->toBeFalse()->and($news->featured)->toBeFalse()->and($news->is_lead)->toBeFalse();
});

it('does not wipe editor flags when a reporter edits their own pending article', function () {
    $news = wfArticle($this->reporter, 'pending', ['breaking' => true, 'is_lead' => true]);

    $this->actingAs($this->reporter)->put("/admin/news/{$news->id}", wfPayload(['title' => 'সংশোধিত শিরোনাম', 'status' => 'pending']))
        ->assertSessionHasNoErrors();

    $news->refresh();

    expect($news->title)->toBe('সংশোধিত শিরোনাম')->and($news->breaking)->toBeTrue()->and($news->is_lead)->toBeTrue();
});

it('moves a reporter article draft -> pending -> draft, but never to published', function () {
    $news = wfArticle($this->reporter);

    $this->actingAs($this->reporter)->post("/admin/news/{$news->id}/transition", ['to' => 'pending'])
        ->assertSessionHasNoErrors();
    expect($news->fresh()->status)->toBe('pending');

    $this->actingAs($this->reporter)->post("/admin/news/{$news->id}/transition", ['to' => 'published'])->assertForbidden();
    expect($news->fresh()->status)->toBe('pending');

    $this->actingAs($this->reporter)->post("/admin/news/{$news->id}/transition", ['to' => 'draft'])
        ->assertSessionHasNoErrors();
    expect($news->fresh()->status)->toBe('draft');
});

it('keeps a reporter away from other reporters articles', function () {
    $news = wfArticle($this->other);

    $this->actingAs($this->reporter)->get("/admin/news/{$news->id}/edit")->assertForbidden();
    $this->actingAs($this->reporter)->put("/admin/news/{$news->id}", wfPayload())->assertForbidden();
    $this->actingAs($this->reporter)->post("/admin/news/{$news->id}/transition", ['to' => 'pending'])->assertForbidden();
    $this->actingAs($this->reporter)->delete("/admin/news/{$news->id}")->assertForbidden();
    $this->actingAs($this->reporter)->get("/admin/news/{$news->id}/preview")->assertForbidden();
    $this->actingAs($this->reporter)->get("/admin/news/{$news->id}/revisions")->assertForbidden();
});

it('freezes a reporter out of their own article once it is published', function () {
    $news = wfArticle($this->reporter, 'published');

    $this->actingAs($this->reporter)->get("/admin/news/{$news->id}/edit")->assertForbidden();
    $this->actingAs($this->reporter)->put("/admin/news/{$news->id}", wfPayload(['title' => 'চুপিসারে বদল']))->assertForbidden();
    $this->actingAs($this->reporter)->delete("/admin/news/{$news->id}")->assertForbidden();
    $this->actingAs($this->reporter)->post("/admin/news/{$news->id}/transition", ['to' => 'draft'])->assertForbidden();

    expect($news->fresh()->title)->not->toBe('চুপিসারে বদল');
});

it('lets a reporter delete only their own drafts', function () {
    $draft = wfArticle($this->reporter, 'draft');
    $pending = wfArticle($this->reporter, 'pending');

    $this->actingAs($this->reporter)->delete("/admin/news/{$pending->id}")->assertForbidden();
    $this->actingAs($this->reporter)->delete("/admin/news/{$draft->id}")->assertRedirect();

    $this->assertSoftDeleted('news', ['id' => $draft->id]);
    $this->assertNotSoftDeleted('news', ['id' => $pending->id]);
});

it('shows a reporter only their own articles in the list', function () {
    wfArticle($this->reporter, 'draft', ['title' => 'আমার নিজের লেখা']);
    wfArticle($this->other, 'draft', ['title' => 'অন্যের গোপন লেখা']);

    $this->actingAs($this->reporter)->get('/admin/news')
        ->assertOk()
        ->assertSee('আমার নিজের লেখা')
        ->assertDontSee('অন্যের গোপন লেখা');

    $this->actingAs($this->editor)->get('/admin/news')
        ->assertSee('আমার নিজের লেখা')
        ->assertSee('অন্যের গোপন লেখা');
});

// ------------------------------------------------------------------ editor

it('lets an editor publish a pending article and stamps the publish time', function () {
    $news = wfArticle($this->reporter, 'pending');

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'published'])
        ->assertSessionHasNoErrors();

    $news->refresh();

    expect($news->status)->toBe('published')
        ->and($news->published_at->diffInSeconds(now(), true))->toBeLessThan(10);

    $this->get(route('frontend.news.show', $news->slug))->assertOk();
});

it('requires a reason when an editor sends someone else\'s article back', function () {
    $news = wfArticle($this->reporter, 'pending');

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'draft'])
        ->assertSessionHasErrors('note');
    expect($news->fresh()->status)->toBe('pending');

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'draft', 'note' => 'সূত্র যোগ করুন'])
        ->assertSessionHasNoErrors();

    expect($news->fresh()->status)->toBe('draft')
        ->and(ArticleRevision::where('news_id', $news->id)->latest('id')->first()->note)->toContain('সূত্র যোগ করুন');
});

it('rejects status changes that are not on the allowed path', function () {
    $draft = wfArticle($this->reporter, 'draft');
    $archived = wfArticle($this->reporter, 'archived');

    // archived can only go back to draft; draft cannot jump to archived
    $this->actingAs($this->editor)->post("/admin/news/{$archived->id}/transition", ['to' => 'published'])->assertForbidden();
    $this->actingAs($this->editor)->post("/admin/news/{$draft->id}/transition", ['to' => 'archived'])->assertForbidden();
    $this->actingAs($this->editor)->post("/admin/news/{$draft->id}/transition", ['to' => 'bogus'])->assertSessionHasErrors('to');

    expect($draft->fresh()->status)->toBe('draft')->and($archived->fresh()->status)->toBe('archived');
});

it('removes an article from the public site when it is unpublished or archived', function () {
    $news = wfArticle($this->editor, 'published');
    $this->get(route('frontend.news.show', $news->slug))->assertOk();

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'archived']);
    $this->get(route('frontend.news.show', $news->slug))->assertNotFound();

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'draft']);
    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'published']);
    $this->get(route('frontend.news.show', $news->slug))->assertOk();

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'draft']);
    $this->get(route('frontend.news.show', $news->slug))->assertNotFound();
});

it('publishes through the edit form when the editor picks "published"', function () {
    $news = wfArticle($this->reporter, 'pending');

    $this->actingAs($this->editor)
        ->put("/admin/news/{$news->id}", wfPayload(['title' => 'সম্পাদিত ও প্রকাশিত', 'status' => 'published']))
        ->assertSessionHasNoErrors();

    $news->refresh();

    expect($news->status)->toBe('published')->and($news->title)->toBe('সম্পাদিত ও প্রকাশিত');
});

it('previews a draft for the author and editors but keeps it off the public site', function () {
    $news = wfArticle($this->reporter, 'draft', ['title' => 'গোপন খসড়া']);

    $this->actingAs($this->reporter)->get("/admin/news/{$news->id}/preview")->assertOk()->assertSee('গোপন খসড়া');
    $this->actingAs($this->editor)->get("/admin/news/{$news->id}/preview")->assertOk();
    auth()->logout();
    $this->get(route('frontend.news.show', $news->slug))->assertNotFound();
});

// --------------------------------------------------------------- scheduling

it('needs a future time to schedule, and a past-or-now time to publish', function () {
    $news = wfArticle($this->reporter, 'pending');

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'scheduled'])
        ->assertSessionHasErrors('published_at');
    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", [
        'to' => 'scheduled', 'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('published_at');
    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", [
        'to' => 'published', 'published_at' => now()->addHour()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('published_at');

    expect($news->fresh()->status)->toBe('pending');
});

it('keeps a scheduled article hidden until the command publishes it at the due time', function () {
    $news = wfArticle($this->reporter, 'pending');

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", [
        'to' => 'scheduled', 'published_at' => now()->addHour()->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();

    expect($news->fresh()->status)->toBe('scheduled');
    $this->get(route('frontend.news.show', $news->slug))->assertNotFound();

    // not due yet: the command leaves it alone
    $this->artisan('articles:publish-scheduled')->assertSuccessful();
    expect($news->fresh()->status)->toBe('scheduled');

    // time passes
    $this->travel(2)->hours();
    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    expect($news->fresh()->status)->toBe('published');
    $this->get(route('frontend.news.show', $news->slug))->assertOk();
    expect(Activity::where('action', 'article.published_by_schedule')->count())->toBe(1);
});

it('registers the scheduled-publish command with the scheduler every minute', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'articles:publish-scheduled'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('* * * * *');
});

// --------------------------------------------------- audit trail & revisions

it('records every status change in the audit log and revision history', function () {
    $news = wfArticle($this->reporter, 'draft');

    $this->actingAs($this->reporter)->post("/admin/news/{$news->id}/transition", ['to' => 'pending']);
    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/transition", ['to' => 'published']);

    $log = Activity::where('action', 'article.transition')->orderBy('id')->get();

    expect($log)->toHaveCount(2)
        ->and($log[0]->user_id)->toBe($this->reporter->id)
        ->and($log[0]->properties)->toMatchArray(['from' => 'draft', 'to' => 'pending'])
        ->and($log[1]->user_id)->toBe($this->editor->id)
        ->and($log[1]->properties)->toMatchArray(['from' => 'pending', 'to' => 'published'])
        ->and($news->revisions()->count())->toBe(2);
});

it('keeps a revision on every save and can restore an older version', function () {
    $this->actingAs($this->editor)->post('/admin/news', wfPayload(['title' => 'প্রথম শিরোনাম', 'description' => '<p>প্রথম লেখা</p>']));
    $news = News::firstOrFail();

    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", wfPayload(['title' => 'দ্বিতীয় শিরোনাম', 'description' => '<p>দ্বিতীয় লেখা</p>']));
    expect($news->fresh()->title)->toBe('দ্বিতীয় শিরোনাম')->and($news->revisions()->count())->toBe(2);

    $first = ArticleRevision::where('news_id', $news->id)->orderBy('id')->first();

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$first->id}/restore")
        ->assertRedirect(route('news.edit', $news));

    $news->refresh();

    expect($news->title)->toBe('প্রথম শিরোনাম')
        ->and($news->description)->toContain('প্রথম লেখা')
        ->and($news->revisions()->count())->toBe(3);
});

it('refuses to restore a revision that belongs to another article', function () {
    $mine = wfArticle($this->editor);
    $theirs = wfArticle($this->editor);
    $foreign = ArticleRevision::capture($theirs, $this->editor);

    $this->actingAs($this->editor)->post("/admin/news/{$mine->id}/revisions/{$foreign->id}/restore")->assertNotFound();
});

it('re-sanitizes content when restoring a revision', function () {
    $news = wfArticle($this->editor);
    $revision = ArticleRevision::create([
        'news_id' => $news->id,
        'snapshot' => ['title' => 'T', 'description' => '<p>ok</p><script>alert(1)</script>', 'category_id' => $this->category->id],
        'created_at' => now(),
    ]);

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$revision->id}/restore");

    expect($news->fresh()->description)->toBe('<p>ok</p>');
});

it('trims the revision history to the configured limit', function () {
    $news = wfArticle($this->editor);

    foreach (range(1, ArticleRevision::KEEP + 5) as $i) {
        ArticleRevision::capture($news, $this->editor, "v{$i}");
    }

    expect($news->revisions()->count())->toBe(ArticleRevision::KEEP);
});

it('shows the audit log to admins only', function () {
    wfArticle($this->editor);
    Activity::record('article.created', null, ['x' => 1], $this->editor);

    $this->actingAs($this->admin)->get('/admin/activity')->assertOk()->assertSee('article.created');
    $this->actingAs($this->editor)->get('/admin/activity')->assertForbidden();
});
