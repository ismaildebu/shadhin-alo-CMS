<?php

use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin with a hashed password', function () {
    $this->artisan('cms:create-admin', ['email' => 'Boss@Example.com', '--name' => 'Boss'])
        ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
        ->expectsQuestion('Confirm password', 'Str0ng-Passw0rd!')
        ->assertSuccessful();

    $user = User::where('email', 'boss@example.com')->firstOrFail();

    expect($user->role)->toBe('admin')
        ->and($user->canManageCms())->toBeTrue()
        ->and(Hash::check('Str0ng-Passw0rd!', $user->password))->toBeTrue();
});

it('refuses weak or mismatching passwords', function () {
    $this->artisan('cms:create-admin', ['email' => 'boss@example.com'])
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Confirm password', 'different')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('promotes an existing user to admin', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    $this->artisan('cms:create-admin', ['email' => 'staff@example.com'])
        ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
        ->expectsQuestion('Confirm password', 'Str0ng-Passw0rd!')
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe('admin');
});

it('sanitizes legacy articles and supports --dry-run', function () {
    $category = Category::create(['name' => 'A', 'slug' => 'a']);
    $news = News::create([
        'category_id' => $category->id, 'title' => 'T', 'slug' => 't',
        'description' => '<p>ok</p><script>alert(1)</script>',
    ]);

    $this->artisan('cms:sanitize-news', ['--dry-run' => true])->assertSuccessful();
    expect($news->fresh()->description)->toContain('<script>');

    $this->artisan('cms:sanitize-news')->assertSuccessful();
    expect($news->fresh()->description)->toBe('<p>ok</p>');
});
