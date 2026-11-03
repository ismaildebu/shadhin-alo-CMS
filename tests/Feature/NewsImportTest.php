<?php

use App\Models\Category;
use App\Models\Media;
use App\Models\News;
use App\Models\NewsImport;
use App\Support\NewsImport\FeedReader;
use App\Support\NewsImport\LocationMatcher;
use App\Support\NewsImport\NewsImporter;
use Database\Seeders\CategorySeeder;

beforeEach(function () {
    Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh']);
    Category::create(['name' => 'অন্যান্য', 'slug' => 'others']);

    NewsImporter::resetMemo();
    LocationMatcher::reset();
});

function importFeedXml(array $items): string
{
    $body = '';

    foreach ($items as $item) {
        $tags = $item['categories'] ?? [$item['category'] ?? ''];
        $categories = '';

        foreach ($tags as $tag) {
            $categories .= '<category>'.htmlspecialchars((string) $tag, ENT_XML1).'</category>';
        }

        $body .= '<item>'
            .'<title>'.htmlspecialchars($item['title'], ENT_XML1).'</title>'
            .'<link>'.$item['link'].'</link>'
            .$categories
            .'<pubDate>'.$item['date'].'</pubDate>'
            .'<description>'.htmlspecialchars($item['description'] ?? '', ENT_XML1).'</description>'
            .(isset($item['image']) ? '<media:content url="'.$item['image'].'" type="image/jpeg"/>' : '')
            .'</item>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel>'
        .'<title>পরীক্ষার ফিড</title>'.$body.'</channel></rss>';
}

test('প্রতিটি সোর্সের কনফিগে ফিড, হোস্ট, বিভাগ ও ছবির নিয়ম আছে', function () {
    $sources = (array) config('news_import.sources');

    expect($sources)->toHaveCount(9);

    foreach ($sources as $key => $source) {
        $feeds = (array) ($source['feeds'] ?? []);

        if ($feeds === []) {
            expect($source['feed'])->toStartWith('https://');
        }

        foreach ($feeds as $feed) {
            expect($feed['feed'])->toStartWith('https://');
            expect($feed['category'])->toMatch('/^[A-Za-z][A-Za-z0-9-]*$/');
        }

        expect($source['allowed_hosts'])->not->toBeEmpty();
        expect(['tag', 'url'])->toContain($source['category_from']);
        expect($source['default_category'])->toMatch('/^[A-Za-z][A-Za-z0-9-]*$/');

        foreach (array_keys($source['category_map']) as $from) {
            expect((string) $from)->not->toBe('');
        }

        foreach (array_values($source['category_map']) as $slug) {
            expect($slug)->toMatch('/^[A-Za-z][A-Za-z0-9-]*$/');
        }

        foreach ($source['image']['from'] as $kind) {
            expect(['media_content', 'media_thumbnail', 'enclosure'])->toContain($kind);
        }

        expect($source)->toHaveKey('content');
        expect($source['content'])->toHaveKeys(['class', 'exclude']);
    }
});

test('ইমপোর্টের সব ক্যাটাগরি slug সিডারের সাথে মেলে', function () {
    $known = array_values(CategorySeeder::CATEGORIES);
    $used = [];

    foreach ((array) config('news_import.sources') as $source) {
        $used[] = (string) $source['default_category'];
        $used = array_merge($used, array_values($source['category_map']));

        foreach ((array) ($source['feeds'] ?? []) as $feed) {
            $used[] = (string) $feed['category'];
        }
    }

    expect(array_values(array_diff(array_unique($used), $known)))->toBe([]);
});

test('ইমপোর্টের দলগুলো সাইটের সব ক্যাটাগরি একবারেই কভার করে', function () {
    $groups = (array) config('news_import.groups');
    $covered = [];

    foreach ($groups as $group) {
        expect((string) $group['label'])->not->toBe('');
        $covered = array_merge($covered, array_map('strval', (array) $group['categories']));
    }

    expect($groups)->toHaveCount(7)
        ->and(array_unique($covered))->toHaveCount(count(CategorySeeder::CATEGORIES))
        ->and(array_values(array_diff(array_unique($covered), array_values(CategorySeeder::CATEGORIES))))->toBe([])
        ->and(array_values(array_diff(array_values(CategorySeeder::CATEGORIES), array_unique($covered))))->toBe([]);

    // সারাদেশ কেবল খুলনা-ফিল্টার করা দলে থাকলে অন্য জেলার সংবাদ কোনোমতেই আসে না।
    expect(collect($groups)->contains(
        fn (array $group) => in_array('saradesh', (array) $group['categories'], true)
            && empty($group['division'])
    ))->toBeTrue();
});

test('সংবাদ পেজ থেকে পূর্ণাঙ্গ প্রতিবেদন বের হয়', function () {
    $html = '<html><body>'
        .'<div class="story-head"><h1>শিরোনাম</h1><p>এই অনুচ্ছেদ বাদ যাবে</p></div>'
        .'<div class="story-content">'
        .'<h2>উপশিরোনাম</h2>'
        .'<p>প্রথম অনুচ্ছেদ। <a href="/bangladesh/more">বিস্তারিত</a></p>'
        .'<script>alert(1)</script>'
        .'<blockquote><p>উদ্ধৃতি</p></blockquote>'
        .'<ul><li>এক</li><li>দুই</li></ul>'
        .'<div class="story-element story-element-text-also-read"><h2>আরও পড়ুন</h2></div>'
        .'<p class="print-tags">ট্যাগ</p>'
        .'</div></body></html>';

    $source = ['content' => ['class' => 'story-content', 'exclude' => ['print-tags', 'story-element-text-also-read']]];
    $content = FeedReader::articleContent($html, $source, 30_000, 'https://www.prothomalo.com/bangladesh/story');

    expect($content)->toContain('প্রথম অনুচ্ছেদ');
    expect($content)->toContain('<h2>উপশিরোনাম</h2>');
    expect($content)->toContain('উদ্ধৃতি');
    expect($content)->toContain('<li>এক</li>');
    expect($content)->toContain('href="https://www.prothomalo.com/bangladesh/more"');
    expect($content)->not->toContain('এই অনুচ্ছেদ বাদ যাবে');
    expect($content)->not->toContain('আরও পড়ুন');
    expect($content)->not->toContain('ট্যাগ');
    expect($content)->not->toContain('<script>');
});

test('id-তে কনটেইনার, div অনুচ্ছেদ ও "আরও পড়ুন" বাক্স—সময়ের আলোর মতো পেজ থেকে বডি বের হয়', function () {
    $html = '<html><body>'
        .'<div id="side">কনটেইনারের বাইরের লেখা</div>'
        .'<div id="f" style="font-size: 20px">'
        .'<img class="mood" src="https://www.shomoyeralo.com/2026/11/03/sa_1.jpg">'
        .'<figcaption class="figcaption">ছবি : সংগৃহীত</figcaption>'
        .'<div style=""><span>প্রথম&#x200E; অনুচ্ছেদ।</span></div>'
        .'<div style=""><span>&#x200E;</span></div>'
        .'<div style=""><span>দ্বিতীয় অনুচ্ছেদ।</span><div style=""><span>তৃতীয় অনুচ্ছেদ।</span></div></div>'
        .'<div style=""><div>আরও পড়ুন</div><div style=""><a class="also" href="/news/101">সম্পর্কিত শিরোনাম</a></div></div>'
        .'<div style=""><div id="ADL1"><script>serveAdvertisement()</script></div></div>'
        .'<div style=""><span>শেষ অনুচ্ছেদ।</span></div>'
        .'</div></body></html>';

    $source = ['content' => [
        'class' => '#f',
        'blocks' => ['div'],
        'exclude' => ['xpath:.//div[.//a[contains(concat(" ",normalize-space(@class)," ")," also ")]]'],
    ]];
    $content = FeedReader::articleContent($html, $source, 30_000, 'https://www.shomoyeralo.com/news/393411');

    // div অনুচ্ছেদ স্যানিটাইজারে খোলা লাইন হয়ে যায়, তাই সেগুলো <p> হয়ে বের হয়
    expect($content)->toContain('<p>প্রথম অনুচ্ছেদ।</p>'); // দিকনির্দেশক চিহ্ন (&#x200E;) বডিতে থাকে না
    expect($content)->not->toMatch('/<p>\s*<\/p>/'); // শুধু সেই চিহ্ন নিয়ে থাকা div ফাঁকা অনুচ্ছেদ হয় না
    expect($content)->toContain('দ্বিতীয় অনুচ্ছেদ');
    expect($content)->toContain('শেষ অনুচ্ছেদ');
    expect(substr_count($content, 'তৃতীয় অনুচ্ছেদ'))->toBe(1);
    expect($content)->not->toContain('কনটেইনারের বাইরের লেখা');
    expect($content)->not->toContain('সম্পর্কিত শিরোনাম');
    expect($content)->not->toContain('আরও পড়ুন');
    expect($content)->not->toContain('ছবি : সংগৃহীত');
    expect($content)->not->toContain('<script>');
    expect($content)->not->toContain('<div');
});

test('ফিডে বিভাগের ট্যাগ না থাকলে feeds-এর ক্যাটাগরি খসড়ায় বসে', function () {
    $config = (array) config('news_import');
    $config['text']['full_article'] = false;
    $now = gmdate('D, d M Y H:i:s').' GMT';

    // FeedReader::collect() প্রতিটি বিভাগ-ফিডের জন্য সোর্সের ক্যাটাগরি এই রূপে বসিয়ে দেয়।
    $source = array_merge((array) $config['sources']['shomoyeralo'], [
        'category_map' => [],
        'default_category' => 'sports',
    ]);

    $items = FeedReader::parse(
        'shomoyeralo',
        $source,
        importFeedXml([['title' => 'বুড়িগঙ্গায় জোয়ারের আগে নৌকার দৌড়', 'link' => 'https://www.shomoyeralo.com/news/393349', 'date' => $now]]),
        $config,
        [],
        3
    );

    expect($items[0]['category'])->toBe('sports')
        ->and($items[0]['categories'])->toBe(['sports'])
        ->and($items[0]['tags'])->toBe([]);
});

test('ফিডের </rss> এর পর লেগে থাকা স্ক্রিপ্ট সংবাদ পড়াতে বাধা দেয় না', function () {
    $config = (array) config('news_import');
    $config['text']['full_article'] = false;
    $source = (array) $config['sources']['shomoyeralo'];

    // সময়ের আলো ফিডটি text/html হিসেবে পাঠায় ও </rss> এর পরে ট্র্যাকিং স্ক্রিপ্ট জোড়ে।
    $xml = importFeedXml([
        ['title' => 'খুলনায় বন্যার সতর্কতা জারি করা হয়েছে', 'link' => 'https://www.shomoyeralo.com/news/393403', 'date' => gmdate('D, d M Y H:i:s').' GMT'],
    ]).'<script>window.__cf_beacon()</script>';

    $items = FeedReader::parse('shomoyeralo', $source, $xml, $config, [], 3);

    expect($items)->toHaveCount(1)
        ->and($items[0]['state'])->toBe('new')
        ->and($items[0]['link'])->toBe('https://www.shomoyeralo.com/news/393403');
});

test('বডি কনটেইনার না পেলে খালি প্রতিবেদন ফেরে', function () {
    $content = FeedReader::articleContent(
        '<html><body><div class="other">কিছু লেখা</div></body></html>',
        ['content' => ['class' => 'story-content']],
        30_000,
        'https://www.prothomalo.com/x'
    );

    expect($content)->toBe('');
});

test('ফিড পড়ে নতুন, ডুপ্লিকেট, পুরোনো ও বাইরের লিংক আলাদা করে', function () {
    $config = (array) config('news_import');
    $config['text']['full_article'] = false; // এই পরীক্ষায় শুধু অবস্থা দেখা হয়
    $source = $config['sources']['prothomalo'];
    $now = gmdate('D, d M Y H:i:s').' GMT';
    $old = gmdate('D, d M Y H:i:s', time() - 5 * 86400).' GMT';

    $xml = importFeedXml([
        [
            'title' => 'জেলায় নতুন সেতু চালু হলো',
            'link' => 'https://www.prothomalo.com/bangladesh/bridge',
            'category' => 'জেলা',
            'date' => $now,
            'description' => 'সেতুটি আজ চালু হয়েছে।',
            'image' => 'https://www.prothomalo.com/images/bridge.jpg',
        ],
        [
            'title' => 'আগেই থাকা খবরের শিরোনাম!',
            'link' => 'https://www.prothomalo.com/bangladesh/old-one',
            'category' => 'বাংলাদেশ',
            'date' => $now,
            'description' => 'পুরোনো খবর।',
        ],
        [
            'title' => 'অন্য সাইটের একটি সংবাদ শিরোনাম',
            'link' => 'https://other.example.com/story',
            'category' => 'বাংলাদেশ',
            'date' => $now,
            'description' => 'বাইরের সাইট।',
        ],
        [
            'title' => 'পাঁচ দিন আগের একটি সংবাদ',
            'link' => 'https://www.prothomalo.com/bangladesh/five-days',
            'category' => 'বাংলাদেশ',
            'date' => $old,
            'description' => 'পুরোনো।',
        ],
        [
            'title' => 'খবর',
            'link' => 'https://www.prothomalo.com/bangladesh/short',
            'category' => 'বাংলাদেশ',
            'date' => $now,
            'description' => 'খুব ছোট শিরোনাম।',
        ],
        [
            'title' => 'হাসির একটি লেখা যা সংবাদ নয়',
            'link' => 'https://www.prothomalo.com/ros-alo/funny',
            'category' => 'রস+আলো',
            'date' => $now,
            'description' => 'হাস্যরস।',
        ],
    ]);

    $known = [FeedReader::titleKey('আগেই থাকা খবরের শিরোনাম') => true];
    $items = FeedReader::parse('prothomalo', $source, $xml, $config, $known, 20);

    expect($items)->toHaveCount(6);

    $new = collect($items)->firstWhere('state', 'new');
    expect($new['title'])->toBe('জেলায় নতুন সেতু চালু হলো');
    expect($new['category'])->toBe('saradesh');
    expect($new['excerpt'])->toBe('সেতুটি আজ চালু হয়েছে।');
    expect($new['image'])->toBe('https://www.prothomalo.com/images/bridge.jpg');
    expect($new['published_at'])->not->toBeNull();

    expect(collect($items)->where('state', 'duplicate')->pluck('title')->all())
        ->toHaveCount(1);
    expect(collect($items)->firstWhere('state', 'old')['title'])->toBe('পাঁচ দিন আগের একটি সংবাদ');
    expect(collect($items)->where('state', 'skipped')->pluck('reason')->all())
        ->toBe(['অন্য সাইট', 'শিরোনাম খুব ছোট', 'বাদ দেওয়া বিভাগ']);
});

test('পূর্ণ প্রতিবেদন বা যথেষ্ট বড় সারাংশ না এলে দুই লাইনের খসড়া হয় না', function () {
    $config = (array) config('news_import');
    $config['text']['og_lookup'] = false;
    $config['images']['og_lookup'] = false;
    $source = $config['sources']['prothomalo'];
    $source['content']['class'] = null; // সংবাদ পেজ খোলা যায় না
    $now = gmdate('D, d M Y H:i:s').' GMT';

    $xml = importFeedXml([
        [
            'title' => 'শিরোনামের কপিই সারাংশ',
            'link' => 'https://www.prothomalo.com/bangladesh/headline-copy',
            'category' => 'বাংলাদেশ',
            'date' => $now,
            'description' => 'শিরোনামের কপিই সারাংশ',
        ],
        [
            'title' => 'দুই লাইন সারাংশের একটি সংবাদ',
            'link' => 'https://www.prothomalo.com/bangladesh/short-summary',
            'category' => 'বাংলাদেশ',
            'date' => $now,
            'description' => 'শ্রমিকরা আজ সকাল থেকে বিক্ষোভ করছেন এবং পুলিশ ঘটনাস্থলে গেছে।',
            'image' => 'https://www.prothomalo.com/images/short.jpg',
        ],
        [
            'title' => 'যথেষ্ট বড় সারাংশের একটি সংবাদ',
            'link' => 'https://www.prothomalo.com/bangladesh/long-summary',
            'category' => 'বাংলাদেশ',
            'date' => $now,
            'description' => str_repeat('বিস্তারিত প্রতিবেদনের সারাংশ এখানে আছে। ', 20),
        ],
    ]);

    $items = collect(FeedReader::parse('prothomalo', $source, $xml, $config, [], 10))->keyBy('title');

    expect($items['শিরোনামের কপিই সারাংশ']['state'])->toBe('skipped')
        ->and($items['শিরোনামের কপিই সারাংশ']['reason'])->toBe('পূর্ণ প্রতিবেদন পাওয়া যায়নি')
        ->and($items['দুই লাইন সারাংশের একটি সংবাদ']['state'])->toBe('skipped')
        ->and($items['যথেষ্ট বড় সারাংশের একটি সংবাদ']['state'])->toBe('new');
});

test('নির্বাচিত সংবাদ খসড়া হিসেবে সংরক্ষিত হয়, একই সংবাদ আবার আসে না', function () {
    $item = [
        'source' => 'prothomalo',
        'title' => 'নতুন সেতু উদ্বোধন করা হয়েছে',
        'link' => 'https://www.prothomalo.com/bangladesh/new-bridge',
        'category' => 'jatiyo',
        'category_tag' => 'বাংলাদেশ',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'আজ সকালে সেতুটি উদ্বোধন করা হয়। <script>alert(1)</script>',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ];

    $result = NewsImporter::save($item);

    expect($result['state'])->toBe('imported');
    expect($result['news_id'])->toBeInt();

    $news = News::firstOrFail();
    expect($news->status)->toBe(News::STATUS_DRAFT);
    expect($news->summary)->toContain('সেতুটি উদ্বোধন');
    expect($news->description)->toContain('সূত্র:');
    expect($news->description)->toContain($item['link']);
    expect($news->description)->not->toContain('<script>');
    expect($news->categories->pluck('slug')->all())->toBe(['jatiyo']);
    expect(NewsImport::count())->toBe(1);

    $again = NewsImporter::save($item);
    expect($again['state'])->toBe('duplicate');
    expect(News::count())->toBe(1);
});

test('পূর্ণ প্রতিবেদন থাকলে খসড়ার বডিতে সেটিই বসে', function () {
    $item = [
        'source' => 'samakal',
        'title' => 'পূর্ণ প্রতিবেদনসহ একটি সংবাদ',
        'link' => 'https://samakal.com/bangladesh/full-story',
        'category' => 'jayato',
        'category_tag' => 'bangladesh',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সংক্ষিপ্ত সারাংশ।',
        'content' => '<p>প্রথম অনুচ্ছেদের বিস্তারিত।</p><h2>উপশিরোনাম</h2><p>দ্বিতীয় অনুচ্ছেদের বিস্তারিত।</p>',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ];

    expect(NewsImporter::save($item)['state'])->toBe('imported');

    $news = News::firstOrFail();
    expect($news->description)
        ->toContain('প্রথম অনুচ্ছেদের বিস্তারিত')
        ->toContain('<h2>উপশিরোনাম</h2>')
        ->toContain('দ্বিতীয় অনুচ্ছেদের বিস্তারিত')
        ->toContain('সূত্র:');
    expect($news->summary)->toBe('সংক্ষিপ্ত সারাংশ।');
});

test('একই শিরোনাম অন্য লিংকে এলেও দ্বিতীয়বার যোগ হয় না', function () {
    $base = [
        'source' => 'samakal',
        'title' => 'একই শিরোনামের দুটি ভিন্ন লিংক',
        'category' => 'others',
        'category_tag' => '',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সংক্ষিপ্ত অংশ।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ];

    expect(NewsImporter::save($base + ['link' => 'https://samakal.com/a'] )['state'])->toBe('imported');
    expect(NewsImporter::save($base + ['link' => 'https://samakal.com/b'] )['state'])->toBe('duplicate');
    expect(News::count())->toBe(1);
});

test('অজানা বা মুছে ফেলা ক্যাটাগরি হলে অন্যান্যে যায়', function () {
    $result = NewsImporter::save([
        'source' => 'jagonews24',
        'title' => 'অজানা বিভাগের একটি সংবাদ',
        'link' => 'https://www.jagonews24.com/misc/story',
        'category' => 'unknown-slug',
        'category_tag' => '',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সংক্ষিপ্ত অংশ।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    expect($result['state'])->toBe('imported');
    expect(News::firstOrFail()->categories->pluck('slug')->all())->toBe(['others']);
});

test('Google News সাইটম্যাপ থেকেও শিরোনাম, লিংক, তারিখ ও ছবি পড়া হয়', function () {
    $config = (array) config('news_import');
    $config['text']['og_lookup'] = false; // নেটওয়ার্ক ছাড়া পরীক্ষা
    $config['text']['full_article'] = false;
    $config['images']['og_lookup'] = false;
    $source = $config['sources']['bdnews24'];
    $source['content']['class'] = null;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
        .' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"'
        .' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'
        .'<url><loc>https://bangla.bdnews24.com/bangladesh/abc123</loc>'
        .'<image:image><image:loc>https://media.example.com/a.jpg</image:loc></image:image>'
        .'<news:news><news:publication><news:name>বিডিনিউজ</news:name><news:language>bn</news:language></news:publication>'
        .'<news:publication_date>'.now()->toIso8601String().'</news:publication_date>'
        .'<news:title><![CDATA[সাভারে নতুন সেতু চালু হলো]]></news:title></news:news></url>'
        .'<url><loc>https://other.example.com/x</loc>'
        .'<news:news><news:title>অন্য সাইটের সংবাদ</news:title></news:news></url>'
        .'</urlset>';

    $items = FeedReader::parse('bdnews24', $source, $xml, $config, [], 10);

    expect($items)->toHaveCount(2);
    expect($items[0]['title'])->toBe('সাভারে নতুন সেতু চালু হলো')
        ->and($items[0]['link'])->toBe('https://bangla.bdnews24.com/bangladesh/abc123')
        ->and($items[0]['image'])->toBe('https://media.example.com/a.jpg')
        ->and($items[0]['published_at'])->not->toBeNull()
        ->and($items[0]['state'])->toBe('new')
        ->and($items[1]['state'])->toBe('skipped')
        ->and($items[1]['reason'])->toBe('অন্য সাইট');
});

test('সারাদেশ সংবাদে শিরোনামে থাকা স্থান খসড়ায় বসে', function () {
    \App\Support\LocationImporter::import();
    $savar = \App\Models\Location::upazilas()->where('slug', 'savar')->firstOrFail();

    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'সাভারে নতুন সেতু চালু হলো',
        'link' => 'https://www.prothomalo.com/bangladesh/savar-bridge',
        'category' => 'saradesh',
        'category_tag' => 'জেলা',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সেতুটি আজ চালু হয়েছে।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    $news = News::firstOrFail();

    expect($result['state'])->toBe('imported');
    expect($news->location_id)->toBe($savar->id);
    expect($news->reporter)->toBe('সংবাদদাতা, সাভার (ঢাকা)');
    expect($news->tags->pluck('name')->all())->toContain('সাভার', 'ঢাকা', 'সারাদেশ');
});

test('জেলা শহরের সারাদেশ সংবাদে বাইলাইন জেলার নাম', function () {
    \App\Support\LocationImporter::import();
    $kushtia = \App\Models\Location::districts()->where('slug', 'kushtia')->firstOrFail();

    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'কুষ্টিয়ায় নতুন সেতু চালু হলো',
        'link' => 'https://www.prothomalo.com/bangladesh/kushtia-bridge',
        'category' => 'saradesh',
        'category_tag' => 'জেলা',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সেতুটি আজ চালু হয়েছে।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    $news = News::firstOrFail();

    expect($result['state'])->toBe('imported');
    expect($news->location_id)->toBe($kushtia->id);
    expect($news->reporter)->toBe('সংবাদদাতা, কুষ্টিয়া');
});

test('সদর উপজেলা নেই এমন জেলার সংবাদও আমদানি হয়', function () {
    \App\Support\LocationImporter::import();
    $dhaka = \App\Models\Location::districts()->where('slug', 'dhaka')->firstOrFail();

    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'ঢাকায় নতুন সেতু চালু হলো',
        'link' => 'https://www.prothomalo.com/bangladesh/dhaka-bridge',
        'category' => 'saradesh',
        'category_tag' => 'জেলা',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সেতুটি আজ চালু হয়েছে।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    $news = News::firstOrFail();

    expect($result['state'])->toBe('imported');
    expect($news->location_id)->toBe($dhaka->id);
    expect($news->reporter)->toBe('সংবাদদাতা, ঢাকা');
});

test('প্রতিটি আমদানি করা সংবাদে ট্যাগ বসে', function () {
    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'নতুন সেতু উদ্বোধন করা হয়েছে',
        'link' => 'https://www.prothomalo.com/bangladesh/new-bridge-tags',
        'category' => 'jatiyo',
        'category_tag' => 'বাংলাদেশ',
        'tags' => ['বাংলাদেশ', 'সেতু'],
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সেতুটি উদ্বোধন করা হয়েছে।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    $tags = News::firstOrFail()->tags->pluck('name')->all();

    expect($result['state'])->toBe('imported');
    expect($tags)->toContain('বাংলাদেশ', 'সেতু', 'জাতীয়');
});

test('সারাদেশ ছাড়া অন্য বিভাগের সংবাদে স্থান নিজে থেকে বসে না', function () {
    \App\Support\LocationImporter::import();

    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'সাভারে নতুন সেতু চালু হলো',
        'link' => 'https://www.prothomalo.com/bangladesh/savar-bridge-jatiyo',
        'category' => 'jatiyo',
        'category_tag' => 'বাংলাদেশ',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সেতুটি আজ চালু হয়েছে।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    expect($result['state'])->toBe('imported');
    expect(News::firstOrFail()->location_id)->toBeNull();
});

test('ফিডের একাধিক ট্যাগ থেকে একাধিক ক্যাটাগরি আসে', function () {
    $config = (array) config('news_import');
    $config['text']['og_lookup'] = false;
    $config['text']['full_article'] = false;
    $config['images']['og_lookup'] = false;
    $source = $config['sources']['prothomalo'];

    $xml = importFeedXml([
        [
            'title' => 'বাংলাদেশের ক্রিকেট দলের নতুন সিরিজ',
            'link' => 'https://www.prothomalo.com/sports/cricket-series',
            'categories' => ['বাংলাদেশ', 'খেলা'],
            'date' => gmdate('D, d M Y H:i:s').' GMT',
            'description' => 'সিরিজ শুরু হচ্ছে।',
            'image' => 'https://www.prothomalo.com/images/cricket.jpg',
        ],
    ]);

    $items = FeedReader::parse('prothomalo', $source, $xml, $config, [], 5);

    expect($items[0]['category'])->toBe('jatiyo');
    expect($items[0]['categories'])->toBe(['jatiyo', 'sports']);
    expect($items[0]['tags'])->toBe(['বাংলাদেশ', 'খেলা']);
});

test('দল বাছাই করলে শুধু সেই দলের ক্যাটাগরির সংবাদ আসে', function () {
    $config = (array) config('news_import');
    $config['text']['og_lookup'] = false;
    $config['text']['full_article'] = false;
    $config['images']['og_lookup'] = false;
    $source = $config['sources']['prothomalo'];
    $now = gmdate('D, d M Y H:i:s').' GMT';

    $xml = importFeedXml([
        [
            'title' => 'বাংলাদেশের ক্রিকেট দলের নতুন সিরিজ',
            'link' => 'https://www.prothomalo.com/sports/cricket-series',
            'category' => 'খেলা',
            'date' => $now,
            'description' => 'সিরিজ শুরু হচ্ছে।',
        ],
        [
            'title' => 'নতুন নাটকের শুটিং শুরু হলো',
            'link' => 'https://www.prothomalo.com/entertainment/new-drama',
            'category' => 'বিনোদন',
            'date' => $now,
            'description' => 'শুটিং শুরু।',
        ],
        [
            'title' => 'জাতিসংঘে নতুন প্রস্তাব পাস হলো',
            'link' => 'https://www.prothomalo.com/international/un-resolution',
            'category' => 'আন্তর্জাতিক',
            'date' => $now,
            'description' => 'প্রস্তাব পাস।',
        ],
        [
            'title' => 'সংসদে নতুন আইন পাস হলো',
            'link' => 'https://www.prothomalo.com/politics/new-law',
            'category' => 'রাজনীতি',
            'date' => $now,
            'description' => 'আইন পাস।',
        ],
        [
            'title' => 'জেলায় নতুন সড়ক উদ্বোধন হলো',
            'link' => 'https://www.prothomalo.com/country/new-road',
            'category' => 'জেলা',
            'date' => $now,
            'description' => 'সড়ক উদ্বোধন।',
        ],
    ]);

    $items = FeedReader::parse('prothomalo', $source, $xml, $config, [], 10, (array) $config['groups']['sports']);

    expect(array_column($items, 'title'))->toBe([
        'বাংলাদেশের ক্রিকেট দলের নতুন সিরিজ',
        'নতুন নাটকের শুটিং শুরু হলো',
        'জাতিসংঘে নতুন প্রস্তাব পাস হলো',
    ]);
});

test('খুলনা বিভাগের দলে কেবল খুলনা বিভাগের জেলার সংবাদ আসে', function () {
    \App\Support\LocationImporter::import();
    LocationMatcher::reset();

    $config = (array) config('news_import');
    $config['text']['og_lookup'] = false;
    $config['text']['full_article'] = false;
    $config['images']['og_lookup'] = false;
    $source = $config['sources']['prothomalo'];
    $now = gmdate('D, d M Y H:i:s').' GMT';

    $xml = importFeedXml([
        [
            'title' => 'খুলনায় নতুন সড়ক উদ্বোধন হলো',
            'link' => 'https://www.prothomalo.com/country/khulna-road',
            'category' => 'জেলা',
            'date' => $now,
            'description' => 'সড়ক উদ্বোধন।',
        ],
        [
            'title' => 'ঢাকায় নতুন সড়ক উদ্বোধন হলো',
            'link' => 'https://www.prothomalo.com/country/dhaka-road',
            'category' => 'জেলা',
            'date' => $now,
            'description' => 'সড়ক উদ্বোধন।',
        ],
        [
            'title' => 'কুষ্টিয়ায় নতুন সেতু চালু হলো',
            'link' => 'https://www.prothomalo.com/country/kushtia-bridge',
            'category' => 'জেলা',
            'date' => $now,
            'description' => 'সেতু চালু।',
        ],
    ]);

    $items = FeedReader::parse('prothomalo', $source, $xml, $config, [], 10, (array) $config['groups']['khulna']);

    expect(array_column($items, 'title'))->toBe([
        'খুলনায় নতুন সড়ক উদ্বোধন হলো',
        'কুষ্টিয়ায় নতুন সেতু চালু হলো',
    ]);

    $all = FeedReader::parse('prothomalo', $source, $xml, $config, [], 10, (array) $config['groups']['saradesh']);

    expect(array_column($all, 'title'))->toBe([
        'খুলনায় নতুন সড়ক উদ্বোধন হলো',
        'ঢাকায় নতুন সড়ক উদ্বোধন হলো',
        'কুষ্টিয়ায় নতুন সেতু চালু হলো',
    ]);
});

test('আইন-আদালত, কৃষি, স্বাস্থ্য ও ভিডিও গ্যালারির সংবাদও ফিড থেকে আসে', function () {
    $sources = (array) config('news_import.sources');

    expect(FeedReader::categoriesFor($sources['prothomalo'], ['কৃষি'], 'https://www.prothomalo.com/agriculture/a'))
        ->toBe(['agriculture'])
        ->and(FeedReader::categoriesFor($sources['khulnagazette'], ['টপ নিউজ', 'কৃষি'], 'https://khulnagazette.com/a'))
        ->toBe(['others', 'agriculture'])
        ->and(FeedReader::categoriesFor($sources['jagonews24'], ['law-courts'], 'https://www.jagonews24.com/law-courts/a'))
        ->toBe(['law-court'])
        ->and(FeedReader::categoriesFor($sources['samakal'], [], 'https://samakal.com/law-court/a'))
        ->toBe(['law-court'])
        ->and(FeedReader::categoriesFor($sources['gramerkagoj'], [], 'https://www.gramerkagoj.com/court-law/a'))
        ->toBe(['law-court'])
        ->and(FeedReader::categoriesFor($sources['banglanews24'], [], 'https://www.banglanews24.com/law-court/news/bd/1.details'))
        ->toBe(['law-court'])
        ->and(FeedReader::categoriesFor($sources['samakal'], [], 'https://samakal.com/doctorbari/a'))
        ->toBe(['health'])
        ->and(FeedReader::categoriesFor($sources['gramerkagoj'], [], 'https://www.gramerkagoj.com/health-story/a'))
        ->toBe(['health'])
        ->and(FeedReader::categoriesFor($sources['bdnews24'], [], 'https://bangla.bdnews24.com/tube/a'))
        ->toBe(['video-gallery']);
});

test('শিক্ষা ট্যাগ বা সেকশন শিক্ষা ক্যাটাগরিতেই মাপে', function () {
    $sources = (array) config('news_import.sources');

    expect(FeedReader::categoriesFor($sources['khulnagazette'], ['টপ নিউজ', 'শিক্ষা'], 'https://khulnagazette.com/a'))
        ->toBe(['others', 'education'])
        ->and(FeedReader::categoriesFor($sources['banglanews24'], [], 'https://www.banglanews24.com/education/news/bd/1.details'))
        ->toBe(['education'])
        ->and(FeedReader::categoriesFor($sources['bdnews24'], [], 'https://bangla.bdnews24.com/education/abc123'))
        ->toBe(['education']);
});

test('ফিডের প্রথম ট্যাগ others হলেও প্রধান ক্যাটাগরি আসল বিভাগ হয়', function () {
    Category::create(['name' => 'শিক্ষা', 'slug' => 'education']);

    $result = NewsImporter::save([
        'source' => 'khulnagazette',
        'title' => 'শিক্ষা প্রতিষ্ঠান নিয়ে নতুন ঘোষণা',
        'link' => 'https://khulnagazette.com/education/new-announcement',
        'category' => 'others',
        'categories' => ['others', 'education'],
        'category_tag' => 'টপ নিউজ',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সংক্ষিপ্ত অংশ।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    expect($result['state'])->toBe('imported');

    $news = News::firstOrFail();
    expect($news->category_id)->toBe(Category::where('slug', 'education')->value('id'))
        ->and($news->categories->pluck('slug')->all())->toContain('others')->toContain('education');
});

test('সংবাদ সংরক্ষণে সব ক্যাটাগরি যুক্ত হয়', function () {
    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'একাধিক ক্যাটাগরির একটি সংবাদ',
        'link' => 'https://www.prothomalo.com/bangladesh/multi-category',
        'category' => 'jatiyo',
        'categories' => ['jatiyo', 'others'],
        'category_tag' => 'বাংলাদেশ',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'সংক্ষিপ্ত অংশ।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    expect($result['state'])->toBe('imported');

    $slugs = News::firstOrFail()->categories->pluck('slug')->all();
    expect($slugs)->toHaveCount(2)->toContain('jatiyo')->toContain('others');
});

test('ছবি না থাকলে ছবি ছাড়াই সংবাদ আমদানি হয়', function () {
    // গ্যালারিতে ছবি থাকলেও নিজে থেকে বসানো হয় না; সম্পাদক পরে হাতে যোগ করবেন।
    Media::create([
        'filename' => 'gallery-default.jpg',
        'original_name' => 'gallery-default.jpg',
        'mime' => 'image/jpeg',
        'size' => 2048,
    ]);

    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'ছবি ছাড়া একটি সংবাদ',
        'link' => 'https://www.prothomalo.com/bangladesh/no-image',
        'category' => 'jatiyo',
        'category_tag' => 'বাংলাদেশ',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'ছবি নেই।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    expect($result['state'])->toBe('imported');

    $news = News::firstOrFail();
    expect($news->image)->toBeNull()
        ->and($news->image_credit)->toBeNull();
});

test('সারাদেশ সংবাদে জেলা বা উপজেলা শনাক্ত না হলে আমদানি হয় না', function () {
    $result = NewsImporter::save([
        'source' => 'prothomalo',
        'title' => 'সারাদেশে নতুন প্রকল্প চালু হলো',
        'link' => 'https://www.prothomalo.com/country/no-upazila',
        'category' => 'saradesh',
        'category_tag' => 'জেলা',
        'published_at' => now()->toIso8601String(),
        'excerpt' => 'কোনো উপজেলার নাম নেই।',
        'image' => null,
        'state' => 'new',
        'reason' => '',
    ]);

    expect($result['state'])->toBe('skipped');
    expect($result['reason'])->toContain('উপজেলা');
    expect(News::count())->toBe(0);
});

test('প্রতি চালে সর্বোচ্চ ২০টি এবং খুলনা সোর্স আগে পড়া হয়', function () {
    $collect = (array) config('news_import.collect');

    expect($collect['max_per_run'])->toBe(20);
    expect($collect['priority_sources'])->toBe(['khulnagazette', 'gramerkagoj']);
    expect($collect['priority_per_source'])->toBeGreaterThan($collect['other_per_source']);
});
