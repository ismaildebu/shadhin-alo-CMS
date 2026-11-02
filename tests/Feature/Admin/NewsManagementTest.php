<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Never write uploads into the real public/ directory while testing.
    $this->publicDir = sys_get_temp_dir().'/shadhinalo-test-'.uniqid();
    File::makeDirectory($this->publicDir, 0755, true);
    $this->app->usePublicPath($this->publicDir);

    $this->admin = User::factory()->admin()->create();
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
});

afterEach(fn () => File::deleteDirectory($this->publicDir));

function newsPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'পদ্মা সেতুতে নতুন রেকর্ড',
        'description' => '<p>বিস্তারিত সংবাদ</p>',
        'status' => 'published',
    ], $overrides);
}

it('creates an article and sanitizes the HTML body', function () {
    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload([
            'description' => '<p>ভালো লেখা</p><script>alert(1)</script><img src="https://x.test/a.jpg" onerror="alert(1)">',
        ]))
        ->assertRedirect(route('news.index'))
        ->assertSessionHasNoErrors();

    $news = News::firstOrFail();

    expect($news->description)->toContain('<p>ভালো লেখা</p>')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->and($news->seo_title)->toBe($news->title)
        ->and($news->status)->toBe('published')
        ->and($news->published_at)->not->toBeNull()
        ->and($news->author_id)->toBe($this->admin->id);
});

it('rejects a body that is empty after sanitizing', function () {
    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload(['description' => '<script>alert(1)</script>']))
        ->assertSessionHasErrors('description');

    expect(News::count())->toBe(0);
});

it('validates category, status and field lengths', function () {
    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload([
            'category_id' => 9999,
            'status' => 'maybe',
            'reporter' => str_repeat('a', 300),
        ]))
        ->assertSessionHasErrors(['category_id', 'status', 'reporter']);

    expect(News::count())->toBe(0);
});

it('stores unchecked checkboxes as false and checked ones as true', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['breaking' => '1']));

    $news = News::firstOrFail();

    expect($news->breaking)->toBeTrue()
        ->and($news->featured)->toBeFalse()
        ->and($news->is_lead)->toBeFalse();
});

it('generates a non-empty unique slug, also for Bengali and duplicate titles', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload());
    $this->actingAs($this->admin)->post('/admin/news', newsPayload());

    $slugs = News::pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2)
        ->and($slugs->every(fn ($slug) => $slug !== ''))->toBeTrue();
});

it('keeps the slug stable when the title changes', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['title' => 'Original title']));
    $news = News::firstOrFail();
    $slug = $news->slug;

    $this->actingAs($this->admin)
        ->put("/admin/news/{$news->id}", newsPayload(['title' => 'A completely different title']))
        ->assertSessionHasNoErrors();

    expect($news->fresh()->title)->toBe('A completely different title')
        ->and($news->fresh()->slug)->toBe($slug);
});

it('keeps the client filename and adds a suffix when two uploads in one second collide', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));

    $images = News::pluck('image');

    expect($images->all())->toEqualCanonicalizing(['a.jpg', 'a-1.jpg']);

    foreach ($images as $name) {
        expect(File::exists($this->publicDir.'/uploads/news/'.$name))->toBeTrue();
    }
});

it('stores a chart shortcode for the main-image slot and clears it when emptied', function () {
    $shortcode = '[chart id="5"]';

    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload(['chart_shortcode' => $shortcode]))
        ->assertSessionHasNoErrors();

    $news = News::firstOrFail();

    expect($news->chart_shortcode)->toBe($shortcode);

    $this->actingAs($this->admin)
        ->put("/admin/news/{$news->id}", newsPayload(['chart_shortcode' => '']))
        ->assertSessionHasNoErrors();

    expect($news->fresh()->chart_shortcode)->toBeNull();
});

it('rejects a chart shortcode that is not a single chart shortcode', function (string $bad) {
    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload(['chart_shortcode' => $bad]))
        ->assertSessionHasErrors('chart_shortcode');

    expect(News::count())->toBe(0);
})->with([
    'another shortcode' => '[map title="বিভাগ" data="ঢাকা=1"]',
    'html after it' => '[chart id="5"]<script>alert(1)</script>',
    'plain text' => 'গ্রাফ দেখুন',
]);

it('accepts a chart shortcode surrounded by spaces', function () {
    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload(['chart_shortcode' => '  [chart type="column" labels="ক|খ" values="1|2"]  ']))
        ->assertSessionHasNoErrors();

    expect(News::firstOrFail()->chart_shortcode)->toContain('[chart type="column"');
});

it('rejects non-image uploads', function () {
    $this->actingAs($this->admin)
        ->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')]))
        ->assertSessionHasErrors('image');

    expect(News::count())->toBe(0);
});

