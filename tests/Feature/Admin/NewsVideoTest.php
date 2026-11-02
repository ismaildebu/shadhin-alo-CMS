<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;
use App\Support\NewsVideo;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo-video']);
});

function videoNews(): News
{
    return News::create([
        'category_id' => test()->category->id,
        'title' => 'ভিডিওর জন্য সংবাদ',
        'slug' => 'video-news-'.uniqid(),
        'description' => '<p>সংক্ষিপ্ত বিবরণ</p>',
        'status' => 'published',
    ]);
}

it('offers a video button on every row and a playback link once a video exists', function () {
    $news = videoNews();

    $this->actingAs($this->admin)
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('ভিডিও তৈরি')
        ->assertSee(route('news.video.store', $news), false)
        ->assertDontSee('ভিডিও দেখুন');

    File::ensureDirectoryExists(dirname(NewsVideo::path($news)));
    File::put(NewsVideo::path($news), 'fake');

    try {
        $this->actingAs($this->admin)
            ->get(route('news.index'))
            ->assertOk()
            ->assertSee('ভিডিও দেখুন')
            ->assertSee('ডাউনলোড')
            ->assertSee(route('news.video.show', $news), false)
            ->assertSee(route('news.video.show', ['news' => $news, 'download' => 1]), false);
    } finally {
        File::delete(NewsVideo::path($news));
    }
});

it('offers the same video action on the article edit page', function () {
    $news = videoNews();

    $this->actingAs($this->admin)
        ->get(route('news.edit', $news))
        ->assertOk()
        ->assertSee('ভিডিও তৈরি')
        ->assertSee(route('news.video.store', $news), false);
});

it('serves the generated video inline for people who can see the article', function () {
    $news = videoNews();

    File::ensureDirectoryExists(dirname(NewsVideo::path($news)));
    File::put(NewsVideo::path($news), 'fake-mp4-bytes');

    try {
        $this->actingAs($this->admin)
            ->get(route('news.video.show', $news))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Disposition', 'inline; filename="'.basename(NewsVideo::path($news)).'"');

        $this->actingAs($this->admin)
            ->get(route('news.video.show', ['news' => $news, 'download' => 1]))
            ->assertOk()
            ->assertDownload(basename(NewsVideo::path($news)));
    } finally {
        File::delete(NewsVideo::path($news));
    }
});

it('returns 404 when the article has no video yet', function () {
    $news = videoNews();

    File::delete(NewsVideo::path($news));

    $this->actingAs($this->admin)
        ->get(route('news.video.show', $news))
        ->assertNotFound();
});

it('keeps video generation and playback behind the panel login', function () {
    $news = videoNews();

    $this->get(route('news.video.show', $news))->assertRedirect(route('login'));
    $this->post(route('news.video.store', $news))->assertRedirect(route('login'));
});

it('refuses videos of other reporters articles', function () {
    $news = videoNews();
    $reporter = User::factory()->reporter()->create();

    $this->actingAs($reporter)
        ->get(route('news.video.show', $news))
        ->assertForbidden();

    $this->actingAs($reporter)
        ->post(route('news.video.store', $news))
        ->assertForbidden();
});
