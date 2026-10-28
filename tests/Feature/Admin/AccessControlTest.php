<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;

$adminUrls = [
    'dashboard' => '/dashboard',
    'news index' => '/admin/news',
    'news create' => '/admin/news/create',
    'categories index' => '/admin/categories',
    'categories create' => '/admin/categories/create',
];

it('redirects guests to the login page', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with($adminUrls);

it('forbids authenticated users without a CMS role', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with($adminUrls);

$staffUrls = [
    'dashboard' => '/dashboard',
    'news index' => '/admin/news',
    'news create' => '/admin/news/create',
];
$managerUrls = [
    'categories index' => '/admin/categories',
    'categories create' => '/admin/categories/create',
];
$adminOnlyUrls = [
    'users index' => '/admin/users',
    'users create' => '/admin/users/create',
    'activity log' => '/admin/activity',
];

it('lets every staff role into the news workspace', function (string $url) {
    foreach (['admin', 'editor', 'reporter'] as $role) {
        $this->actingAs(User::factory()->{$role}()->create())->get($url)->assertOk();
    }
})->with($staffUrls);

it('keeps categories for editors and admins only', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->editor()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->reporter()->create())->get($url)->assertForbidden();
})->with($managerUrls);

it('keeps users and the audit log for admins only', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->editor()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->reporter()->create())->get($url)->assertForbidden();
})->with($adminOnlyUrls);

it('locks deactivated staff out of the panel immediately', function (string $url) {
    $this->actingAs(User::factory()->admin()->inactive()->create())->get($url)->assertForbidden();
})->with($staffUrls);

it('hides links a role may not use', function () {
    $this->actingAs(User::factory()->reporter()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('/admin/categories')
        ->assertDontSee('/admin/users');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('/admin/categories')
        ->assertSee('/admin/users');
});

it('does not let a plain user write, update or delete', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'A', 'slug' => 'a']);
    $news = News::create([
        'category_id' => $category->id, 'title' => 'T', 'slug' => 't', 'description' => '<p>d</p>',
    ]);

    $this->actingAs($user)->post('/admin/news', ['title' => 'x'])->assertForbidden();
    $this->actingAs($user)->put("/admin/news/{$news->id}", ['title' => 'x'])->assertForbidden();
    $this->actingAs($user)->delete("/admin/news/{$news->id}")->assertForbidden();
    $this->actingAs($user)->delete("/admin/categories/{$category->id}")->assertForbidden();

    expect(News::count())->toBe(1)->and(Category::count())->toBe(1);
});

it('keeps role from being mass assigned', function () {
    $user = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'role' => 'admin']);

    expect($user->fresh()->role)->toBe('user');
});

it('lets any authenticated user use their own profile page', function () {
    $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
});
