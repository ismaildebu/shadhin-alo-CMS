<?php

use App\Models\Activity;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use App\Models\User;
use App\Services\LocationResolver;
use App\Support\LocationImporter;

beforeEach(function () {
    LocationImporter::import();
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->editor()->create();
    $this->dhakaDiv = Location::divisions()->where('slug', 'dhaka')->first();
    $this->dhakaDist = Location::districts()->where('slug', 'dhaka')->first();
    $this->savar = Location::upazilas()->where('slug', 'savar')->first();
    $this->sylhetDiv = Location::divisions()->where('slug', 'sylhet')->first();
    $this->sylhetDist = Location::districts()->where('slug', 'sylhet')->first();
    $this->gazipur = Location::districts()->where('slug', 'gazipur')->first();
});

function lcPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'লোকেশন পরীক্ষার সংবাদ',
        'description' => '<p>বিস্তারিত</p>',
        'status' => 'draft',
    ], $overrides);
}

function lcArticle(?Location $location, string $status = 'published', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => test()->category->id,
        'author_id' => test()->editor->id,
        'location_id' => $location?->id,
        'title' => "লোকেশন সংবাদ {$n}",
        'slug' => "lc-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(30 - min($n, 25)) : null,
    ], $extra));
}

// ----------------------------------------------- choosing a place on the form

it('stores the most specific choice: upazila, then district, then division', function () {
    $this->actingAs($this->editor)->post('/admin/news', lcPayload([
        'division_id' => $this->dhakaDiv->id, 'district_id' => $this->dhakaDist->id, 'upazila_id' => $this->savar->id,
    ]))->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->post('/admin/news', lcPayload([
        'title' => 'জেলা পর্যায়', 'division_id' => $this->dhakaDiv->id, 'district_id' => $this->dhakaDist->id,
    ]))->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->post('/admin/news', lcPayload([
        'title' => 'বিভাগ পর্যায়', 'division_id' => $this->dhakaDiv->id,
    ]))->assertSessionHasNoErrors();

    expect(News::where('title', 'লোকেশন পরীক্ষার সংবাদ')->first()->location_id)->toBe($this->savar->id)
        ->and(News::where('title', 'জেলা পর্যায়')->first()->location_id)->toBe($this->dhakaDist->id)
        ->and(News::where('title', 'বিভাগ পর্যায়')->first()->location_id)->toBe($this->dhakaDiv->id);
});

it('accepts an upazila or district without the levels above it', function () {
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['upazila_id' => $this->savar->id]))->assertSessionHasNoErrors();

    expect(News::firstOrFail()->location_id)->toBe($this->savar->id);
});

it('leaves the place empty for national news', function () {
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['location_present' => 1]))->assertSessionHasNoErrors();

    expect(News::firstOrFail()->location_id)->toBeNull();
});

it('rejects choices that do not describe one place', function (array $mix) {
    $ids = [
        'dhakaDiv' => $this->dhakaDiv->id, 'sylhetDiv' => $this->sylhetDiv->id,
        'dhakaDist' => $this->dhakaDist->id, 'sylhetDist' => $this->sylhetDist->id,
        'savar' => $this->savar->id,
    ];

    $payload = [];
    foreach ($mix as $field => $key) {
        $payload[$field] = $ids[$key];
    }

    $this->actingAs($this->editor)->post('/admin/news', lcPayload($payload))->assertSessionHasErrors('upazila_id');

    expect(News::count())->toBe(0);
})->with([
    'district from another division' => [['division_id' => 'sylhetDiv', 'district_id' => 'dhakaDist']],
    'upazila from another district' => [['district_id' => 'sylhetDist', 'upazila_id' => 'savar']],
    'upazila from another division' => [['division_id' => 'sylhetDiv', 'upazila_id' => 'savar']],
    'all three, upazila elsewhere' => [['division_id' => 'dhakaDiv', 'district_id' => 'sylhetDist', 'upazila_id' => 'savar']],
]);

it('rejects ids of the wrong kind or that do not exist', function () {
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['district_id' => $this->dhakaDiv->id]))->assertSessionHasErrors('upazila_id');
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['division_id' => $this->savar->id]))->assertSessionHasErrors('upazila_id');
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['upazila_id' => 999999]))->assertSessionHasErrors('upazila_id');
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['district_id' => 'abc']))->assertSessionHasErrors('district_id');

    expect(News::count())->toBe(0);
});