it('replaces the image on update and removes the old file only afterwards', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));
    $news = News::firstOrFail();
    $old = $news->image;

    $this->actingAs($this->admin)
        ->put("/admin/news/{$news->id}", newsPayload(['image' => UploadedFile::fake()->image('b.png')]));

    $new = $news->fresh()->image;

    expect($new)->not->toBe($old)
        ->and(File::exists($this->publicDir.'/uploads/news/'.$new))->toBeTrue()
        ->and(File::exists($this->publicDir.'/uploads/news/'.$old))->toBeFalse();
});

it('keeps the existing image when no new one is uploaded', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));
    $news = News::firstOrFail();
    $image = $news->image;

    $this->actingAs($this->admin)->put("/admin/news/{$news->id}", newsPayload(['title' => 'Edited']));

    expect($news->fresh()->image)->toBe($image)
        ->and(File::exists($this->publicDir.'/uploads/news/'.$image))->toBeTrue();
});

it('soft-deletes an article and keeps its image so it can be restored', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));
    $news = News::firstOrFail();
    $path = $this->publicDir.'/uploads/news/'.$news->image;

    $this->actingAs($this->admin)->delete("/admin/news/{$news->id}")->assertRedirect(route('news.index'));

    $this->assertSoftDeleted('news', ['id' => $news->id]);
    expect(File::exists($path))->toBeTrue();

    $this->actingAs($this->admin)->post("/admin/news/{$news->id}/restore")->assertRedirect(route('news.index'));

    expect(News::find($news->id))->not->toBeNull();
});

it('does not reuse the slug of a soft-deleted article', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['title' => 'Same title']));
    $first = News::firstOrFail();
    $first->delete();

    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['title' => 'Same title']))
        ->assertRedirect(route('news.index'));

    expect(News::count())->toBe(1)
        ->and(News::first()->slug)->not->toBe($first->slug);
});

it('never deletes files outside the upload folder, even with a tampered image value', function () {
    $outside = $this->publicDir.'/keep-me.txt';
    File::put($outside, 'important');

    $news = News::create([
        'category_id' => $this->category->id, 'title' => 'T', 'slug' => 't',
        'description' => '<p>d</p>', 'image' => '../../keep-me.txt',
    ]);

    // Replacing the image triggers deletion of the (tampered) old value.
    $this->actingAs($this->admin)->put("/admin/news/{$news->id}", newsPayload([
        'image' => UploadedFile::fake()->image('new.jpg'),
    ]));

    expect(File::exists($outside))->toBeTrue();
});

// ------------------------------------------- changing categories from the list

it('changes an article\'s categories from the management list', function () {
    $sports = Category::create(['name' => 'খেলাধুলা', 'slug' => 'sports']);

    $news = News::create([
        'category_id' => $this->category->id, 'title' => 'তালিকা থেকে ক্যাটাগরি', 'slug' => 'list-category',
        'description' => '<p>d</p>',
    ]);
    $news->categories()->sync([$this->category->id]);

    $this->actingAs($this->admin)
        ->from(route('news.index'))
        ->post(route('news.category', $news), ['category_ids' => [$sports->id]])
        ->assertRedirect(route('news.index'))
        ->assertSessionHasNoErrors();

    expect($news->fresh()->category_id)->toBe($sports->id)
        ->and($news->fresh()->categories->pluck('slug')->all())->toBe(['sports']);
});

it('keeps every chosen category and makes the first one the primary', function () {
    $sports = Category::create(['name' => 'খেলাধুলা', 'slug' => 'sports']);
    $world = Category::create(['name' => 'আন্তর্জাতিক', 'slug' => 'international']);

    $news = News::create([
        'category_id' => $this->category->id, 'title' => 'দুই ক্যাটাগরি', 'slug' => 'two-categories',
        'description' => '<p>d</p>',
    ]);
    $news->categories()->sync([$this->category->id]);

    $this->actingAs($this->admin)
        ->post(route('news.category', $news), ['category_ids' => [$world->id, $sports->id]])
        ->assertSessionHasNoErrors();

    $news->refresh();

    expect($news->category_id)->toBe($world->id)
        ->and($news->categories->pluck('slug')->sort()->values()->all())->toBe(['international', 'sports']);
});

it('refuses an empty or unknown category set from the list', function () {
    $news = News::create([
        'category_id' => $this->category->id, 'title' => 'ভুল ক্যাটাগরি', 'slug' => 'bad-category',
        'description' => '<p>d</p>',
    ]);
    $news->categories()->sync([$this->category->id]);

    $this->actingAs($this->admin)->post(route('news.category', $news), ['category_ids' => []])
        ->assertSessionHasErrors('category_ids');
    $this->actingAs($this->admin)->post(route('news.category', $news), ['category_ids' => [999999]])
        ->assertSessionHasErrors('category_ids.0');

    expect($news->fresh()->categories->pluck('slug')->all())->toBe(['jatiyo']);
});

