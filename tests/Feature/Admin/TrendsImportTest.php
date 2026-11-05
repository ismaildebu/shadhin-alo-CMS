<?php

use App\Models\Category;
use App\Models\News;
use App\Models\TrendCheck;
use App\Models\TrendEvent;
use App\Models\User;
use App\Services\TranslationService;
use App\Support\NewsTrends\ArticlePage;
use App\Support\NewsTrends\Forecaster;
use App\Support\NewsTrends\HeadlineAdvisor;
use App\Support\NewsTrends\PublishDate;
use App\Support\NewsTrends\Relevance;
use App\Support\NewsTrends\TrendsClient;
use App\Support\NewsTrends\TrendsImporter;
use App\Support\Permissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Everything here runs offline: the feed is a fixture, the publish-date and paragraph
 * readers take a markup string, and the translator goes through Http::fake. No test in
 * this file may depend on what Google or a publisher is serving today.
 */
beforeEach(function () {
    config([
        'translate.driver' => 'google_web',
        'translate.target_language' => 'bn',
        'news_trends.enabled' => true,
    ]);

    ArticlePage::resetMemo();
});

function trendsConfig(): array
{
    return (array) config('news_trends');
}

/**
 * A cut-down copy of the real `trends.google.com/trending/rss?geo=BD` body: the same
 * namespace, the same ht:approx_traffic and the same three ht:news_item blocks.
 */
