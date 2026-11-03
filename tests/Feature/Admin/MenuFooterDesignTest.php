<?php

use App\Models\User;
use App\Support\MenuFooterDesign;
use App\Support\Settings;

// Settings are cached with Cache::forever and survive the per-test DB rollback.
beforeEach(fn () => Settings::set([]));

function menuFooterPayload(array $overrides = []): array
{
    return array_merge([
        'menu_bg' => '#101010',
        'menu_text' => '#fefefe',
        'menu_hover_bg' => '#202020',
        'menu_size' => 'lg',
        'menu_align' => 'left',
        'menu_sticky' => '0',
        'footer_bg' => '#303030',
        'footer_text' => '#dddddd',
        'footer_heading' => '#fafafa',
        'footer_accent' => '#00aa00',
        'footer_hover' => '#00ff00',
        'footer_size' => 'sm',
    ], $overrides);
}

it('shows the menu and footer design page to layout managers', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('menu-footer-design.edit'))
        ->assertOk()
        ->assertSee('মেনু ও ফুটারের ডিজাইন')
        ->assertSee('লাইভ প্রিভিউ');
});

it('keeps the design page away from users without layout permission', function () {
    $this->actingAs(User::factory()->reporter()->create())
        ->get(route('menu-footer-design.edit'))
        ->assertForbidden();

    $this->actingAs(User::factory()->reporter()->create())
        ->put(route('menu-footer-design.update'), menuFooterPayload())
        ->assertForbidden();
});

it('saves the design and applies it to the public site', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('menu-footer-design.update'), menuFooterPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(MenuFooterDesign::all()['opt'])->toMatchArray([
        'menu_bg' => '#101010',
        'menu_size' => 'lg',
        'menu_align' => 'left',
        'menu_sticky' => '0',
        'footer_bg' => '#303030',
        'footer_size' => 'sm',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('linear-gradient(180deg')
        ->toContain('#101010 55%')
        ->toContain('border-top:3px solid #00aa00')
        ->toContain('justify-content:flex-start !important')
        ->toContain('position:static')
        ->toContain('font-size:17px')
        ->toContain('background:#303030')
        ->toContain('border-top-color:#00aa00')
        ->toContain('font-size:14px');
});

it('rejects an invalid color and keeps the saved design', function () {
    Settings::set(['menu_bg' => '#112233']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('menu-footer-design.update'), menuFooterPayload(['menu_bg' => 'red']))
        ->assertSessionHasErrors('menu_bg');

    expect(Settings::get('menu_bg'))->toBe('#112233');
});

it('keeps the current look as the default design', function () {
    expect(MenuFooterDesign::all()['opt'])->toMatchArray([
        'menu_bg' => '#123b68',
        'menu_text' => '#ffffff',
        'menu_hover_bg' => '#0b2745',
        'menu_sticky' => '1',
        'footer_bg' => '#0b2745',
        'footer_text' => '#cfd8e3',
        'footer_accent' => '#d60000',
    ]);
});