it('does not let a reporter change another author\'s categories from the list', function () {
    $reporter = User::factory()->reporter()->create();

    $news = News::create([
        'category_id' => $this->category->id, 'author_id' => $this->admin->id,
        'title' => 'অন্যের সংবাদ', 'slug' => 'others-news', 'description' => '<p>d</p>',
    ]);
    $news->categories()->sync([$this->category->id]);

    $this->actingAs($reporter)->post(route('news.category', $news), ['category_ids' => [$this->category->id]])
        ->assertForbidden();
});

// ------------------------------------------- bulk delete (list and trash)

it('moves the selected articles to the trash in one request', function () {
    $a = News::create([
        'category_id' => $this->category->id, 'title' => 'এক', 'slug' => 'one',
        'description' => '<p>d</p>',
    ]);
    $b = News::create([
        'category_id' => $this->category->id, 'title' => 'দুই', 'slug' => 'two',
        'description' => '<p>d</p>',
    ]);

    $this->actingAs($this->admin)
        ->post(route('news.bulk-destroy'), ['ids' => [$a->id, $b->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted('news', ['id' => $a->id]);
    $this->assertSoftDeleted('news', ['id' => $b->id]);
});

it('refuses more than 100 articles in one bulk action', function () {
    $ids = range(1, 101);

    $this->actingAs($this->admin)
        ->post(route('news.bulk-destroy'), ['ids' => $ids])
        ->assertSessionHasErrors('ids');

    $this->actingAs($this->admin)
        ->post(route('news.bulk-force-delete'), ['ids' => $ids])
        ->assertSessionHasErrors('ids');
});

it('permanently deletes trashed articles and removes their images', function () {
    $image = 'gone.jpg';
    File::ensureDirectoryExists($this->publicDir.'/uploads/news');
    File::put($this->publicDir.'/uploads/news/'.$image, 'image-bytes');

    $news = News::create([
        'category_id' => $this->category->id, 'title' => 'স্থায়ীভাবে মুছবে', 'slug' => 'force-one',
        'description' => '<p>d</p>', 'image' => $image,
    ]);
    $news->delete();

    $keep = News::create([
        'category_id' => $this->category->id, 'title' => 'থাকবে', 'slug' => 'force-keep',
        'description' => '<p>d</p>',
    ]);
    $keep->delete();

    $this->actingAs($this->admin)
        ->post(route('news.bulk-force-delete'), ['ids' => [$news->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(News::withTrashed()->find($news->id))->toBeNull()
        ->and(News::withTrashed()->find($keep->id))->not->toBeNull()
        ->and(File::exists($this->publicDir.'/uploads/news/'.$image))->toBeFalse();
});

it('does not force delete articles that are not in the trash', function () {
    $active = News::create([
        'category_id' => $this->category->id, 'title' => 'সক্রিয়', 'slug' => 'still-active',
        'description' => '<p>d</p>',
    ]);

    $this->actingAs($this->admin)
        ->post(route('news.bulk-force-delete'), ['ids' => [$active->id]])
        ->assertSessionHasErrors('ids');

    expect(News::find($active->id))->not->toBeNull();
});

it('only lets editors and admins permanently delete from the trash', function () {
    $reporter = User::factory()->reporter()->create();

    $news = News::create([
        'category_id' => $this->category->id, 'author_id' => $reporter->id,
        'title' => 'রিপোর্টারের ট্র্যাশ', 'slug' => 'reporter-trash', 'description' => '<p>d</p>',
    ]);
    $news->delete();

    $this->actingAs($reporter)
        ->post(route('news.bulk-force-delete'), ['ids' => [$news->id]])
        ->assertSessionHasErrors('ids');

    expect(News::withTrashed()->find($news->id))->not->toBeNull();
});

it('will not add সারাদেশ from the list without division, district and upazila', function () {
    $saradesh = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh']);

    $news = News::create([
        'category_id' => $this->category->id, 'title' => 'স্থানহীন সারাদেশ', 'slug' => 'no-place-saradesh',
        'description' => '<p>d</p>',
    ]);
    $news->categories()->sync([$this->category->id]);

    $this->actingAs($this->admin)->post(route('news.category', $news), ['category_ids' => [$saradesh->id]])
        ->assertSessionHasErrors('category_ids');

    expect($news->fresh()->categories->pluck('slug')->all())->toBe(['jatiyo']);

    $division = \App\Models\Location::create(['parent_id' => null, 'type' => 'division', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka', 'slug' => 'dhaka']);
    $district = \App\Models\Location::create(['parent_id' => $division->id, 'type' => 'district', 'name_bn' => 'ঢাকা', 'name_en' => 'Dhaka', 'slug' => 'dhaka']);
    $upazila = \App\Models\Location::create(['parent_id' => $district->id, 'type' => 'upazila', 'name_bn' => 'সাভার', 'name_en' => 'Savar', 'slug' => 'savar']);
    $news->forceFill(['location_id' => $upazila->id])->save();

    $this->actingAs($this->admin)->post(route('news.category', $news), ['category_ids' => [$saradesh->id]])
        ->assertSessionHasNoErrors();

    expect($news->fresh()->categories->pluck('slug')->all())->toBe(['saradesh']);
});
