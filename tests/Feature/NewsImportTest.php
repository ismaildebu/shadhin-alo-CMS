<?php

use App\Models\Category;
use App\Models\News;
use App\Models\NewsImport;
use App\Support\NewsImport\FeedReader;
use App\Support\NewsImport\NewsImporter;

beforeEach(function () {
    Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh']);
    Category::create(['name' => 'অন্যান্য', 'slug' => 'others']);
    Category::create(['name' => 'সর্বশেষ', 'slug' => 'Latest']);

    config(['news_import.images.required' => false]);

    NewsImporter::resetMemo();
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

    expect($sources)->toHaveCount(8);

    foreach ($sources as $key => $source) {
        expect($source['feed'])->toStartWith('https://');
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

test('অজানা বা মুছে ফেলা ক্যাটাগরি হলে Latest-এ যায়', function () {
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
    expect(News::firstOrFail()->categories->pluck('slug')->all())->toBe(['Latest']);
});

test('Google News সাইটম্যাপ থেকেও শিরোনাম, লিংক, তারিখ ও ছবি পড়া হয়', function () {
    $config = (array) config('news_import');
    $config['text']['og_lookup'] = false; // নেটওয়ার্ক ছাড়া পরীক্ষা
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

    expect($result['state'])->toBe('imported');
    expect(News::firstOrFail()->location_id)->toBe($savar->id);
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

    expect($items[0]['category'])->toBe('Jayato');
    expect($items[0]['categories'])->toBe(['Jayato', 'Sports']);
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

test('ছবি না থাকলে সংবাদ আমদানি হয় না', function () {
    config(['news_import.images.required' => true]);

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

    expect($result['state'])->toBe('skipped');
    expect($result['reason'])->toBe('ছবি নেই');
    expect(News::count())->toBe(0);
});

test('সারাদেশ সংবাদে উপজেলা শনাক্ত না হলে আমদানি হয় না', function () {
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