it('changes the place on update, and clears it when the form sends none', function () {
    $news = lcArticle($this->dhakaDist, 'draft');

    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", lcPayload(['district_id' => $this->sylhetDist->id]))
        ->assertSessionHasNoErrors();
    expect($news->fresh()->location_id)->toBe($this->sylhetDist->id);

    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", lcPayload(['location_present' => 1]))
        ->assertSessionHasNoErrors();
    expect($news->fresh()->location_id)->toBeNull();
});

it('keeps the place when an update never mentions it', function () {
    $news = lcArticle($this->savar, 'draft');

    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", lcPayload(['title' => 'শুধু শিরোনাম']))
        ->assertSessionHasNoErrors();

    expect($news->fresh()->location_id)->toBe($this->savar->id);
});

it('shows the current place in the edit form, with all three selects', function () {
    $news = lcArticle($this->savar, 'draft');

    $html = $this->actingAs($this->editor)->get("/admin/news/{$news->id}/edit")->assertOk()->getContent();

    expect($html)->toContain('id="location-field"')->toContain('name="location_present"')
        ->and($html)->toContain('data-level="division"')->toContain('data-level="district"')->toContain('data-level="upazila"')
        ->and($html)->toMatch('/<option value="'.$this->savar->id.'" data-parent="'.$this->dhakaDist->id.'" selected>/')
        ->and($html)->toMatch('/<option value="'.$this->dhakaDist->id.'" data-parent="'.$this->dhakaDiv->id.'" selected>/')
        ->and($html)->toMatch('/<option value="'.$this->dhakaDiv->id.'" selected>/')
        ->and(substr_count($html, 'data-parent='))->toBe(Location::districts()->count() + Location::upazilas()->count());
});

it('tells the editor how to load the places when none exist', function () {
    Location::query()->update(['parent_id' => null]);
    Location::query()->delete();

    $this->actingAs($this->editor)->get('/admin/news/create')->assertOk()->assertSee('php artisan locations:import');
});

// ------------------------------------------------------- district correspondents

it('lets a district correspondent report only for their own district and its upazilas', function () {
    $reporter = User::factory()->reporter()->create();
    $reporter->forceFill(['district_id' => $this->dhakaDist->id])->save();

    // allowed: own district, own upazila
    $this->actingAs($reporter)->post('/admin/news', lcPayload(['title' => 'নিজ জেলা', 'district_id' => $this->dhakaDist->id]))->assertSessionHasNoErrors();
    $this->actingAs($reporter)->post('/admin/news', lcPayload(['title' => 'নিজ উপজেলা', 'upazila_id' => $this->savar->id]))->assertSessionHasNoErrors();

    // refused: another district, its division, someone else's upazila, a whole other division
    $this->actingAs($reporter)->post('/admin/news', lcPayload(['title' => 'অন্য জেলা', 'district_id' => $this->gazipur->id]))->assertSessionHasErrors('district_id');
    $this->actingAs($reporter)->post('/admin/news', lcPayload(['title' => 'পুরো বিভাগ', 'division_id' => $this->dhakaDiv->id]))->assertSessionHasErrors('district_id');
    $this->actingAs($reporter)->post('/admin/news', lcPayload(['title' => 'অন্য উপজেলা', 'upazila_id' => Location::upazilas()->where('parent_id', $this->gazipur->id)->first()->id]))->assertSessionHasErrors('district_id');
    $this->actingAs($reporter)->post('/admin/news', lcPayload(['title' => 'সিলেট', 'district_id' => $this->sylhetDist->id]))->assertSessionHasErrors('district_id');

    expect(News::pluck('title')->sort()->values()->all())->toBe(collect(['নিজ জেলা', 'নিজ উপজেলা'])->sort()->values()->all());
});

it('defaults a correspondent\'s article to their own district when they pick nothing', function () {
    $reporter = User::factory()->reporter()->create();
    $reporter->forceFill(['district_id' => $this->dhakaDist->id])->save();

    $this->actingAs($reporter)->post('/admin/news', lcPayload(['location_present' => 1]))->assertSessionHasNoErrors();

    expect(News::firstOrFail()->location_id)->toBe($this->dhakaDist->id);
});

it('does not let a correspondent smuggle an out-of-district place past the form', function () {
    $reporter = User::factory()->reporter()->create();
    $reporter->forceFill(['district_id' => $this->dhakaDist->id])->save();

    // a request that mixes a valid district with a forged upazila of another district
    $this->actingAs($reporter)->post('/admin/news', lcPayload([
        'district_id' => $this->dhakaDist->id,
        'upazila_id' => Location::upazilas()->where('parent_id', $this->gazipur->id)->first()->id,
    ]))->assertSessionHasErrors('upazila_id');

    expect(News::count())->toBe(0);
});

