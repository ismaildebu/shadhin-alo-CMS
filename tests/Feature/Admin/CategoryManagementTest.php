<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('creates a category from the form (name only) and generates the slug', function () {
    // Regression: the create form has no slug field, but the controller used to require one.
    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'খেলা'])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasNoErrors();

    $category = Category::firstOrFail();

    expect($category->slug)->not->toBe('')
        ->and($category->status)->toBeTrue()
        ->and($category->serial)->toBe(0);
});

it('rejects duplicate and malformed slugs', function () {
    Category::create(['name' => 'A', 'slug' => 'taken']);

    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'B', 'slug' => 'taken'])
        ->assertSessionHasErrors('slug');
    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'B', 'slug' => 'bad slug<script>'])
        ->assertSessionHasErrors('slug');
});

it('allows updating a category while keeping its own slug', function () {
    $category = Category::create(['name' => 'A', 'slug' => 'a']);

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$category->id}", ['name' => 'A2', 'slug' => 'a', 'serial' => 3, 'status' => 0])
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->name)->toBe('A2')
        ->and($category->serial)->toBe(3)
        ->and($category->status)->toBeFalse();
});

it('refuses to delete a category that still has news', function () {
    $category = Category::create(['name' => 'A', 'slug' => 'a']);
    News::create(['category_id' => $category->id, 'title' => 'T', 'slug' => 't', 'description' => '<p>d</p>']);

    $this->actingAs($this->admin)
        ->delete("/admin/categories/{$category->id}")
        ->assertSessionHasErrors('category');

    expect(Category::count())->toBe(1)->and(News::count())->toBe(1);
});

it('deletes an empty category', function () {
    $category = Category::create(['name' => 'A', 'slug' => 'a']);

    $this->actingAs($this->admin)->delete("/admin/categories/{$category->id}")
        ->assertRedirect(route('categories.index'));

    expect(Category::count())->toBe(0);
});
