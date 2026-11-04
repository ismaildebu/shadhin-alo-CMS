<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use App\Models\Video;
use App\Support\LocationImporter;

beforeEach(function () {
    LocationImporter::import();

    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo', 'serial' => 1, 'show_in_menu' => true]);
    $this->nation = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh', 'serial' => 2, 'show_in_menu' => true]);

    $this->news = sbNews('সাইডবারের খবর', $this->category);
    $this->news->syncTagNames(['টেস্ট-ট্যাগ']);

    // এই খবরটি ওই পেজের তালিকায় নেই — শুধু সাইডবারে আসবে
    $this->elsewhere = sbNews('অন্য বিভাগের খবর', $this->nation);

    $this->video = Video::create([
        'title' => 'পরীক্ষার ভিডিও',
        'youtube_id' => 'abcdef12345',
        'description' => 'বিবরণ',
        'status' => true,
        'published_at' => now()->subHour(),
    ]);
});

function sbNews(string $title, Category $category): News
{
    return News::create([
        'category_id' => $category->id,
        'location_id' => Location::districts()->where('slug', 'dhaka')->first()->id,
        'title' => $title,
        'slug' => 'sb-'.Str::slug($title).'-'.$category->id,
        'description' => '<p>বিস্তারিত</p>',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);
}

it('gives every listing page a sidebar rail', function () {
    $paths = [
        '/category/jatiyo',
        '/tag/'.rawurlencode('টেস্ট-ট্যাগ'),
        '/location/dhaka/dhaka',
        '/videos',
        route('frontend.video.show', $this->video),
    ];

    foreach ($paths as $path) {
        $this->get($path)->assertOk()->assertSee('sa-sidebar', false);
    }
});

it('fills the sidebar widgets on a page that sends no sidebar data of its own', function () {
    $list = newsList($this->get('/category/jatiyo')->assertOk()->getContent());

    // তালিকায় নেই, কিন্তু সাইডবারের "সর্বশেষ" তালিকায় আছে
    expect($list)->not->toContain('অন্য বিভাগের খবর');
    $this->get('/category/jatiyo')->assertOk()->assertSee('অন্য বিভাগের খবর');
});

it('lists every division of the সারাদেশ menu on its own line without a heading', function () {
    $html = $this->get('/category/jatiyo')->assertOk()->getContent();

    expect($html)->not->toContain('বিভাগসমূহ');
    expect($html)->not->toContain('sa-mega-col');

    // সারাদেশের ড্রপডাউন: প্রতিটি বিভাগ আলাদা <li>-তে
    preg_match('/সারাদেশ<span class="sa-nav-caret".*?<\/ul>/s', $html, $drop);
    $panel = $drop[0] ?? '';

    foreach (Location::divisions()->ordered()->get() as $division) {
        expect($panel)->toContain('<li><a class="dropdown-item" href="'.route('frontend.location.show', $division->slug).'">'.$division->name_bn.'</a></li>');
    }
});

it('keeps the news list two cards wide beside the rail', function () {
    $this->get('/category/jatiyo')
        ->assertOk()
        ->assertSee('col-lg-8 sa-list-main', false)
        ->assertSee('col-lg-6 col-md-6', false);
});
