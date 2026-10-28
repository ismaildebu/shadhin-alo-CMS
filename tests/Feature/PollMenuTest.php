<?php

use App\Models\User;

it('shows the online vote link in the admin menu', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="'.route('admin.polls.index').'"', false)
        ->assertSee('অনলাইন ভোট');
});

it('keeps the link away from users who cannot manage the poll pages', function () {
    $this->actingAs(User::factory()->reporter()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.polls.index'), false);
});

it('marks the menu entry as active on the poll pages', function () {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.polls.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('~ad-link is-active[^>]*>\s*<svg[^>]*>.*?</svg>\s*অনলাইন ভোট~su');
});
