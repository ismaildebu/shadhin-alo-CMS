<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // No feeds: the route runs without touching the network.
    config(['news_import.sources' => []]);
    $this->admin = User::factory()->admin()->create();
});

it('runs the importer from the sidebar button', function () {
    $this->actingAs($this->admin)
        ->post(route('news.import'))
        ->assertRedirect(route('news.index'))
        ->assertSessionHas('success');
});

it('shows the import button in the sidebar', function () {
    $this->actingAs($this->admin)->get('/dashboard')
        ->assertOk()
        ->assertSee('নতুন সংবাদ আনুন')
        ->assertSee(route('news.import'), false);
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
