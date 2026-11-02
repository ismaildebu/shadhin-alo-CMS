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

    NewsImporter::resetMemo();
});

function importFeedXml(array $items): string
{
    $body = '';

    foreach ($items as $item) {
        $body .= '<item>'
            .'<title>'.htmlspecialchars($item['title'], ENT_XML1).'</title>'
            .'<link>'.$item['link'].'</link>'
            .'<category>'.htmlspecialchars($item['category'] ?? '', ENT_XML1).'</category>'
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

    expect($sources)->toHaveCount(4);

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
