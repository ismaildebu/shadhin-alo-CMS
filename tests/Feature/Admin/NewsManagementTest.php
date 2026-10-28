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

it('stores uploads under a random name so two uploads in one second cannot collide', function () {
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));
    $this->actingAs($this->admin)->post('/admin/news', newsPayload(['image' => UploadedFile::fake()->image('a.jpg')]));

    $images = News::pluck('image');

    expect($images->unique())->toHaveCount(2);

    foreach ($images as $name) {
        expect($name)->toMatch('/^[0-9a-f-]{36}\.jpg$/')
            ->and(File::exists($this->publicDir.'/uploads/news/'.$name))->toBeTrue();
    }
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
