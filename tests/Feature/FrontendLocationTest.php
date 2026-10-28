<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use App\Support\LocationImporter;

beforeEach(function () {
    LocationImporter::import();
    $this->category = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh', 'serial' => 1]);
    $this->dhakaDiv = Location::divisions()->where('slug', 'dhaka')->first();
    $this->dhakaDist = Location::districts()->where('slug', 'dhaka')->first();
    $this->gazipur = Location::districts()->where('slug', 'gazipur')->first();
    $this->savar = Location::upazilas()->where('slug', 'savar')->first();
    $this->sylhetDist = Location::districts()->where('slug', 'sylhet')->first();
});

function flNews(?Location $location, string $title, string $status = 'published', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => test()->category->id,
        'location_id' => $location?->id,
        'title' => $title,
        'slug' => "fl-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(30 - min($n, 25)) : null,
    ], $extra));
}

// ----------------------------------------------------------------- pages

it('shows a division page with the news of its districts and upazilas, and nothing else', function () {
    flNews($this->dhakaDiv, 'বিভাগীয় খবর');
    flNews($this->dhakaDist, 'ঢাকা জেলার খবর');
    flNews($this->savar, 'সাভারের খবর');
    flNews($this->sylhetDist, 'সিলেটের খবর');
    flNews(null, 'লোকেশনহীন খবর');

    $this->get('/location/dhaka')->assertOk()
        ->assertSee('বিভাগীয় খবর')->assertSee('ঢাকা জেলার খবর')->assertSee('সাভারের খবর')
        ->assertDontSee('সিলেটের খবর')->assertDontSee('লোকেশনহীন খবর');
});

it('shows a district page with its upazilas\' news but not the division-level or other districts\' news', function () {
    flNews($this->dhakaDiv, 'বিভাগীয় খবর');
    flNews($this->dhakaDist, 'ঢাকা জেলার খবর');
    flNews($this->savar, 'সাভারের খবর');
    flNews($this->gazipur, 'গাজীপুরের খবর');

    $this->get('/location/dhaka/dhaka')->assertOk()
        ->assertSee('ঢাকা জেলার খবর')->assertSee('সাভারের খবর')
        ->assertDontSee('বিভাগীয় খবর')->assertDontSee('গাজীপুরের খবর');
});

it('shows an upazila page with only that upazila\'s news', function () {
    flNews($this->dhakaDist, 'ঢাকা জেলার খবর');
    flNews($this->savar, 'সাভারের খবর');

    $this->get('/location/dhaka/dhaka/savar')->assertOk()
        ->assertSee('সাভারের খবর')->assertDontSee('ঢাকা জেলার খবর');
});

it('lists only published, live articles', function () {
    flNews($this->dhakaDist, 'প্রকাশিত');
    flNews($this->dhakaDist, 'খসড়া', 'draft');
    flNews($this->dhakaDist, 'অপেক্ষমাণ', 'pending');
    flNews($this->dhakaDist, 'ভবিষ্যতের', 'scheduled', ['published_at' => now()->addDay()]);
    flNews($this->dhakaDist, 'ট্র্যাশের')->delete();

    $this->get('/location/dhaka/dhaka')->assertOk()
        ->assertSee('প্রকাশিত')->assertDontSee('খসড়া')->assertDontSee('অপেক্ষমাণ')
        ->assertDontSee('ভবিষ্যতের')->assertDontSee('ট্র্যাশের');
});

it('links the districts of a division and the upazilas of a district', function () {
    $this->get('/location/dhaka')->assertOk()
        ->assertSee('/location/dhaka/gazipur', false)->assertSee('/location/dhaka/dhaka', false)
        ->assertSee('জেলা:');

    $this->get('/location/dhaka/dhaka')->assertOk()
        ->assertSee('/location/dhaka/dhaka/savar', false)->assertSee('উপজেলা:');

    $this->get('/location/dhaka/dhaka/savar')->assertOk()->assertDontSee('উপজেলা:')->assertDontSee('জেলা:');
});

it('shows a breadcrumb from the division down to the page', function () {
    $html = $this->get('/location/dhaka/dhaka/savar')->assertOk()->getContent();

    expect(strpos($html, '/location/dhaka"'))->not->toBeFalse()
        ->and($html)->toContain('/location/dhaka/dhaka"')
        ->and($html)->toContain('aria-current="page">সাভার<');
});

it('answers 404 for places that do not exist or are in the wrong place in the hierarchy', function () {
    $this->get('/location/nowhere')->assertNotFound();
    $this->get('/location/dhaka/nowhere')->assertNotFound();
    $this->get('/location/dhaka/dhaka/nowhere')->assertNotFound();
    $this->get('/location/sylhet/dhaka')->assertNotFound();           // Dhaka district is not in Sylhet division
    $this->get('/location/dhaka/gazipur/savar')->assertNotFound();    // Savar is not in Gazipur
    $this->get('/location/dhaka/dhaka/savar/extra')->assertNotFound(); // no fourth level
    $this->get('/location/dhaka/dhaka')->assertOk();                   // ...while the real ones work
});