it('stops a correspondent moving their article out of the district on update', function () {
    $reporter = User::factory()->reporter()->create();
    $reporter->forceFill(['district_id' => $this->dhakaDist->id])->save();
    $news = lcArticle($this->dhakaDist, 'draft', ['author_id' => $reporter->id]);

    $this->actingAs($reporter)->put("/admin/news/{$news->id}", lcPayload(['district_id' => $this->gazipur->id]))
        ->assertSessionHasErrors('district_id');

    expect($news->fresh()->location_id)->toBe($this->dhakaDist->id);
});

it('still lets a correspondent edit an old article whose place is left unchanged after being moved to another district', function () {
    $reporter = User::factory()->reporter()->create();
    $news = lcArticle($this->gazipur, 'draft', ['author_id' => $reporter->id]);

    // later the correspondent is assigned to Dhaka
    $reporter->forceFill(['district_id' => $this->dhakaDist->id])->save();

    $this->actingAs($reporter)->put("/admin/news/{$news->id}", lcPayload(['title' => 'সংশোধিত', 'district_id' => $this->gazipur->id]))
        ->assertSessionHasNoErrors();
    expect($news->fresh()->title)->toBe('সংশোধিত')->and($news->fresh()->location_id)->toBe($this->gazipur->id);

    // ...but they cannot move it to yet another place outside Dhaka
    $this->actingAs($reporter)->put("/admin/news/{$news->id}", lcPayload(['district_id' => $this->sylhetDist->id]))
        ->assertSessionHasErrors('district_id');
});

it('does not restrict reporters without a district, editors or admins', function () {
    $free = User::factory()->reporter()->create();

    foreach ([$free, $this->editor, $this->admin] as $user) {
        $this->actingAs($user)->post('/admin/news', lcPayload(['title' => 'কারও জন্য '.$user->id, 'district_id' => $this->sylhetDist->id]))
            ->assertSessionHasNoErrors();
    }

    expect(News::count())->toBe(3);
});

it('shows a correspondent only their own district in the form hint', function () {
    $reporter = User::factory()->reporter()->create();
    $reporter->forceFill(['district_id' => $this->dhakaDist->id])->save();

    $this->actingAs($reporter)->get('/admin/news/create')->assertOk()->assertSee('জেলার প্রতিনিধি');
    $this->actingAs($this->editor)->get('/admin/news/create')->assertOk()->assertDontSee('জেলার প্রতিনিধি');
});

// --------------------------------------------------------- the resolver itself

it('resolves choices to one place and decides who may use it', function () {
    expect(LocationResolver::resolve(null, null, null)['location'])->toBeNull();

    $resolved = LocationResolver::resolve($this->dhakaDiv->id, $this->dhakaDist->id, $this->savar->id);
    expect($resolved['location']->id)->toBe($this->savar->id)->and($resolved['error'])->toBeNull();

    $correspondent = User::factory()->reporter()->create();
    $correspondent->forceFill(['district_id' => $this->dhakaDist->id])->save();

    expect(LocationResolver::allows($correspondent, $this->dhakaDist))->toBeTrue()
        ->and(LocationResolver::allows($correspondent, $this->savar))->toBeTrue()
        ->and(LocationResolver::allows($correspondent, $this->dhakaDiv))->toBeFalse()
        ->and(LocationResolver::allows($correspondent, $this->gazipur))->toBeFalse()
        ->and(LocationResolver::allows($correspondent, null))->toBeFalse()
        ->and(LocationResolver::allows($this->editor, null))->toBeTrue()
        ->and(LocationResolver::allows(User::factory()->reporter()->create(), $this->sylhetDist))->toBeTrue();
});

// --------------------------------------------------------- assigning correspondents

it('assigns a district to a reporter from the user form', function () {
    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'জেলা প্রতিনিধি', 'email' => 'district@example.com', 'password' => 'Str0ng-Passw0rd!',
        'role' => 'reporter', 'is_active' => 1, 'district_id' => $this->sylhetDist->id,
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'district@example.com')->firstOrFail();
    expect($user->district_id)->toBe($this->sylhetDist->id)->and($user->isDistrictCorrespondent())->toBeTrue();
});

