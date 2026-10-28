<?php

use App\Models\Location;
use App\Models\News;
use App\Support\LocationImporter;

beforeEach(fn () => LocationImporter::import());

// The 8 divisions with the 64 districts each contains: an independent description of the administrative
// structure, so a wrong or corrupted data file cannot pass just because it agrees with itself.
const EXPECTED_DISTRICTS = [
    'barishal' => ['Barguna', 'Barishal', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur'],
    'chattogram' => ['Bandarban', 'Brahmanbaria', 'Chandpur', 'Chattogram', "Cox's Bazar", 'Cumilla', 'Feni', 'Khagrachhari', 'Lakshmipur', 'Noakhali', 'Rangamati'],
    'dhaka' => ['Dhaka', 'Faridpur', 'Gazipur', 'Gopalganj', 'Kishoreganj', 'Madaripur', 'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Rajbari', 'Shariatpur', 'Tangail'],
    'khulna' => ['Bagerhat', 'Chuadanga', 'Jashore', 'Jhenaidah', 'Khulna', 'Kushtia', 'Magura', 'Meherpur', 'Narail', 'Satkhira'],
    'mymensingh' => ['Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur'],
    'rajshahi' => ['Bogura', 'Chapainawabganj', 'Joypurhat', 'Naogaon', 'Natore', 'Pabna', 'Rajshahi', 'Sirajganj'],
    'rangpur' => ['Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Panchagarh', 'Rangpur', 'Thakurgaon'],
    'sylhet' => ['Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet'],
];

it('loads 8 divisions and 64 districts', function () {
    expect(Location::divisions()->count())->toBe(8)
        ->and(Location::districts()->count())->toBe(64);
});

it('puts every district under the right division', function () {
    foreach (EXPECTED_DISTRICTS as $divisionSlug => $districts) {
        $division = Location::divisions()->where('slug', $divisionSlug)->firstOrFail();

        expect($division->children()->pluck('name_en')->sort()->values()->all())
            ->toBe(collect($districts)->sort()->values()->all(), "districts of {$divisionSlug}");
    }
});

it('gives every district at least one upazila, and a plausible total', function () {
    foreach (Location::districts()->withCount('children')->get() as $district) {
        expect($district->children_count)->toBeGreaterThan(0, "{$district->name_en} has no upazila");
    }

    // The official number varies slightly over time; a wildly different total means a damaged file.
    expect(Location::upazilas()->count())->toBeBetween(485, 500);
});

it('knows upazilas that are certain', function () {
    $dhaka = Location::districts()->where('slug', 'dhaka')->firstOrFail();

    expect($dhaka->children()->pluck('name_en')->sort()->values()->all())
        ->toBe(['Dhamrai', 'Dohar', 'Keraniganj', 'Nawabganj', 'Savar']);

    $sylhet = Location::districts()->where('slug', 'sylhet')->firstOrFail();
    expect($sylhet->children()->pluck('name_en')->all())->toContain('Beanibazar', 'Jaintiapur', 'Gowainghat');
});

it('has Bengali names that are real Bengali, trimmed and in one canonical Unicode form', function () {
    foreach (Location::all() as $place) {
        expect($place->name_bn)->toMatch('/[\x{0980}-\x{09FF}]/u')
            ->and($place->name_bn)->toBe(trim($place->name_bn))
            ->and($place->name_bn)->not->toContain('  ')
            ->and(\App\Support\BengaliText::normalize($place->name_bn))->toBe($place->name_bn, "{$place->name_en} is not NFC");

        expect($place->name_en)->toBe(trim($place->name_en))->and($place->name_en)->not->toBeEmpty();
    }
});

it('has unique slugs among siblings and only valid parent/child types', function () {
    $dupes = Location::query()
        ->selectRaw('parent_id, slug, count(*) as n')
        ->groupBy('parent_id', 'slug')
        ->havingRaw('count(*) > 1')
        ->get();

    expect($dupes)->toHaveCount(0);

    foreach (Location::with('parent')->get() as $place) {
        match ($place->type) {
            Location::DIVISION => expect($place->parent_id)->toBeNull(),
            Location::DISTRICT => expect($place->parent->type)->toBe(Location::DIVISION),
            Location::UPAZILA => expect($place->parent->type)->toBe(Location::DISTRICT),
        };
    }
});

it('uses readable English slugs, with the official spellings', function () {
    expect(Location::districts()->where('slug', 'coxs-bazar')->exists())->toBeTrue()
        ->and(Location::districts()->where('slug', 'cumilla')->exists())->toBeTrue()
        ->and(Location::divisions()->where('slug', 'chattogram')->exists())->toBeTrue()
        ->and(Location::divisions()->where('slug', 'barishal')->exists())->toBeTrue()
        ->and(Location::all()->every(fn ($p) => preg_match('/^[a-z0-9-]+$/', $p->slug) === 1))->toBeTrue();
});

it('lets the same upazila name exist in different districts', function () {
    $nawabganj = Location::upazilas()->where('slug', 'nawabganj')->get();

    expect($nawabganj->count())->toBeGreaterThan(1)
        ->and($nawabganj->pluck('parent_id')->unique())->toHaveCount($nawabganj->count());
});

it('can be imported twice without duplicating anything', function () {
    $before = Location::count();

    $stats = LocationImporter::import();

    expect(Location::count())->toBe($before)
        ->and($stats['created'])->toBe(0)
        ->and($stats['updated'])->toBe($before);
});

it('repairs a changed name on re-import but never deletes a place or its articles', function () {
    $dhaka = Location::districts()->where('slug', 'dhaka')->firstOrFail();
    $dhaka->update(['name_bn' => 'ভুল নাম']);

    $extra = Location::create(['parent_id' => $dhaka->id, 'type' => 'upazila', 'name_bn' => 'অতিরিক্ত', 'name_en' => 'Extra', 'slug' => 'extra']);

    LocationImporter::import();

    expect($dhaka->fresh()->name_bn)->toBe('ঢাকা')
        ->and(Location::find($extra->id))->not->toBeNull();
});

it('reports a missing or broken data file instead of failing silently', function () {
    expect(fn () => LocationImporter::import('/nonexistent/file.json'))->toThrow(InvalidArgumentException::class);

    $bad = tempnam(sys_get_temp_dir(), 'loc');
    file_put_contents($bad, '{not json');
    expect(fn () => LocationImporter::import($bad))->toThrow(InvalidArgumentException::class);
    @unlink($bad);

    $this->artisan('locations:import', ['file' => '/nonexistent/file.json'])->assertFailed();
    $this->artisan('locations:import')->assertSuccessful();
});

it('builds paths, names and URLs from a place up to its division', function () {
    $savar = Location::upazilas()->where('slug', 'savar')->firstOrFail();

    expect($savar->path()->pluck('slug')->all())->toBe(['dhaka', 'dhaka', 'savar'])
        ->and($savar->path()->pluck('type')->all())->toBe(['division', 'district', 'upazila'])
        ->and($savar->fullNameBn())->toBe('সাভার, ঢাকা, ঢাকা')
        ->and($savar->url())->toBe(url('/location/dhaka/dhaka/savar'));
});

it('collects a place and everything below it', function () {
    $division = Location::divisions()->where('slug', 'sylhet')->firstOrFail();
    $district = Location::districts()->where('slug', 'sylhet')->firstOrFail();
    $upazila = Location::upazilas()->where('slug', 'beanibazar')->firstOrFail();

    $divisionIds = $division->descendantIds();
    expect($divisionIds)->toContain($division->id, $district->id, $upazila->id)
        ->and(count($divisionIds))->toBe(1 + 4 + Location::upazilas()->whereIn('parent_id', $division->children()->pluck('id'))->count());

    expect($district->descendantIds())->toContain($district->id, $upazila->id)->not->toContain($division->id)
        ->and($upazila->descendantIds())->toBe([$upazila->id]);
});

it('can be rolled back and migrated again WITH data loaded (a populated, self-referencing table used to block the drop)', function () {
    $category = \App\Models\Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $savar = Location::upazilas()->where('slug', 'savar')->firstOrFail();
    $reporter = \App\Models\User::factory()->reporter()->create();
    $reporter->forceFill(['district_id' => $savar->parent_id])->save();
    $news = News::create(['category_id' => $category->id, 'location_id' => $savar->id, 'title' => 'সংবাদ', 'slug' => 's', 'description' => '<p>x</p>']);

$this->artisan('migrate:rollback', [
    '--path' => 'database/migrations/2026_10_14_000001_create_locations_table.php',
])->assertSuccessful();

    expect(\Illuminate\Support\Facades\Schema::hasTable('locations'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('news', 'location_id'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('users', 'district_id'))->toBeFalse()
        ->and(News::find($news->id))->not->toBeNull()                       // articles survive
        ->and(\App\Models\User::find($reporter->id))->not->toBeNull();      // users survive

    $this->artisan('migrate')->assertSuccessful();

    expect(\Illuminate\Support\Facades\Schema::hasTable('locations'))->toBeTrue();
});
