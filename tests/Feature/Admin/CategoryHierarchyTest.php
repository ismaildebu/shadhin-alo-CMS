<?php

use App\Models\Activity;
use App\Models\Category;
use App\Models\News;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->parent = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo', 'serial' => 1]);
});

function hcNews(Category $category, string $status = 'published', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => $category->id,
        'title' => "হায়ারার্কি সংবাদ {$n}",
        'slug' => "hc-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinute() : null,
    ], $extra));
}

// ------------------------------------------------------------------ rules

it('creates a sub-category under a top-level parent', function () {
    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'ঢাকা', 'parent_id' => $this->parent->id])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    $child = Category::where('name', 'ঢাকা')->firstOrFail();

    expect($child->parent_id)->toBe($this->parent->id)
        ->and($child->show_in_menu)->toBeTrue()
        ->and($child->slug)->not->toBe('')
        ->and($this->parent->children()->count())->toBe(1);
});

it('allows only one level of sub-categories', function () {
    $child = Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);

    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'মিরপুর', 'parent_id' => $child->id])
        ->assertSessionHasErrors('parent_id');

    expect(Category::where('name', 'মিরপুর')->exists())->toBeFalse();
});

it('does not let a category become its own parent', function () {
    $this->actingAs($this->admin)
        ->put("/admin/categories/{$this->parent->id}", ['name' => 'জাতীয়', 'slug' => 'jatiyo', 'parent_id' => $this->parent->id])
        ->assertSessionHasErrors('parent_id');

    expect($this->parent->fresh()->parent_id)->toBeNull();
});

it('does not let a category with sub-categories become a sub-category itself', function () {
    Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);
    $other = Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti']);

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$this->parent->id}", ['name' => 'জাতীয়', 'slug' => 'jatiyo', 'parent_id' => $other->id])
        ->assertSessionHasErrors('parent_id');

    expect($this->parent->fresh()->parent_id)->toBeNull();
});

it('rejects a parent that does not exist', function () {
    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'ভুয়া', 'parent_id' => 99999])
        ->assertSessionHasErrors('parent_id');
});

it('moves a sub-category to another parent or back to the top level', function () {
    $other = Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti']);
    $child = Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$child->id}", ['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $other->id])
        ->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBe($other->id);

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$child->id}", ['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => ''])
        ->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBeNull();
});

it('keeps the parent when an update does not mention it', function () {
    $child = Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$child->id}", ['name' => 'ঢাকা মহানগর', 'slug' => 'dhaka'])
        ->assertSessionHasNoErrors();

    expect($child->fresh()->parent_id)->toBe($this->parent->id);
});

it('stores menu visibility and SEO fields', function () {
    $this->actingAs($this->admin)->post('/admin/categories', [
        'name' => 'খেলা', 'show_in_menu' => 0, 'serial' => 5,
        'meta_title' => 'খেলার খবর', 'meta_description' => 'সব খেলার সর্বশেষ খবর',
    ])->assertSessionHasNoErrors();

    $category = Category::where('name', 'খেলা')->firstOrFail();

    expect($category->show_in_menu)->toBeFalse()
        ->and($category->serial)->toBe(5)
        ->and($category->meta_title)->toBe('খেলার খবর')
        ->and($category->meta_description)->toBe('সব খেলার সর্বশেষ খবর');

    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'বড় বিবরণ', 'meta_description' => str_repeat('অ', 501)])
        ->assertSessionHasErrors('meta_description');
});

it('orders categories by serial and then id, so equal serials are stable', function () {
    $a = Category::create(['name' => 'এ', 'slug' => 'a', 'serial' => 0]);
    $b = Category::create(['name' => 'বি', 'slug' => 'b', 'serial' => 0]);
    $first = Category::create(['name' => 'প্রথম', 'slug' => 'first', 'serial' => -0 + 0]);
    $this->parent->update(['serial' => 7]);

    expect(Category::ordered()->pluck('slug')->all())->toBe(['a', 'b', 'first', 'jatiyo']);

    $c = Category::create(['name' => 'সি', 'slug' => 'c', 'parent_id' => $this->parent->id, 'serial' => 2]);
    $d = Category::create(['name' => 'ডি', 'slug' => 'd', 'parent_id' => $this->parent->id, 'serial' => 1]);

    expect($this->parent->children()->pluck('slug')->all())->toBe(['d', 'c']);
});

it('includes active sub-categories, and only those, in categoryIds()', function () {
    $active = Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);
    $inactive = Category::create(['name' => 'খুলনা', 'slug' => 'khulna', 'parent_id' => $this->parent->id, 'status' => false]);

    expect($this->parent->categoryIds())->toContain($this->parent->id, $active->id)
        ->not->toContain($inactive->id);
});

// ------------------------------------------------------------ permissions

it('keeps category changes away from reporters and plain users', function () {
    $reporter = User::factory()->reporter()->create();
    $plain = User::factory()->create();

    foreach ([$reporter, $plain] as $user) {
        $this->actingAs($user)->post('/admin/categories', ['name' => 'অনুপ্রবেশ'])->assertForbidden();
        $this->actingAs($user)->put("/admin/categories/{$this->parent->id}", ['name' => 'বদল'])->assertForbidden();
        $this->actingAs($user)->delete("/admin/categories/{$this->parent->id}")->assertForbidden();
    }

    expect(Category::count())->toBe(1)->and($this->parent->fresh()->name)->toBe('জাতীয়');
});

it('lets editors manage categories', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post('/admin/categories', ['name' => 'সম্পাদকের ক্যাটাগরি'])
        ->assertRedirect(route('categories.index'));

    expect(Category::where('name', 'সম্পাদকের ক্যাটাগরি')->exists())->toBeTrue();
});

