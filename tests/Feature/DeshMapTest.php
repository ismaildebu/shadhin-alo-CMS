
<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use App\Support\BdMap;
use App\Support\LocationImporter;

beforeEach(function () {
    LocationImporter::import();
});

it('lists the children of a place as JSON with their public URLs', function () {
    $division = Location::divisions()
        ->where('slug', 'dhaka')
        ->firstOrFail();

    $json = $this->getJson(
        route('frontend.locations.children', $division->id)
    )->assertOk()->json();

    expect($json)->not->toBeEmpty()
        ->and($json[0])->toHaveKeys(['id', 'name', 'url'])
        ->and($json[0]['url'])->toStartWith(
            url('/location/dhaka/')
        );
});

it('returns an empty list for a place without children and 404 for an unknown one', function () {
    $upazila = Location::upazilas()->firstOrFail();

    $this->getJson(
        route('frontend.locations.children', $upazila->id)
    )->assertOk()->assertExactJson([]);

    $this->getJson(
        route('frontend.locations.children', 999999)
    )->assertNotFound();
});

it('knows a map key for every seeded division', function () {
    foreach (Location::divisions()->get() as $division) {
        expect(BdMap::keyFor(
            $division->slug,
            $division->name_en
        ))->not->toBeNull(
            "no map shape for division {$division->slug} / {$division->name_en}"
        );
    }
});

it('renders the desh_map block with the filter and a map shape per division', function () {
    $category = Category::create([
        'name' => 'দেশজুড়ে',
        'slug' => 'deshjure',
        'show_on_home' => true,
        'home_layout' => 'desh_map',
        'home_full' => true,
    ]);

    foreach (range(1, 6) as $i) {
        News::create([
            'category_id' => $category->id,
            'title' => "দেশজুড়ে খবর {$i}",
            'slug' => "deshjure-khobor-{$i}",
            'description' => '<p>x</p>',
            'status' => News::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes($i),
        ]);
    }

    $html = $this->get('/')
        ->assertOk()
        ->assertSee('data-desh-filter', false)
        ->getContent();

    expect(substr_count($html, 'class="sa-bdmap-div'))
        ->toBe(Location::divisions()->count());
});
