<?php

use App\Models\Category;
use App\Models\FacebookPost;
use App\Models\News;
use App\Models\User;
use App\Services\NewsWorkflow;
use App\Support\FacebookShare;
use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ফেসবুক অটো-পোস্ট: প্রকাশিত খবরের ফটোকার্ড পেজে শেয়ার, কমেন্টে লিংক।
 * Graph API-এর সব ডাক এখানে ফেক করা হয় — আসল ফেসবুকে কিছু যায় না।
 */
beforeEach(function () {
    $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fb-share-'.Str::lower(Str::random(8));
    $this->app->usePublicPath($this->base.DIRECTORY_SEPARATOR.'public');
    $this->withoutVite();

    // অ্যাডমিন পেজ css/admin.css-এর filemtime নেয় — খালি ফাইলটিই যথেষ্ট।
    File::ensureDirectoryExists(public_path('css'));
    File::put(public_path('css/admin.css'), '');

    $this->admin = User::factory()->admin()->create();
    $this->auto = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo-fb', 'fb_auto' => true]);
    $this->manual = Category::create(['name' => 'মতামত', 'slug' => 'motamot-fb', 'fb_auto' => false]);

    Settings::set(['fb_enabled' => '1']);

    config(['services.facebook' => [
        'page_id' => '1029384756',
        'page_token' => 'page-token',
        'graph_version' => 'v23.0',
    ]]);
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

/** কার্ডের ফাইল বানিয়ে খবরে বসায়, তারপর ফটোকার্ড কেন্দ্রের মতো লাইনটি যাচাই করে। */
function fbCard(News $news): string
{
    $name = 'fb-'.$news->id.'.jpg';

    File::ensureDirectoryExists(public_path('uploads/cards'));
    File::put(public_path('uploads/cards/'.$name), 'fake-jpeg-bytes');

    News::whereKey($news->id)->update(['card_image' => $name]);

    FacebookShare::sync($news->refresh());

    return $name;
}

function fbNews(Category $category, string $slug): News
{
    return News::create([
        'category_id' => $category->id,
        'author_id' => test()->admin->id,
        'title' => 'পরীক্ষার খবর',
        'slug' => $slug,
        'summary' => 'সংক্ষিপ্ত সারাংশ',
        'description' => '<p>বিস্তারিত</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
}

/** ছবি, কমেন্ট ও পেজের ডাক — URL দেখে উত্তর দেয়। */
function fbGraph(?array $photoError = null): void
{
    Http::fake(function (Request $request) use ($photoError) {
        $url = $request->url();

        if (str_ends_with($url, '/photos')) {
            return $photoError !== null
                ? Http::response(['error' => $photoError], 400)
                : Http::response(['id' => 'photo-77', 'post_id' => '1029384756_9001']);
        }

        if (str_ends_with($url, '/comments')) {
            return Http::response(['id' => 'comment-1']);
        }

        return Http::response(['id' => '1029384756', 'name' => 'স্বাধীন আলো']);
    });
}

it('lines up the article the moment it is published', function () {
    $news = News::create([
        'category_id' => $this->auto->id,
        'author_id' => $this->admin->id,
        'title' => 'প্রকাশের অপেক্ষায়',
        'slug' => 'fb-on-transition',
        'description' => '<p>খসড়া অবস্থায়</p>',
        'status' => News::STATUS_DRAFT,
    ]);

    expect(FacebookPost::where('news_id', $news->id)->exists())->toBeFalse();

    NewsWorkflow::transition($news->refresh(), $this->admin, News::STATUS_PUBLISHED);

    $post = FacebookPost::where('news_id', $news->id)->first();

    expect($post)->not->toBeNull()
        ->and($post->status)->toBe(FacebookPost::NEEDS_CARD); // কার্ড এখনো তৈরি হয়নি
});

it('does not line up anything published while the switch is off', function () {
    Settings::set(['fb_enabled' => '0']);

    $news = News::create([
        'category_id' => $this->auto->id,
        'author_id' => $this->admin->id,
        'title' => 'বন্ধ সময়ের খবর',
        'slug' => 'fb-while-off',
        'description' => '<p>বন্ধ থাকার সময়ের খবর</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);

    expect(FacebookPost::where('news_id', $news->id)->exists())->toBeFalse();
});

it('waits for the card and starts as soon as the card is saved', function () {
    $news = fbNews($this->auto, 'fb-card-wait');

    $this->actingAs($this->admin)
        ->post("/admin/news/{$news->id}/card", ['card' => UploadedFile::fake()->image('card.jpg')])
        ->assertSessionHas('success');

    expect(FacebookPost::where('news_id', $news->id)->first()->status)->toBe(FacebookPost::QUEUED);

    // কার্ড মোছা মানে আবার কার্ডের অপেক্ষা।
    $this->actingAs($this->admin)->delete("/admin/news/{$news->id}/card")->assertSessionHas('success');

    expect(FacebookPost::where('news_id', $news->id)->first()->status)->toBe(FacebookPost::NEEDS_CARD);
});

it('posts the card and puts the link in the comment', function () {
    $news = fbNews($this->auto, 'fb-happy-path');
    fbCard($news);

    fbGraph();

    Artisan::call('facebook:publish');

    $post = FacebookPost::where('news_id', $news->id)->first();

    expect($post->status)->toBe(FacebookPost::POSTED)
        ->and($post->photo_id)->toBe('photo-77')
        ->and($post->post_id)->toBe('1029384756_9001')
        ->and($post->permalink)->toBe('https://www.facebook.com/1029384756_9001')
        ->and($post->error)->toBe('')
        ->and($post->message)->toBe("পরীক্ষার খবর\n\nসংক্ষিপ্ত সারাংশ");

    // ছবিটি multipart-এ যায় — সেই ডাকের গায়েই ক্যাপশন ও টোকেন খুঁজতে হয়।
    Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v23.0/1029384756/photos'
        && str_contains($r->body(), 'fake-jpeg-bytes')
        && str_contains($r->body(), $post->message)
        && str_contains($r->body(), 'page-token'));

    Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v23.0/1029384756_9001/comments'
        && $r['message'] === route('frontend.news.show', $news->slug));

    Http::assertSentCount(2);
});

it('keeps an unticked category waiting until an editor presses send', function () {
    $news = fbNews($this->manual, 'fb-needs-approval');
    fbCard($news);

    fbGraph();

    Artisan::call('facebook:publish');

    $post = FacebookPost::where('news_id', $news->id)->first();

    expect($post->status)->toBe(FacebookPost::QUEUED);

    Http::assertNothingSent();

    $this->actingAs($this->admin)->post("/admin/facebook/{$post->id}/send")->assertSessionHas('success');

    expect($post->refresh()->status)->toBe(FacebookPost::POSTED)
        ->and($post->approver->id)->toBe($this->admin->id);

    Http::assertSentCount(2);
});

it('keeps the reason Facebook gave and stops after five tries', function () {
    $news = fbNews($this->auto, 'fb-refused');
    fbCard($news);

    fbGraph(['message' => 'The email address has been disabled', 'type' => 'OAuthException', 'code' => 104]);

    for ($try = 0; $try < 8; $try++) {
        Artisan::call('facebook:publish');
    }

    $post = FacebookPost::where('news_id', $news->id)->first();

    expect($post->status)->toBe(FacebookPost::FAILED)
        ->and($post->error)->toContain('The email address has been disabled')
        ->and($post->attempts)->toBe(FacebookShare::MAX_ATTEMPTS);

    Http::assertSentCount(FacebookShare::MAX_ATTEMPTS);
});

it('never sends the same article to Facebook twice', function () {
    $news = fbNews($this->auto, 'fb-no-duplicate');
    fbCard($news);

    fbGraph();

    Artisan::call('facebook:publish');

    // প্রকাশনা ফেরত দিয়ে আবার প্রকাশ করা হয়; লাইন তো আগে থেকেই আছে।
    News::whereKey($news->id)->update(['status' => News::STATUS_DRAFT]);
    $news->refresh()->forceFill(['status' => News::STATUS_PUBLISHED])->save();

    Artisan::call('facebook:publish');

    $post = FacebookPost::where('news_id', $news->id)->first();

    expect(FacebookPost::where('news_id', $news->id)->count())->toBe(1)
        ->and($post->status)->toBe(FacebookPost::POSTED);

    Http::assertSentCount(2);
});

it('posts again when an editor asks for it a second time', function () {
    $news = fbNews($this->auto, 'fb-resend');
    fbCard($news);

    fbGraph();

    Artisan::call('facebook:publish');

    $post = FacebookPost::where('news_id', $news->id)->first();

    $this->actingAs($this->admin)->post("/admin/facebook/{$post->id}/resend")->assertSessionHas('success');

    expect($post->refresh()->status)->toBe(FacebookPost::POSTED);

    // প্রথম চাল + আবার পাঠানো = ছবি দুইবার, কমেন্ট দুইবার।
    Http::assertSentCount(4);
});

it('reports the page when the token is checked', function () {
    fbGraph();

    $this->actingAs($this->admin)
        ->post('/admin/facebook/verify')
        ->assertSessionHas('success', 'পেজ: স্বাধীন আলো | পোস্ট চালু: হ্যাঁ | অটো পোস্টের ক্যাটাগরি: ১টি');
});

it('warns about a missing token without ever showing it', function () {
    config(['services.facebook.page_token' => '']);

    $html = $this->actingAs($this->admin)->get('/admin/facebook')->assertOk()->getContent();

    expect($html)->toContain('বসানো নেই')
        ->and($html)->not->toContain('page-token');
});

it('saves the templates and the category ticks', function () {
    $this->actingAs($this->admin)->put('/admin/facebook', [
        'fb_enabled' => '1',
        'fb_message' => '{title} — {category} ({date})',
        'fb_comment' => 'বিস্তারিত পড়ুন: {url}',
        'fb_categories' => [$this->manual->id],
    ])->assertSessionHas('success');

    expect(Settings::get('fb_message'))->toBe('{title} — {category} ({date})')
        ->and(Settings::get('fb_comment'))->toBe('বিস্তারিত পড়ুন: {url}')
        ->and((bool) $this->auto->refresh()->fb_auto)->toBeFalse()
        ->and((bool) $this->manual->refresh()->fb_auto)->toBeTrue();
});

it('rejects an empty caption and a tick that is not a category', function () {
    $this->actingAs($this->admin)->put('/admin/facebook', [
        'fb_enabled' => '1',
        'fb_message' => '',
        'fb_comment' => '{url}',
        'fb_categories' => ['not-an-id'],
    ])->assertSessionHasErrors(['fb_message', 'fb_categories.0']);
});

it('shows both tables and keeps reporters out', function () {
    $sent = fbNews($this->auto, 'fb-page-render');
    fbCard($sent);

    $manual = fbNews($this->manual, 'fb-page-render-manual');
    fbCard($manual);

    $waiting = fbNews($this->auto, 'fb-page-render-card');

    // টিক করা ক্যাটাগরির একটি সারি সর্বোচ্চ চেষ্টায় থেমে গেলে লাইনেই দেখা যায়।
    $stuck = fbNews($this->auto, 'fb-page-render-stuck');
    fbCard($stuck);

    fbGraph();
    Artisan::call('facebook:publish');

    FacebookPost::where('news_id', $stuck->id)->update([
        'status' => FacebookPost::FAILED,
        'error' => 'The page has been restricted',
        'attempts' => FacebookShare::MAX_ATTEMPTS,
    ]);

    expect(FacebookPost::where('news_id', $sent->id)->first()->status)->toBe(FacebookPost::POSTED)
        ->and(FacebookPost::where('news_id', $manual->id)->first()->status)->toBe(FacebookPost::QUEUED)
        ->and(FacebookPost::where('news_id', $waiting->id)->first()->status)->toBe(FacebookPost::NEEDS_CARD);

    $html = $this->actingAs($this->admin)->get('/admin/facebook')->assertOk()->getContent();

    expect($html)->toContain('পরীক্ষার খবর')
        ->and($html)->toContain('অপেক্ষমাণ ও ব্যর্থ')
        ->and($html)->toContain('সাম্প্রতিক পোস্ট')
        // টিক করা ক্যাটাগরি নিজে চলে, না করাটি ছাড়পত্রের অপেক্ষায়, কার্ডহীনটি কার্ড বানানোর পথে।
        ->and($html)->toContain('পরের চালে আপনি যাবে')
        ->and($html)->toContain('ছাড়পত্র বাকি')
        ->and($html)->toContain('কার্ড বানান')
        ->and($html)->toContain('The page has been restricted');

    $this->actingAs(User::factory()->reporter()->create())->get('/admin/facebook')->assertForbidden();
});

it('deletes a row but leaves the news and the Facebook post alone', function () {
    $news = fbNews($this->auto, 'fb-row-delete');
    $post = FacebookPost::where('news_id', $news->id)->first();

    $this->actingAs($this->admin)->delete("/admin/facebook/{$post->id}")
        ->assertSessionHas('success', 'সারিটি মোছা হয়েছে; ফেসবুকের পোস্টটি এখান থেকে মোছা হয়নি।');

    expect(FacebookPost::find($post->id))->toBeNull()
        ->and(News::find($news->id))->not->toBeNull();
});

it('says plainly in the log when the switch is off', function () {
    Settings::set(['fb_enabled' => '0']);

    fbGraph();

    expect(Artisan::call('facebook:publish'))->toBe(0)
        ->and(Artisan::output())->toContain('কিছু পাঠানো হয়নি');

    Http::assertNothingSent();
});