it('does not mistake a district or upazila for a division', function () {
    // "gazipur" is a district, never a division
    $this->get('/location/gazipur')->assertNotFound();
    $this->get('/location/savar')->assertNotFound();
});

it('paginates, newest first', function () {
    foreach (range(1, 13) as $i) {
        flNews($this->dhakaDist, "পেজ-লোকেশন খবর {$i}", 'published', ['published_at' => now()->subHours(20 - $i)]);
    }

    $this->get('/location/dhaka/dhaka')->assertOk()
        ->assertSee('পেজ-লোকেশন খবর 13')->assertSee('page=2', false)->assertDontSee('পেজ-লোকেশন খবর 1<', false);
    $this->get('/location/dhaka/dhaka?page=2')->assertOk()->assertSee('পেজ-লোকেশন খবর 1')->assertDontSee('পেজ-লোকেশন খবর 13');
});

it('says so when a place has no news yet, instead of a 404', function () {
    $this->get('/location/dhaka/dhaka')->assertOk()->assertSee('কোনো সংবাদ এখনও প্রকাশিত হয়নি');
});

it('sets the page title from the place', function () {
    $this->get('/location/dhaka')->assertSee('<title>ঢাকা বিভাগ | স্বাধীন আলো</title>', false);
    $this->get('/location/dhaka/gazipur')->assertSee('<title>গাজীপুর | স্বাধীন আলো</title>', false);
});

// --------------------------------------------------------------- the menu

it('puts the 8 divisions under "সারাদেশ" in the menu, each linking to its page', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $nationwide = strpos($html, 'href="http://localhost/category/saradesh"');

    expect($nationwide)->not->toBeFalse();

    foreach (Location::divisions()->get() as $division) {
        $pos = strpos($html, 'href="'.$division->url().'"');
        expect($pos)->not->toBeFalse("division {$division->slug} missing from the menu")
            ->and($pos)->toBeGreaterThan($nationwide);
    }

    expect(substr_count($html, 'href="http://localhost/location/'))->toBeGreaterThanOrEqual(8);
});

it('keeps the "সারাদেশ" menu working before any location is loaded', function () {
    Location::query()->update(['parent_id' => null]);
    Location::query()->delete();

    $this->get('/')->assertOk()->assertSee('/category/saradesh', false);
});

it('does not add division dropdowns to other categories', function () {
    $other = Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti', 'serial' => 2]);

    $html = $this->get('/')->getContent();
    $start = strpos($html, 'href="http://localhost/category/rajniti"');

    expect($start)->not->toBeFalse()
        ->and(substr($html, $start, 160))->not->toContain('/location/');
});

it('updates the menu at once when a division changes', function () {
    $this->get('/')->assertOk()->assertSee('ঢাকা');

    $this->dhakaDiv->update(['name_bn' => 'ঢাকা-পরিবর্তিত']);

    $this->get('/')->assertSee('ঢাকা-পরিবর্তিত');
});

// ------------------------------------------------------------- article page

it('shows where the article is about, as links from the most general to the most specific', function () {
    $news = flNews($this->savar, 'সাভারের বিশেষ খবর');

    $html = $this->get(route('frontend.news.show', $news->slug))->assertOk()->assertSee('স্থান:')->getContent();

    $division = strpos($html, 'href="http://localhost/location/dhaka"');
    $district = strpos($html, 'href="http://localhost/location/dhaka/dhaka"');
    $upazila = strpos($html, 'href="http://localhost/location/dhaka/dhaka/savar"');

    expect($division)->not->toBeFalse()
        ->and($district)->toBeGreaterThan($division)
        ->and($upazila)->toBeGreaterThan($district);
});

it('omits the place line when the article has none', function () {
    $news = flNews(null, 'জাতীয় খবর');

    $this->get(route('frontend.news.show', $news->slug))->assertOk()->assertDontSee('স্থান:');
});

it('escapes place names', function () {
    $evil = Location::create(['parent_id' => $this->dhakaDist->id, 'type' => 'upazila', 'name_bn' => '<script>alert(1)</script>', 'name_en' => 'Evil', 'slug' => 'evil']);
    $news = flNews($evil, 'খারাপ নামের স্থান');

    $this->get(route('frontend.news.show', $news->slug))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/location/dhaka/dhaka/evil')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});

it('renders a freshly seeded site with the geography loaded', function () {
    $this->seed(\Database\Seeders\CategorySeeder::class);
    $this->seed(\Database\Seeders\NewsSeeder::class);
    $this->seed(\Database\Seeders\LocationSeeder::class);

    $this->get('/')->assertOk()->assertSee('/location/dhaka', false);
});
