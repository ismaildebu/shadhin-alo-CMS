<?php

use App\Models\Category;
use App\Models\Chart;
use App\Models\Location;
use App\Models\News;
use App\Support\BdGeo;
use App\Support\LocationImporter;

beforeEach(function () {
    LocationImporter::import();

    $this->saradesh = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh']);
    $this->jatiyo = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $this->satkhira = Location::where('slug', 'satkhira')->firstOrFail();
    $this->debhatta = Location::where('slug', 'debhata')->firstOrFail();
});

function sceneNews(Category $category, Location $location, array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => $category->id,
        'title' => "ম্যাপ ছবির সংবাদ {$n}",
        'slug' => "map-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
        'location_id' => $location->id,
    ], $extra));
}

it('draws the country map with the enlarged district, pin and name for Saradesh news', function () {
    $news = sceneNews($this->saradesh, $this->debhatta);

    $html = view('frontend.partials.no-image', ['news' => $news, 'sizeClass' => 'card-img-top'])->render();

    expect($html)
        ->toContain('sa-noimg-scene')
        ->toContain('সাতক্ষীরা')
        ->toContain('sa-noimg-outline')
        ->toContain('sa-noimg-link')
        ->toContain('sa-noimg-pin')
        ->toContain('sa-noimg-dot')
        ->not->toContain('sa-chart-thumb');

    // every division is drawn once on the small country map
    expect(substr_count($html, 'sa-noimg-cty'))->toBe(count(\App\Support\BdMap::SHAPES));
});

it('uses a compact district marker for list thumbnails', function () {
    $news = sceneNews($this->saradesh, $this->debhatta);

    $html = view('frontend.partials.no-image', [
        'news' => $news,
        'sizeClass' => 'sa-list-thumb',
    ])->render();

    expect($html)
        ->toContain('viewBox="0 0 300 300"')
        ->toContain('sa-noimg-outline')
        ->not->toContain('sa-noimg-link')
        ->not->toContain('sa-noimg-label');
});

it('keeps the chart in the image slot when a Saradesh news also has a chart', function () {
    $chart = Chart::create([
        'title' => 'সারাদেশের গ্রাফ',
        'type' => 'column',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [3, 8]],
        'is_published' => true,
    ]);

    $news = sceneNews($this->saradesh, $this->debhatta, [
        'chart_shortcode' => '[chart id="'.$chart->id.'"]',
    ]);

    $html = view('frontend.partials.no-image', ['news' => $news, 'sizeClass' => 'card-img-top'])->render();

    expect($html)->toContain('sa-chart-thumb')->not->toContain('sa-noimg-scene');
});

it('leaves other categories on the plain placeholder', function () {
    $news = sceneNews($this->jatiyo, $this->debhatta);

    $html = view('frontend.partials.no-image', ['news' => $news, 'sizeClass' => 'card-img-top'])->render();

    expect($html)->toContain('sa-noimg-plain')->not->toContain('sa-noimg-scene');
});

it('builds scene geometry inside the picture and names the district', function () {
    $geo = BdGeo::locate($this->satkhira, $this->debhatta);
    $scene = BdGeo::scene($geo);

    expect($scene)->not->toBeNull()
        ->and($scene['name'])->toBe('সাতক্ষীরা')
        ->and($scene['right']['x'])->toBeGreaterThanOrEqual(520)
        ->and($scene['right']['x'] + $scene['right']['w'])->toBeLessThanOrEqual(900)
        ->and($scene['right']['y'])->toBeGreaterThanOrEqual(10)
        ->and($scene['right']['y'] + $scene['right']['h'])->toBeLessThanOrEqual(558)
        ->and($scene['connector'])->toContain((string) $scene['pin']['x']);
});

it('shows the map picture on the published article page', function () {
    $news = sceneNews($this->saradesh, $this->debhatta);

    $this->get(route('frontend.news.show', $news->slug))
        ->assertOk()
        ->assertSee('sa-noimg-scene', false)
        ->assertSee('সাতক্ষীরা');
});