it('drops the district when the role is not reporter, and refuses anything that is not a district', function () {
    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'সম্পাদক', 'email' => 'ed@example.com', 'password' => 'Str0ng-Passw0rd!',
        'role' => 'editor', 'is_active' => 1, 'district_id' => $this->sylhetDist->id,
    ])->assertSessionHasNoErrors();
    expect(User::where('email', 'ed@example.com')->first()->district_id)->toBeNull();

    foreach ([$this->dhakaDiv->id, $this->savar->id, 999999] as $notADistrict) {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'X', 'email' => "x{$notADistrict}@example.com", 'password' => 'Str0ng-Passw0rd!',
            'role' => 'reporter', 'is_active' => 1, 'district_id' => $notADistrict,
        ])->assertSessionHasErrors('district_id');
    }
});

it('clears the district when a reporter is promoted, and lets it be changed or removed', function () {
    $user = User::factory()->reporter()->create();
    $user->forceFill(['district_id' => $this->dhakaDist->id])->save();

    $update = fn (array $extra) => $this->actingAs($this->admin)->put("/admin/users/{$user->id}", array_merge([
        'name' => $user->name, 'email' => $user->email, 'role' => 'reporter', 'is_active' => 1,
    ], $extra))->assertSessionHasNoErrors();

    $update(['district_id' => $this->gazipur->id]);
    expect($user->fresh()->district_id)->toBe($this->gazipur->id);

    $update(['district_id' => '']);
    expect($user->fresh()->district_id)->toBeNull();

    $user->forceFill(['district_id' => $this->dhakaDist->id])->save();
    $update(['role' => 'editor', 'district_id' => $this->dhakaDist->id]);
    expect($user->fresh()->district_id)->toBeNull();
});

it('does not let district_id be set through mass assignment', function () {
    $user = User::create(['name' => 'X', 'email' => 'mass@example.com', 'password' => 'secret-pass-1', 'district_id' => $this->dhakaDist->id]);

    expect($user->fresh()->district_id)->toBeNull();
});

it('renders the user forms with the districts', function () {
    $user = User::factory()->reporter()->create();
    $user->forceFill(['district_id' => $this->gazipur->id])->save();

    $this->actingAs($this->admin)->get('/admin/users/create')->assertOk()->assertSee('রিপোর্টারের এলাকা')->assertSee('গাজীপুর');
    $this->actingAs($this->admin)->get("/admin/users/{$user->id}/edit")->assertOk()
        ->assertSee('data-selected="'.$this->gazipur->id.'"', false);
});

// ------------------------------------------------------------- revisions

it('keeps the place in revisions and restores it, tolerating a vanished place', function () {
    $this->actingAs($this->editor)->post('/admin/news', lcPayload(['title' => 'সংস্করণ', 'district_id' => $this->dhakaDist->id]));
    $news = News::firstOrFail();
    $this->actingAs($this->editor)->put("/admin/news/{$news->id}", lcPayload(['title' => 'সংস্করণ', 'district_id' => $this->sylhetDist->id]));
    expect($news->fresh()->location_id)->toBe($this->sylhetDist->id);

    $first = ArticleRevision::where('news_id', $news->id)->orderBy('id')->first();
    expect($first->snapshot['location_id'])->toBe($this->dhakaDist->id);

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$first->id}/restore");
    expect($news->fresh()->location_id)->toBe($this->dhakaDist->id);

    // a revision pointing at a place that no longer exists leaves the article without one
    $ghost = ArticleRevision::create([
        'news_id' => $news->id,
        'snapshot' => ['title' => 'ভূতুড়ে', 'description' => '<p>x</p>', 'category_id' => $this->category->id, 'location_id' => 999999],
        'created_at' => now(),
    ]);
    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$ghost->id}/restore");
    expect($news->fresh()->location_id)->toBeNull();
});

it('leaves the place alone when restoring a revision that predates locations', function () {
    $news = lcArticle($this->savar, 'draft');
    $legacy = ArticleRevision::create([
        'news_id' => $news->id,
        'snapshot' => ['title' => 'পুরনো', 'description' => '<p>x</p>', 'category_id' => $this->category->id],
        'created_at' => now(),
    ]);

    $this->actingAs($this->editor)->post("/admin/news/{$news->id}/revisions/{$legacy->id}/restore");

    expect($news->fresh()->title)->toBe('পুরনো')->and($news->fresh()->location_id)->toBe($this->savar->id);
});

it('detaches articles from a place that is deleted instead of deleting them', function () {
    $extra = Location::create(['parent_id' => $this->dhakaDist->id, 'type' => 'upazila', 'name_bn' => 'অতিরিক্ত', 'name_en' => 'Extra', 'slug' => 'extra']);
    $news = lcArticle($extra);

    $extra->delete();

    expect(News::find($news->id))->not->toBeNull()->and($news->fresh()->location_id)->toBeNull();
});
