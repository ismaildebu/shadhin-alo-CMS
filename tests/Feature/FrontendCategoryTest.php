<?php

use App\Models\Category;
use App\Models\News;

beforeEach(function () {
    $this->parent = Category::create(['name' => "জাতী\u{09DF}", 'slug' => 'jatiyo', 'serial' => 1]);
    $this->child = Category::create(['name' => 'ঢাকা', 'slug' => 'dhaka', 'parent_id' => $this->parent->id, 'serial' => 1]);
    $this->other = Category::create(['name' => 'রাজনীতি', 'slug' => 'rajniti', 'serial' => 2]);
});

function fcNews(Category $category, string $title, string $status = 'published', array $extra = []): News
{
    static $n = 0;
    $n++;

    return News::create(array_merge([
        'category_id' => $category->id,
        'title' => $title,
        'slug' => "fc-news-{$n}",
        'description' => '<p>বিস্তারিত</p>',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinutes(30 - $n) : null,
    ], $extra));
}

// ----------------------------------------------------------------- the page

it('shows a category page with its own articles', function () {
    fcNews($this->other, 'রাজনীতির খবর');
    fcNews($this->parent, 'জাতীয় খবর');

    $this->get('/category/rajniti')
        ->assertOk()
        ->assertSee('রাজনীতির খবর')
        ->assertDontSee('জাতীয় খবর');
});

it('puts articles of a sub-category on the parent page too, labelled with their own category', function () {
    fcNews($this->parent, 'জাতীয় খবর');
    fcNews($this->child, 'ঢাকার খবর');

    $parentPage = $this->get('/category/jatiyo')->assertOk();
    $parentPage->assertSee('জাতীয় খবর')->assertSee('ঢাকার খবর');

    $this->get('/category/dhaka')->assertOk()->assertSee('ঢাকার খবর')->assertDontSee('জাতীয় খবর');
});

it('lists only published articles', function () {
    fcNews($this->parent, 'প্রকাশিত খবর');
    fcNews($this->parent, 'খসড়া খবর', 'draft');
    fcNews($this->parent, 'অপেক্ষমাণ খবর', 'pending');
    fcNews($this->parent, 'আর্কাইভ খবর', 'archived');
    fcNews($this->parent, 'ভবিষ্যতের খবর', 'scheduled', ['published_at' => now()->addDay()]);
    $trashed = fcNews($this->parent, 'ট্র্যাশের খবর');
    $trashed->delete();

    $this->get('/category/jatiyo')
        ->assertOk()
        ->assertSee('প্রকাশিত খবর')
        ->assertDontSee('খসড়া খবর')
        ->assertDontSee('অপেক্ষমাণ খবর')
        ->assertDontSee('আর্কাইভ খবর')
        ->assertDontSee('ভবিষ্যতের খবর')
        ->assertDontSee('ট্র্যাশের খবর');
});

it('leaves out articles of an inactive sub-category', function () {
    $hidden = Category::create(['name' => 'লুকানো', 'slug' => 'hidden', 'parent_id' => $this->parent->id, 'status' => false]);
    fcNews($hidden, 'লুকানো খবর');
    fcNews($this->parent, 'দৃশ্যমান খবর');

    $this->get('/category/jatiyo')->assertOk()->assertSee('দৃশ্যমান খবর')->assertDontSee('লুকানো খবর');
});

it('returns 404 for unknown, inactive and orphaned categories', function () {
    $this->get('/category/does-not-exist')->assertNotFound();

    $this->other->update(['status' => false]);
    $this->get('/category/rajniti')->assertNotFound();

    $this->parent->update(['status' => false]);
    $this->get('/category/jatiyo')->assertNotFound();
    $this->get('/category/dhaka')->assertNotFound(); // child of an inactive parent
});

it('paginates, newest first', function () {
    foreach (range(1, 13) as $i) {
        fcNews($this->other, "পেজিনেশন খবর {$i}", 'published', ['published_at' => now()->subHours(20 - $i)]);
    }

    $first = $this->get('/category/rajniti')->assertOk();
    $first->assertSee('পেজিনেশন খবর 13')->assertSee('page=2', false)->assertDontSee('পেজিনেশন খবর 1<', false);

    $this->get('/category/rajniti?page=2')->assertOk()->assertSee('পেজিনেশন খবর 1')->assertDontSee('পেজিনেশন খবর 13');
});

it('shows sub-category chips on a parent page and a breadcrumb on the child page', function () {
    $this->get('/category/jatiyo')->assertOk()->assertSee('/category/dhaka', false);

    $child = $this->get('/category/dhaka')->assertOk();
    $child->assertSee('breadcrumb', false)->assertSee('/category/jatiyo', false);
});

it('uses the SEO title and description when set, and the name otherwise', function () {
    $this->other->update(['meta_title' => 'রাজনীতির সর্বশেষ', 'meta_description' => 'দেশের রাজনীতির খবর']);

    $this->get('/category/rajniti')
        ->assertOk()
        ->assertSee('<title>রাজনীতির সর্বশেষ | স্বাধীন আলো</title>', false)
        ->assertSee('<meta name="description" content="দেশের রাজনীতির খবর">', false);

    $this->get('/category/jatiyo')
        ->assertSee('<title>'.$this->parent->name.' | স্বাধীন আলো</title>', false)
        ->assertDontSee('<meta name="description"', false);
});

