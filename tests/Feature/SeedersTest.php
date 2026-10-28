<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;

it('seeds every category slug that the home page sections look up', function () {
    $this->seed(CategorySeeder::class);

    $wanted = [];
    foreach (glob(resource_path('views/frontend/sections/*.blade.php')) as $file) {
        if (preg_match("/firstWhere\\('slug', '([^']+)'\\)/", file_get_contents($file), $m)) {
            $wanted[basename($file)] = $m[1];
        }
    }

    expect($wanted)->not->toBeEmpty();

    foreach ($wanted as $file => $slug) {
        expect(Category::where('slug', $slug)->exists())->toBeTrue("{$file} looks for slug '{$slug}', which the seeder does not create");
    }
});

it('seeds readable slugs and a deterministic menu order', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(count(CategorySeeder::CATEGORIES))
        ->and(Category::pluck('slug')->every(fn ($slug) => preg_match('/^[a-z0-9-]+$/', $slug) === 1))->toBeTrue()
        ->and(Category::ordered()->first()->slug)->toBe('jatiyo')
        ->and(Category::pluck('serial')->unique())->toHaveCount(count(CategorySeeder::CATEGORIES));
});

it('can be run twice without duplicating categories or undoing admin edits', function () {
    $this->seed(CategorySeeder::class);
    Category::where('slug', 'jatiyo')->update(['name' => 'জাতীয় সংবাদ', 'serial' => 42]);

    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(count(CategorySeeder::CATEGORIES))
        ->and(Category::where('slug', 'jatiyo')->first()->name)->toBe('জাতীয় সংবাদ')
        ->and(Category::where('slug', 'jatiyo')->first()->serial)->toBe(42);
});

it('renders a home page with category sections on a freshly seeded database', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(\Database\Seeders\NewsSeeder::class);

    $html = $this->get('/')->assertOk()->getContent();

    // the main menu links use the readable slugs
    expect($html)->toContain('/category/jatiyo')->toContain('/category/business')->not->toContain('orth-banijz');
});
