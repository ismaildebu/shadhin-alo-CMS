<?php

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // No feeds: the route runs without touching the network.
    config(['news_import.sources' => []]);
    Permissions::flush();
    $this->admin = User::factory()->admin()->create();
});

it('runs one category group from its sidebar button', function () {
    $this->actingAs($this->admin)
        ->post(route('news.import', 'sports'))
        ->assertRedirect(route('news.index'))
        ->assertSessionHas('success');
});

it('shows a button for every import group in the sidebar', function () {
    $response = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    foreach ((array) config('news_import.groups') as $key => $group) {
        $response->assertSee(route('news.import', $key), false)
            ->assertSee($group['label']);
    }

    $response->assertSee('নতুন সংবাদ আনুন');
});

it('rejects an unknown import group', function () {
    $this->actingAs($this->admin)
        ->post(route('news.import', 'no-such-group'))
        ->assertNotFound();
});

it('refuses when collection is disabled or already running', function () {
    config(['news_import.enabled' => false]);

    $this->actingAs($this->admin)
        ->post(route('news.import'))
        ->assertSessionHasErrors('import');

    config(['news_import.enabled' => true]);

    $lock = Cache::lock('news-import', 60);
    expect($lock->get())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('news.import'))
        ->assertSessionHasErrors('import');

    $lock->release();
});

it('requires an authenticated user with panel access', function () {
    $this->post(route('news.import'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->post(route('news.import'))
        ->assertForbidden();
});

it('hides the import buttons and refuses the route without the news_import permission', function () {
    $reporter = User::factory()->reporter()->create();

    $this->actingAs($reporter)->get('/dashboard')->assertOk()
        ->assertDontSee('নতুন সংবাদ আনুন')
        ->assertDontSee(route('news.import', 'sports'), false);

    $this->actingAs($reporter)
        ->post(route('news.import', 'sports'))
        ->assertForbidden();
});

it('lets a role import once news_import is granted', function () {
    Permissions::saveRole('reporter', ['cms_access' => true, 'news_import' => true]);

    $reporter = User::factory()->reporter()->create();

    $this->actingAs($reporter)->get('/dashboard')->assertOk()
        ->assertSee('নতুন সংবাদ আনুন')
        ->assertSee(route('news.import', 'sports'), false);

    $this->actingAs($reporter)
        ->post(route('news.import', 'sports'))
        ->assertRedirect(route('news.index'))
        ->assertSessionHas('success');
});

it('grants news_import to editors but not reporters by default', function () {
    expect(Permissions::roleAllows(User::ROLE_EDITOR, 'news_import'))->toBeTrue()
        ->and(Permissions::roleAllows(User::ROLE_REPORTER, 'news_import'))->toBeFalse();
});
