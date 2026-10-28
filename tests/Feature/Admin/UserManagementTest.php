<?php

use App\Models\Activity;
use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'নতুন রিপোর্টার',
        'email' => 'new.reporter@example.com',
        'password' => 'Str0ng-Passw0rd!',
        'role' => 'reporter',
        'is_active' => 1,
        'phone' => '+880 1700-000000',
        'bio' => 'ঢাকা প্রতিনিধি',
    ], $overrides);
}

it('creates a user with a hashed password, the chosen role and a verified email', function () {
    $this->actingAs($this->admin)->post('/admin/users', userPayload())
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'new.reporter@example.com')->firstOrFail();

    expect($user->role)->toBe('reporter')
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Str0ng-Passw0rd!', $user->password))->toBeTrue()
        ->and(Activity::where('action', 'user.created')->exists())->toBeTrue();
});

it('validates new users', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->admin)->post('/admin/users', userPayload(['email' => 'taken@example.com']))->assertSessionHasErrors('email');
    $this->actingAs($this->admin)->post('/admin/users', userPayload(['password' => 'short']))->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post('/admin/users', userPayload(['password' => '']))->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post('/admin/users', userPayload(['role' => 'superadmin']))->assertSessionHasErrors('role');
    $this->actingAs($this->admin)->post('/admin/users', userPayload(['phone' => 'call me <script>']))->assertSessionHasErrors('phone');

    expect(User::count())->toBe(2); // the admin + the one "taken" user
});

it('forbids non-admins from creating, editing or deleting users', function () {
    $victim = User::factory()->reporter()->create();

    foreach (['editor', 'reporter'] as $role) {
        $actor = User::factory()->{$role}()->create();
        $this->actingAs($actor)->post('/admin/users', userPayload(['email' => "x-{$role}@example.com"]))->assertForbidden();
        $this->actingAs($actor)->put("/admin/users/{$victim->id}", userPayload(['role' => 'admin']))->assertForbidden();
        $this->actingAs($actor)->delete("/admin/users/{$victim->id}")->assertForbidden();
    }

    expect($victim->fresh()->role)->toBe('reporter');
});

it('changes a role and keeps the password when the field is left blank', function () {
    $user = User::factory()->reporter()->create(['password' => 'Old-Passw0rd!']);
    $hash = $user->password;

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", userPayload([
        'email' => $user->email, 'role' => 'editor', 'password' => '',
    ]))->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->role)->toBe('editor')->and($user->password)->toBe($hash);
});

it('changes the password when one is given', function () {
    $user = User::factory()->reporter()->create();

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", userPayload([
        'email' => $user->email, 'password' => 'Brand-New-Pass1!',
    ]))->assertSessionHasNoErrors();

    expect(Hash::check('Brand-New-Pass1!', $user->fresh()->password))->toBeTrue();
});

it('does not let an admin change their own role or deactivate themselves', function () {
    // A second active admin exists, so only the "not yourself" rule can be what stops this.
    User::factory()->admin()->create();

    $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", userPayload([
        'email' => $this->admin->email, 'role' => 'reporter',
    ]))->assertSessionHasErrors('role');

    $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", userPayload([
        'email' => $this->admin->email, 'role' => 'admin', 'is_active' => 0,
    ]))->assertSessionHasErrors('role');

    expect($this->admin->fresh()->role)->toBe('admin')->and($this->admin->fresh()->is_active)->toBeTrue();
});

it('never leaves the system without an active admin', function () {
    // Exercise the guard directly: demoting / deactivating / deleting the only active admin is refused.
    $controller = new \App\Http\Controllers\Admin\UserController();
    $guard = new ReflectionMethod($controller, 'wouldRemoveLastAdmin');

    // $this->admin is the only active admin
    expect($guard->invoke($controller, $this->admin, 'editor', true))->toBeTrue()
        ->and($guard->invoke($controller, $this->admin, 'admin', false))->toBeTrue()
        ->and($guard->invoke($controller, $this->admin, null, false))->toBeTrue()
        ->and($guard->invoke($controller, $this->admin, 'admin', true))->toBeFalse();

    // with a second active admin, changing the first one is fine
    User::factory()->admin()->create();
    expect($guard->invoke($controller, $this->admin, 'editor', true))->toBeFalse();

    // an inactive extra admin does not count
    User::where('id', '!=', $this->admin->id)->update(['is_active' => false]);
    expect($guard->invoke($controller, $this->admin, 'editor', true))->toBeTrue();
});

it('does not let an admin delete themselves', function () {
    User::factory()->admin()->create(); // so the last-admin rule cannot be what stops it

    $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")->assertSessionHasErrors('user');

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('refuses to delete a user who has articles, trashed ones included', function () {
    $category = Category::create(['name' => 'A', 'slug' => 'a']);
    $reporter = User::factory()->reporter()->create();
    $news = News::create(['category_id' => $category->id, 'author_id' => $reporter->id, 'title' => 'T', 'slug' => 't', 'description' => '<p>d</p>']);
    $news->delete();

    $this->actingAs($this->admin)->delete("/admin/users/{$reporter->id}")->assertSessionHasErrors('user');

    expect(User::find($reporter->id))->not->toBeNull();
});

it('deletes a user without articles and logs it', function () {
    $user = User::factory()->reporter()->create(['email' => 'gone@example.com']);

    $this->actingAs($this->admin)->delete("/admin/users/{$user->id}")->assertRedirect(route('users.index'));

    expect(User::find($user->id))->toBeNull()
        ->and(Activity::where('action', 'user.deleted')->first()->properties['email'])->toBe('gone@example.com');
});

it('lets a second admin demote another admin while one admin always remains', function () {
    $second = User::factory()->admin()->create();

    $this->actingAs($this->admin)->put("/admin/users/{$second->id}", userPayload([
        'email' => $second->email, 'role' => 'editor',
    ]))->assertSessionHasNoErrors();

    expect($second->fresh()->role)->toBe('editor')
        ->and(User::where('role', 'admin')->where('is_active', true)->count())->toBeGreaterThanOrEqual(1);
});

it('blocks deactivated users from logging in, even with the right password', function () {
    $user = User::factory()->reporter()->inactive()->create(['password' => 'Right-Passw0rd!']);

    $this->post('/login', ['email' => $user->email, 'password' => 'Right-Passw0rd!'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('lets an active reporter log in and reach the dashboard', function () {
    $user = User::factory()->reporter()->create(['password' => 'Right-Passw0rd!']);

    $this->post('/login', ['email' => $user->email, 'password' => 'Right-Passw0rd!']);

    $this->assertAuthenticatedAs($user);
    $this->get('/dashboard')->assertOk();
});

it('searches users by name or email', function () {
    User::factory()->reporter()->create(['name' => 'রহিম উদ্দিন', 'email' => 'rahim@example.com']);
    User::factory()->reporter()->create(['name' => 'করিম মিয়া', 'email' => 'karim@example.com']);

    $this->actingAs($this->admin)->get('/admin/users?q=rahim')
        ->assertOk()
        ->assertSee('rahim@example.com')
        ->assertDontSee('karim@example.com');
});

it('logs role changes with before and after values', function () {
    $user = User::factory()->reporter()->create();

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", userPayload(['email' => $user->email, 'role' => 'editor']));

    $log = Activity::where('action', 'user.updated')->firstOrFail();

    expect($log->properties['before']['role'])->toBe('reporter')
        ->and($log->properties['after']['role'])->toBe('editor');
});