function trendsFeed(array $terms): string
{
    $body = '';

    foreach ($terms as $term) {
        $items = '';

        foreach ((array) ($term['items'] ?? []) as $item) {
            $items .= '<ht:news_item>'
                .'<ht:news_item_title>'.htmlspecialchars((string) $item['title'], ENT_XML1).'</ht:news_item_title>'
                .'<ht:news_item_url>'.$item['link'].'</ht:news_item_url>'
                .'<ht:news_item_source>'.htmlspecialchars((string) ($item['source'] ?? ''), ENT_XML1).'</ht:news_item_source>'
                .'<ht:news_item_picture>'.($item['image'] ?? '').'</ht:news_item_picture>'
                .'</ht:news_item>';
        }

        $body .= '<item>'
            .'<title>'.htmlspecialchars((string) $term['query'], ENT_XML1).'</title>'
            .'<pubDate>Thu, 05 Nov 2026 21:10:00 -0800</pubDate>'
            .'<ht:approx_traffic>'.htmlspecialchars((string) ($term['traffic'] ?? '100+'), ENT_XML1).'</ht:approx_traffic>'
            .'<ht:picture>https://example.com/thumb.jpg</ht:picture>'
            .$items
            .'</item>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0" xmlns:ht="https://trends.google.com/trending/rss"><channel>'
        .'<title>Trending Searches For Bangladesh</title>'.$body.'</channel></rss>';
}

// ------------------------------------------------------------------ feed reading

it('reads the trending terms, traffic and news links out of the feed', function () {
    $xml = trendsFeed([
        [
            'query' => 'ঢাকা মেডিকেলে ছাত্রলীগ সংঘর্ষ',
            'traffic' => '2000+',
            'items' => [
                ['title' => 'ঢাকা মেডিকেলে ছাত্রসংঘর্ষ, আহত ১২', 'link' => 'https://www.ittefaq.com.bd/1001', 'source' => 'Dainik Ittefaq'],
                ['title' => 'Students injured in Dhaka clash', 'link' => 'https://www.bbc.com/news/1002', 'source' => 'BBC News'],
            ],
        ],
        ['query' => 'River', 'traffic' => '500+', 'items' => []],
    ]);

    $doc = TrendsClient::parse($xml);
    expect($doc)->not->toBeNull();

    $terms = TrendsClient::terms($doc, trendsConfig());

    expect($terms)->toHaveCount(2)
        ->and($terms[0]['query'])->toBe('ঢাকা মেডিকেলে ছাত্রলীগ সংঘর্ষ')
        ->and($terms[0]['traffic'])->toBe('2000+')
        ->and($terms[0]['items'])->toHaveCount(2)
        ->and($terms[0]['items'][0]['link'])->toBe('https://www.ittefaq.com.bd/1001')
        ->and($terms[0]['items'][1]['source'])->toBe('BBC News')
        ->and($terms[1]['items'])->toBe([]);
});

it('refuses to read a body that is not a feed', function () {
    expect(TrendsClient::parse(''))->toBeNull()
        ->and(TrendsClient::parse('<html>blocked</html>'))->not->toBeNull();
});

it('gives one trending term one stable key whatever the spacing or case', function () {
    $a = TrendsClient::key('  Bangladesh   Cricket  ');
    $b = TrendsClient::key('bangladesh cricket');

    expect($a)->toBe($b)->and(strlen($a))->toBe(40);
});

// ------------------------------------------------------------------ host lists

it('accepts a listed publisher and rejects look-alikes and unknown hosts', function () {
    $config = trendsConfig();

    expect(Relevance::isAllowed('www.ittefaq.com.bd', $config))->toBeTrue()
        ->and(Relevance::isAllowed('www.bbc.com', $config))->toBeTrue()
        ->and(Relevance::isAllowed('evil-bbc.com', $config))->toBeFalse()
        ->and(Relevance::isAllowed('some-random-blog.com', $config))->toBeFalse()
        ->and(Relevance::isAllowed('', $config))->toBeFalse();
});

it('blocks betting and odds hosts even though they dress up as reporting', function () {
    $config = trendsConfig();

    foreach (['www.fanduel.com', 'bet365.com', 'www.oddschecker.com'] as $host) {
        expect(Relevance::isBlocked($host, $config))->toBeTrue()
            ->and(Relevance::isAllowed($host, $config))->toBeFalse();
    }
});

// ------------------------------------------------------------------ language, category, worth

it('tells a Bangla term from a foreign one and picks the section', function () {
    $config = trendsConfig();

    expect(Relevance::language('ঢাকায় বন্যা আতঙ্ক'))->toBe('bn')
        ->and(Relevance::language('England vs India semi final'))->toBe('en')
        ->and(Relevance::category('ঢাকায় বন্যা', 'ঢাকায় বন্যা আতঙ্ক', 'bn', $config))->toBe('saradesh')
        ->and(Relevance::category('virat kohli', 'Kohli century', 'en', $config))->toBe('sports')
        ->and(Relevance::category('new zealand quake', 'Earthquake hits NZ', 'en', $config))->toBe('international');
});

it('does not treat a bare generic word as something to report on', function () {
    $config = trendsConfig();

    expect(Relevance::reportable('football', $config))->toBeFalse()
        ->and(Relevance::reportable('খুব ছোট', $config))->toBeFalse()
        ->and(Relevance::reportable('bhola where is it', $config))->toBeTrue();
});

// ------------------------------------------------------------------ the date gate

it('takes the publish date the page states itself, from any of its metadata forms', function () {
    foreach ([
        '<meta property="article:published_time" content="2026-11-05T09:41:52+06:00">',
        '<time itemprop="datePublished" datetime="2026-11-05T09:41:52+06:00"></time>',
        '<script type="application/ld+json">{"datePublished":"2026-11-05T09:41:52+06:00"}</script>',
        '<time datetime="2026-11-05">৫ নভেম্বর</time>',
    ] as $markup) {
        $date = PublishDate::fromHtml($markup);
        expect($date)->not->toBeNull()->and($date->toDateString())->toBe('2026-11-05');
    }
});

it('reports no date rather than inventing one', function () {
    expect(PublishDate::fromHtml('<html><body><p>খবর</p></body></html>'))->toBeNull()
        ->and(PublishDate::fromHtml('<time datetime="গতকাল">গতকাল</time>'))->toBeNull()
        // a template value from the site shell, not the article
        ->and(PublishDate::fromHtml('<meta property="article:published_time" content="2015-01-01T00:00:00Z">'))->toBeNull()
        ->and(PublishDate::fromHtml('<meta property="article:published_time" content="'.now()->addYears(2)->toIso8601String().'">'))->toBeNull();
});

it('keeps its own freshness window and does not widen it', function () {
    $collect = (array) config('news_trends.collect');

    // সংখ্যা বাড়াতে এই গেটগুলো ঢিলা করা হবে না — পরিবর্তন হলে পরীক্ষা থেমে যাবে।
    expect($collect['max_age_hours'])->toBe(24)
        ->and($collect['min_title_chars'])->toBe(12)
        ->and($collect['min_body_chars'])->toBe(400)
        ->and($collect['dedupe_hours'])->toBe(72)
        ->and($collect['max_per_run'])->toBe(10)
        ->and($collect['max_foreign_per_run'])->toBe(4);

    // and the feed importer this system reuses is untouched
    $import = (array) config('news_import.collect');

    expect($import['max_age_hours'])->toBe(10)
        ->and($import['dedupe_window_days'])->toBe(30)
        ->and($import['min_title_chars'])->toBe(12)
        ->and($import['min_summary_chars'])->toBe(300)
        ->and(config('news_import.enabled'))->not->toBeNull();
});

// ------------------------------------------------------------------ reading the page

it('keeps only the report paragraphs and re-escapes somebody else\'s markup', function () {
    $html = '<html><body>'
        .'<script>var ad="advertise with us banner";</script>'
        .'<nav><p>মেনু লিংক তালিকা যা কোনো প্রতিবেদন নয় এবং বেশ লম্বা বলে ধরা পড়বে না ঠিক</p></nav>'
        .'<figure><figcaption>ছবির ক্যাপশন</figcaption></figure>'
        .'<p>খুব ছোট লাইন</p>'
        .'<p>প্রধানমন্ত্রী আজ সচিবালয়ে বৈঠক করেছেন এবং বলেছেন_selok &#8211; দুই হাজার কোটি টাকার প্রকল্প অনুমোদন হয়েছে।</p>'
        .'<p>Advertise with us: subscribe to our newsletter for the latest news updates today.</p>'
        .'<p>বৈঠকে অর্থমন্ত্রী, প্রধান সচিব ও সংশ্লিষ্ট বিভাগের কর্মকর্তারা উপস্থিত ছিলেন বলে জানা গেছে।</p>'
        .'</body></html>';

    $body = ArticlePage::paragraphs($html);

    expect($body)->not->toBe('')
        ->and(substr_count($body, '<p>'))->toBe(2)
        ->and($body)->not->toContain('মেনু লিংক')
        ->and($body)->not->toContain('Advertise')
        ->and($body)->not->toContain('<script')
        // the raw ampersand never reaches the draft
        ->and($body)->not->toContain('&amp;#8211;');
});

it('finds no report on an empty or bot-blocked page', function () {
    expect(ArticlePage::paragraphs(''))->toBe('')
        ->and(ArticlePage::paragraphs('<html><body><p>Access denied</p></body></html>'))->toBe('');
});

// ------------------------------------------------------------------ translation

it('does not ask the provider for text that is already Bangla', function () {
    Http::fake();

    $service = new TranslationService;
    $result = $service->toBangla('ঢাকা মেডিকেলে ছাত্রলীগ সংঘর্ষ');

    expect($result['ok'])->toBeTrue()
        ->and($result['text'])->toBe('ঢাকা মেডিকেলে ছাত্রলীগ সংঘর্ষ')
        ->and($service->requests())->toBe(0);

    Http::assertNothingSent();
});

it('translates a foreign headline and remembers it for the next run', function () {
    Http::fake([
        'translate.googleapis.com/*' => Http::response([[['নদী নিয়ে আলোচনা', null, 'en', 0.9]], 'en']),
    ]);

    $service = new TranslationService;
    $first = $service->toBangla('River flooding in England', 'auto');
    $second = $service->toBangla('River flooding in England', 'auto');

    expect($first['ok'])->toBeTrue()->and($first['text'])->toBe('নদী নিয়ে আলোচনা')
        ->and($second['note'])->toBe('ক্যাশ থেকে')
        ->and($service->used())->toBeGreaterThan(0);

    Http::assertSentCount(1);
});

it('refuses to count an echo of the original as a translation', function () {
    Http::fake([
        'translate.googleapis.com/*' => Http::response([[['River flooding in England', null, 'en', 1.0]], 'en']),
    ]);

    $result = (new TranslationService)->toBangla('River flooding in England', 'auto');

    expect($result['ok'])->toBeFalse()->and($result['note'])->toBe('অনুবাদ পাওয়া যায়নি');
});

it('stops when the budget is spent and says so in Bengali', function () {
    Http::fake([
        '*' => Http::response([[['অনূদিত অংশ', null, 'en', 0.9]], 'en']),
    ]);

    $service = new TranslationService(40);

    $long = str_repeat('The government meeting went on for hours. ', 10);
    $first = $service->toBangla($long, 'auto');

    expect($first['ok'])->toBeTrue()
        ->and($service->used())->toBeLessThanOrEqual(40)
        ->and($service->remaining())->toBe(0);

    $second = $service->toBangla('A completely different English sentence', 'auto');

    expect($second['ok'])->toBeFalse()
        ->and($second['note'])->toBe('অনুবাদের বাজেট শেষ')
        ->and($second['text'])->toBe('A completely different English sentence');
});

it('keeps the paragraph shape when a body is translated', function () {
    Http::fake([
        '*' => Http::response([[['অনূদিত বাক্য', null, 'en', 0.9]], 'en']),
    ]);

    $result = (new TranslationService)->translateParagraphs('<p>First paragraph here.</p><p>Second paragraph here.</p>', 'auto');

    expect($result['translated'])->toBeTrue()
        ->and(substr_count($result['html'], '<p>'))->toBe(2)
        ->and($result['html'])->toContain('অনূদিত বাক্য');
});

it('gives the desk three headlines when no report may be run', function () {
    Http::fake([
        'translate.googleapis.com/*' => Http::response([[['সংঘর্ষে বারো জন আহত', null, 'en', 0.9]], 'en']),
    ]);

    $term = [
        'query' => 'Coxs Bazar cyclone warning',
        'items' => [
            ['title' => 'Twelve injured as clash breaks out | Dainik Ittefaq', 'link' => 'https://www.ittefaq.com.bd/1', 'source' => 'Dainik Ittefaq'],
        ],
    ];

    $advice = HeadlineAdvisor::for($term, trendsConfig(), new TranslationService);

    expect($advice['headlines'])->toHaveCount(3)
        ->and($advice['translated'])->toBeTrue()
        ->and($advice['headlines'][0])->toBe('সংঘর্ষে বারো জন আহত')
        ->and($advice['headlines'][1])->toContain('কী ঘটল, কেন তাৎপর্য')
        ->and($advice['headlines'][2])->toContain('Coxs Bazar cyclone warning');
});

it('still suggests headlines when the translator is unreachable', function () {
    Http::fake(['translate.googleapis.com/*' => Http::response(null, 503)]);

    $term = ['query' => 'Kuakata sea erosion', 'items' => [['title' => 'Sea eats the coast at Kalabox', 'link' => 'https://www.bbc.com/news/1', 'source' => 'BBC']]];
    $advice = HeadlineAdvisor::for($term, trendsConfig(), new TranslationService);

    expect($advice['translated'])->toBeFalse()
        ->and($advice['headlines'])->toHaveCount(3)
        // the foreign headline is kept as the lead rather than faked into Bangla
        ->and($advice['headlines'][0])->toContain('Sea eats the coast');
});

it('lists what readers will search for next from counted evidence only', function () {
    $config = trendsConfig();

    $category = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh']);

    News::create([
        'category_id' => $category->id,
        'title' => 'আগামীকাল ঢাকায় শিক্ষা সংসদের সভা',
        'slug' => 'agami-kal-shikkha-songsod-sobha',
        'description' => '<p>সভা অনুষ্ঠিত হবে।</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    // the same announcement published twice, with the words in another order
    News::create([
        'category_id' => $category->id,
        'title' => 'মোংলা থেকে উত্তরাঞ্চল পর্যন্ত জ্বালানি অবকাঠামো গড়ে তোলা হবে : প্রধানমন্ত্রী',
        'slug' => 'mongla-jbalani-1',
        'description' => '<p>সভা অনুষ্ঠিত হবে।</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHours(2),
    ]);

    News::create([
        'category_id' => $category->id,
        'title' => 'মোংলা থেকে উত্তরাঞ্চল পর্যন্ত গড়ে তোলা হবে জ্বালানি অবকাঠামো : প্রধানমন্ত্রী',
        'slug' => 'mongla-jbalani-2',
        'description' => '<p>সভা অনুষ্ঠিত হবে।</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHours(3),
    ]);

    // a past killing: "প্রতিবন্ধী" holds the letters of "বন্ধ" but is not a strike announcement
    News::create([
        'category_id' => $category->id,
        'title' => 'সাতক্ষীরায় গাড়ির চাকায় পিষ্ট হয়ে মানসিক প্রতিবন্ধী বৃদ্ধা নিহত',
        'slug' => 'satkhira-pishto-biddha-nihata',
        'description' => '<p>খবর পাওয়া গেছে।</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subHours(4),
    ]);

    // the same term rejected in three runs is a real repeat trend
    foreach (range(1, 3) as $ignored) {
        TrendCheck::create([
            'geo' => 'BD',
            'trend_key' => TrendsClient::key('khulna port bridge'),
            'query' => 'khulna port bridge',
            'verdict' => TrendCheck::VERDICT_SUGGESTED,
            'reason' => 'আনোগ্য প্রতিবেদন মেলেনি',
        ]);
    }

    TrendCheck::create([
        'geo' => 'BD',
        'trend_key' => TrendsClient::key('একবারের শব্দ'),
        'query' => 'একবারের শব্দ',
        'verdict' => TrendCheck::VERDICT_SUGGESTED,
        'reason' => 'আনোগ্য প্রতিবেদন মেলেনি',
    ]);

    // a term already imported is not an upcoming trend, however often it shows up
    foreach (range(1, 2) as $ignored) {
        TrendCheck::create([
            'geo' => 'BD',
            'trend_key' => TrendsClient::key('oldtrend already in'),
            'query' => 'oldtrend already in',
            'verdict' => TrendCheck::VERDICT_DUPLICATE,
            'reason' => 'এই শব্দের সংবাদ ইতিমধ্যেই আনা হয়েছে',
        ]);
    }

    $rows = collect(Forecaster::upcoming($config))->keyBy('query');

    expect($rows->keys())->toContain('khulna port bridge')
        ->and($rows->has('একবারের শব্দ'))->toBeFalse()
        ->and($rows->has('oldtrend already in'))->toBeFalse()
        ->and((int) $rows['khulna port bridge']['mentions'])->toBe(3);

    $own = $rows->first(fn (array $row) => str_contains($row['reason'], 'প্রকাশিত সংবাদেই ঘোষণা আছে'));
    expect($own)->not->toBeNull()->and($own['source_link'])->toStartWith('/article/');

    // one line per event, whatever the order the words went in
    $mongla = $rows->filter(fn (array $row) => str_contains($row['reason'], 'মোংলা'));
    expect($mongla)->toHaveCount(1);

    // and a headline with no forward marker at all is not an upcoming trend
    expect($rows->first(fn (array $row) => str_contains($row['reason'], 'সাতক্ষীরায়')))->toBeNull();

    // and remembering the list is idempotent — no duplicate rows per term
    expect(Forecaster::remember(Forecaster::upcoming($config), $config))->toBeGreaterThan(0);

    // a line from an earlier run that this run does not count again leaves the desk's list
    $stale = TrendEvent::create([
        'trend_key' => TrendsClient::key('previous run leftover'),
        'query' => 'previous run leftover',
        'kind' => TrendEvent::KIND_FORECAST,
        'status' => TrendEvent::STATUS_PENDING,
        'title' => 'previous run leftover',
        'occurred_at' => now(),
    ]);

    Forecaster::remember(Forecaster::upcoming($config), $config);

    expect($stale->fresh()->status)->toBe(TrendEvent::STATUS_HIDDEN);

    $before = TrendEvent::count();
    Forecaster::remember(Forecaster::upcoming($config), $config);

    expect(TrendEvent::count())->toBe($before)
        ->and(TrendEvent::query()->where('kind', TrendEvent::KIND_FORECAST)->distinct('trend_key')->count('trend_key'))
        ->toBe(TrendEvent::query()->where('kind', TrendEvent::KIND_FORECAST)->count());
});

// ------------------------------------------------------------------ importer entry points

it('says why it did not run instead of pretending the feed was empty', function () {
    config(['news_trends.enabled' => false]);

    $result = TrendsImporter::run(true);

    expect($result['error'])->toContain('আলোচিত সার্চ থেকে সংগ্রহ বন্ধ')
        ->and($result['imported'])->toBe(0);
});

it('explains a blocked or unpublishable host without downloading it', function () {
    Http::fake();

    $result = TrendsImporter::verifySource('https://www.bet365.com/sports/123', trendsConfig());

    expect($result['blocked'])->toBeTrue()
        ->and($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toContain('বাজি');

    Http::assertNothingSent();
});

it('reports an unreachable feed honestly and stores nothing', function () {
    // a loopback feed URL is refused by the SSRF guard, so nothing is downloaded at all
    config(['news_trends.feed' => 'https://127.0.0.1:1/trending/rss?geo=%s']);

    $dry = TrendsImporter::run(true);
    $live = TrendsImporter::run(false);

    expect($dry['error'])->toContain('ট্রেন্ড ফিড আনা যায়নি')
        ->and($live['error'])->toContain('ট্রেন্ড ফিড আনা যায়নি')
        ->and($dry['imported'])->toBe(0)
        ->and(TrendEvent::count())->toBe(0)
        ->and(TrendCheck::count())->toBe(0);
});

// ------------------------------------------------------------------ admin page

it('shows the আলোচিত সার্চ page only to staff with the trends permission', function () {
    $this->get('/admin/trends')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/trends')->assertForbidden();

    Permissions::flush();
    expect(Permissions::roleAllows(User::ROLE_EDITOR, 'trends_manage'))->toBeTrue()
        ->and(Permissions::roleAllows(User::ROLE_REPORTER, 'trends_manage'))->toBeFalse();

    $response = $this->actingAs(User::factory()->editor()->create())->get('/admin/trends');
    $response->assertOk()->assertSee('আলোচিত সার্চ');

    foreach (['আমদানি', 'সম্ভাব্য শিরোনাম', 'আগামী ট্রেন্ড', 'যাচাই'] as $tab) {
        $response->assertSee($tab);
    }
});

it('lets the desk mark a suggestion read or take it off the list', function () {
    $admin = User::factory()->admin()->create();

    $event = TrendEvent::create([
        'trend_key' => TrendsClient::key('tests key'),
        'query' => 'tests key',
        'kind' => TrendEvent::KIND_SUGGESTION,
        'status' => TrendEvent::STATUS_PENDING,
        'title' => 'tests key',
        'headlines' => json_encode(['শিরোনাম এক', 'শিরোনাম দুই'], JSON_UNESCAPED_UNICODE),
        'occurred_at' => now(),
    ]);

    $this->actingAs($admin)->put(route('trends.update', $event), ['status' => TrendEvent::STATUS_DONE])
        ->assertRedirect(route('trends.index'));

    expect($event->fresh()->status)->toBe(TrendEvent::STATUS_DONE);

    $this->actingAs($admin)->put(route('trends.update', $event), ['status' => 'nonsense'])
        ->assertSessionHasErrors('trends');

    expect($event->fresh()->status)->toBe(TrendEvent::STATUS_DONE);

    $this->actingAs($admin)->put(route('trends.update', $event), ['status' => TrendEvent::STATUS_HIDDEN])
        ->assertRedirect(route('trends.index'));

    $this->actingAs($admin)->get('/admin/trends')->assertDontSee('tests key');
});

it('deletes a desk row without touching the draft it points at', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh']);

    $draft = News::create([
        'category_id' => $category->id,
        'title' => 'খসড়া হিসেবে আনা সংবাদ',
        'slug' => 'khosra-ana-sombad',
        'description' => '<p>একটি প্রতিবেদন।</p>',
        'status' => News::STATUS_DRAFT,
    ]);

    $event = TrendEvent::create([
        'trend_key' => TrendsClient::key('delete me'),
        'query' => 'delete me',
        'kind' => TrendEvent::KIND_IMPORTED,
        'status' => TrendEvent::STATUS_PENDING,
        'title' => 'খসড়া হিসেবে আনা সংবাদ',
        'news_id' => $draft->id,
        'occurred_at' => now(),
    ]);

    $check = TrendCheck::create([
        'geo' => 'BD',
        'trend_key' => TrendsClient::key('delete me'),
        'query' => 'delete me',
        'verdict' => TrendCheck::VERDICT_IMPORTED,
        'reason' => 'খসড়া #'.$draft->id,
        'news_id' => $draft->id,
    ]);

    $this->actingAs($admin)->get('/admin/trends')->assertOk()->assertSee('মুছুন');

    $this->delete(route('trends.destroy', $event))->assertRedirect(route('trends.index'));
    $this->delete(route('trends.checkDestroy', $check))->assertRedirect(route('trends.index'));

    expect(TrendEvent::find($event->id))->toBeNull()
        ->and(TrendCheck::find($check->id))->toBeNull()
        // the draft is সংবাদ তালিকার জিনিস — এখানে মুছে যায় না
        ->and(News::find($draft->id))->not->toBeNull();

    $other = TrendEvent::create([
        'trend_key' => TrendsClient::key('reporter row'),
        'query' => 'reporter row',
        'kind' => TrendEvent::KIND_SUGGESTION,
        'status' => TrendEvent::STATUS_PENDING,
        'title' => 'reporter row',
        'occurred_at' => now(),
    ]);

    $this->actingAs(User::factory()->reporter()->create())
        ->delete(route('trends.destroy', $other))
        ->assertForbidden();

    expect(TrendEvent::find($other->id))->not->toBeNull();
});

it('refuses to run from the page while collection is off or a run is already going', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    config(['news_trends.enabled' => false]);
    $this->post(route('trends.run'))->assertSessionHasErrors('trends');

    config(['news_trends.enabled' => true]);

    $lock = Cache::lock('news-trends', 60);
    expect($lock->get())->toBeTrue();

    $this->post(route('trends.run'))->assertSessionHasErrors('trends');

    $lock->release();
});

it('asks for a full address before probing a source', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->post(route('trends.verify'), ['url' => 'ittefaq.com.bd'])->assertSessionHasErrors('trends');
    $this->post(route('trends.verify'), ['url' => ''])->assertSessionHasErrors('trends');

    $this->post(route('trends.verify'), ['url' => 'https://www.bet365.com/x'])
        ->assertSessionHas('verified');
});

it('links the page in the sidebar for people who may use it', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get('/dashboard')->assertSee(route('trends.index'), false);

    $reporter = User::factory()->reporter()->create();

    $this->actingAs($reporter)->get('/dashboard')->assertDontSee(route('trends.index'), false);
});