// --------------------------------------------------------------- deleting

it('refuses to delete a category that has sub-categories', function () {
    Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);

    $this->actingAs($this->admin)->delete("/admin/categories/{$this->parent->id}")
        ->assertSessionHasErrors('category');

    expect(Category::count())->toBe(2);
});

it('refuses to delete a category with articles unless a destination is given', function () {
    hcNews($this->parent);

    $this->actingAs($this->admin)->delete("/admin/categories/{$this->parent->id}")
        ->assertSessionHasErrors('category');

    expect(Category::find($this->parent->id))->not->toBeNull()->and(News::count())->toBe(1);
});

it('rejects an invalid move destination', function () {
    hcNews($this->parent);

    $this->actingAs($this->admin)->delete("/admin/categories/{$this->parent->id}", ['move_to' => $this->parent->id])
        ->assertSessionHasErrors('move_to');
    $this->actingAs($this->admin)->delete("/admin/categories/{$this->parent->id}", ['move_to' => 99999])
        ->assertSessionHasErrors('move_to');

    expect(Category::find($this->parent->id))->not->toBeNull()->and(News::count())->toBe(1);
});

it('moves every article, trashed ones included, to the chosen category and then deletes', function () {
    $target = Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti']);
    $live = hcNews($this->parent);
    $draft = hcNews($this->parent, 'draft');
    $trashed = hcNews($this->parent);
    $trashed->delete();

    $this->actingAs($this->admin)->delete("/admin/categories/{$this->parent->id}", ['move_to' => $target->id])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    expect(Category::find($this->parent->id))->toBeNull()
        ->and($live->fresh()->category_id)->toBe($target->id)
        ->and($draft->fresh()->category_id)->toBe($target->id)
        ->and(News::withTrashed()->find($trashed->id)->category_id)->toBe($target->id)
        ->and(News::withTrashed()->count())->toBe(3);

    $log = Activity::where('action', 'category.deleted')->firstOrFail();
    expect($log->properties)->toMatchArray(['moved_articles' => 3, 'moved_to' => (string) $target->id]);
});

it('deletes an empty category without asking for a destination', function () {
    $this->actingAs($this->admin)->delete("/admin/categories/{$this->parent->id}")
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    expect(Category::count())->toBe(0);
});

it('writes category changes to the audit log', function () {
    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'লগ-ক্যাটাগরি']);
    $category = Category::where('name', 'লগ-ক্যাটাগরি')->firstOrFail();
    $this->actingAs($this->admin)->put("/admin/categories/{$category->id}", ['name' => 'লগ-ক্যাটাগরি', 'status' => 0]);

    expect(Activity::where('action', 'category.created')->count())->toBe(1)
        ->and(Activity::where('action', 'category.updated')->first()->properties['after']['status'])->toBeFalse();
});

// ------------------------------------------------------------ admin pages

it('renders the category tree with sub-categories under their parent', function () {
    Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);
    Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti', 'serial' => 9]);

    $html = $this->actingAs($this->admin)->get('/admin/categories')->assertOk()->getContent();

    expect(strpos($html, 'জাতীয়'))->toBeLessThan(strpos($html, 'ঢাকা'))
        ->and(strpos($html, 'ঢাকা'))->toBeLessThan(strpos($html, 'রাজনীতি'));
    expect($html)->toContain('↳')->toContain('/admin/categories/'.$this->parent->id.'/edit');
});

it('shows an edit link for every category (it used to be unreachable from the list)', function () {
    $this->actingAs($this->admin)->get('/admin/categories')
        ->assertOk()
        ->assertSee("/admin/categories/{$this->parent->id}/edit", false);
});

it('offers the move-and-delete form only where articles exist', function () {
    $empty = Category::create(['name' => 'খালি', 'slug' => 'khali']);
    hcNews($this->parent);

    $html = $this->actingAs($this->admin)->get('/admin/categories')->assertOk()->getContent();

    expect($html)->toContain('সংবাদ সরিয়ে মুছুন')
        ->and(substr_count($html, 'name="move_to"'))->toBe(1);
});

it('renders the create and edit forms, offering only valid parents', function () {
    $child = Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id]);
    $other = Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti']);

    $this->actingAs($this->admin)->get('/admin/categories/create')
        ->assertOk()->assertSee('name="parent_id"', false)->assertSee('রাজনীতি')
        ->assertDontSee('<option value="'.$child->id.'"', false); // a sub-category cannot be a parent

    $edit = $this->actingAs($this->admin)->get("/admin/categories/{$other->id}/edit")->assertOk();
    $edit->assertDontSee('<option value="'.$other->id.'"', false); // not itself

    // a parent that has children cannot itself move under another category
    $this->actingAs($this->admin)->get("/admin/categories/{$this->parent->id}/edit")
        ->assertOk()->assertSee('উপ-ক্যাটাগরি আছে')->assertSee('disabled', false);
});

it('authorizes category requests by capability even without the route middleware', function () {
    $request = fn (User $user) => tap(
        \App\Http\Requests\CategoryRequest::create('/admin/categories', 'POST'),
        fn ($r) => $r->setUserResolver(fn () => $user)
    );

    expect($request(User::factory()->admin()->create())->authorize())->toBeTrue()
        ->and($request(User::factory()->editor()->create())->authorize())->toBeTrue()
        ->and($request(User::factory()->reporter()->create())->authorize())->toBeFalse()
        ->and($request(User::factory()->create())->authorize())->toBeFalse();
});