it('escapes category names and SEO fields', function () {
    $evil = Category::create([
        'name' => '<script>alert(1)</script>', 'slug' => 'evil',
        'meta_title' => '"><script>alert(2)</script>', 'meta_description' => '"><script>alert(3)</script>',
    ]);

    $this->get('/category/evil')->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertDontSee('<script>alert(3)</script>', false);
});

// --------------------------------------------------------------------- menu

it('builds the menu with real links instead of "#" placeholders', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('/category/jatiyo')->toContain('/category/rajniti');
});

it('lists sub-categories in a dropdown under their parent', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // "জাতীয়" is a main-menu item; its child sits inside the dropdown that follows it
    $parentPos = strpos($html, 'href="http://localhost/category/jatiyo"');
    $childPos = strpos($html, 'href="http://localhost/category/dhaka"');

    expect($parentPos)->not->toBeFalse()->and($childPos)->toBeGreaterThan($parentPos);
    expect(substr($html, $parentPos, $childPos - $parentPos))->toContain('dropdown-menu');
});

it('hides inactive and "not in menu" categories from the menu', function () {
    $this->other->update(['show_in_menu' => false]);
    $this->child->update(['status' => false]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('/category/rajniti')->not->toContain('/category/dhaka');

    // ...but a "not in menu" category's page still works
    $this->get('/category/rajniti')->assertOk();
});

it('hides the children of an inactive parent from the menu', function () {
    $this->parent->update(['status' => false]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('/category/jatiyo')->not->toContain('/category/dhaka');
});

it('puts categories outside the main menu list under "অন্যান্য", with their sub-categories', function () {
    $misc = Category::create([
    'name' => 'সাহিত্য',
    'slug' => 'sahitya',
    'serial' => 50,
    'menu_serial' => 500,
    'menu_more' => true,
]);
    Category::create(['name' => 'কবিতা', 'slug' => 'kobita', 'parent_id' => $misc->id]);

    $html = $this->get('/')->assertOk()->getContent();
    $others = strpos($html, 'অন্যান্য');

    expect($others)->not->toBeFalse()
        ->and(strpos($html, '/category/sahitya', $others))->not->toBeFalse()
        ->and(strpos($html, '/category/kobita', $others))->not->toBeFalse();
});

it('updates the menu immediately when a category changes (cache is flushed)', function () {
    $this->get('/')->assertOk()->assertDontSee('/category/new-one', false);

    Category::create(['name' => 'নতুন', 'slug' => 'new-one', 'serial' => 3]);
    $this->get('/')->assertSee('/category/new-one', false);

    Category::where('slug', 'new-one')->first()->update(['show_in_menu' => false]);
    $this->get('/')->assertDontSee('/category/new-one', false);
});

it('orders menu items by serial', function () {
    $html = $this->get('/')->getContent();

    expect(strpos($html, '/category/jatiyo'))->toBeLessThan(strpos($html, '/category/rajniti'));

    $this->parent->update(['menu_serial' => 99]);
    $html = $this->get('/')->getContent();

    expect(strpos($html, '/category/rajniti'))->toBeLessThan(strpos($html, '/category/jatiyo'));
});

// -------------------------------------------------------------- home blocks

it('shows a sub-category\'s news in its parent\'s home block', function () {
    // The child's article is OLD. Twenty newer articles in another category push it out of every
    // "latest" list on the home page, so it can only appear through the parent's category block.
    fcNews($this->child, 'কেবল-ব্লকে-থাকা-ঢাকার-খবর', 'published', ['published_at' => now()->subDays(10)]);

    foreach (range(1, 20) as $i) {
        fcNews($this->other, "নতুন রাজনীতির খবর {$i}", 'published', ['published_at' => now()->subMinutes($i)]);
    }

    $this->get('/')->assertOk()->assertSee('কেবল-ব্লকে-থাকা-ঢাকার-খবর');
});

it('keeps the home page working when sections reference a category that became a sub-category', function () {
    // the "national" section looks up the category with slug "jatiyo"
    $root = Category::create(['name' => 'সারাদেশ', 'slug' => 'saradesh-root']);
    $this->parent->update(['parent_id' => null]);
    Category::where('slug', 'jatiyo')->update(['parent_id' => $this->other->id]);
    Category::where('slug', 'dhaka')->update(['parent_id' => $this->other->id]);

    fcNews(Category::where('slug', 'jatiyo')->first(), 'জাতীয় সেকশনের খবর');

    $this->get('/')->assertOk();
});

it('keeps a category in the main menu whichever way its name was typed', function () {
    // the header's list spells জাতীয় with the single character য় (U+09DF); an admin's keyboard may produce য + nukta
    $typedTwoChars = "জাতী\u{09AF}\u{09BC}";
    $typedOneChar = "জাতী\u{09DF}";

    foreach ([$typedTwoChars, $typedOneChar] as $i => $name) {
        $this->parent->update(['name' => $name]);

        $html = $this->get('/')->assertOk()->getContent();
        $firstMenuItem = strpos($html, '/category/jatiyo');
        $others = strpos($html, 'অন্যান্য');

expect($firstMenuItem)->not->toBeFalse(
    "variant {$i} disappeared from the menu"
);

if ($others !== false) {
    expect($firstMenuItem)->toBeLessThan(
        $others,
        "variant {$i} was demoted to অন্যান্য"
    );
}
    }
});
