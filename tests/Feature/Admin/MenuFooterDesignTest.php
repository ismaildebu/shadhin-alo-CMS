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
        'menu_accent' => '#ff0000',
        'menu_size' => 'lg',
        'menu_align' => 'left',
        'menu_sticky' => '0',
        'menu_gap' => 'wide',
        'menu_radius' => 'pill',
        'menu_weight' => '700',
        'menu_hover_style' => 'underline',
        'menu_height' => 'tall',
        'menu_top_line' => 'bold',
        'menu_shadow' => 'strong',

        'drop_bg' => '#111111',
        'drop_text' => '#eeeeee',
        'drop_hover_bg' => '#333333',
        'drop_radius' => 'round',
        'drop_width' => 'wide',

        'footer_bg' => '#303030',
        'footer_text' => '#dddddd',
        'footer_heading' => '#fafafa',
        'footer_accent' => '#00aa00',
        'footer_hover' => '#00ff00',
        'footer_bottom_bg' => '#202020',
        'footer_size' => 'sm',
        'footer_pad' => 'slim',
        'footer_gap' => 'wide',
        'footer_top_line' => 'hair',
        'social_shape' => 'square',
    ], $overrides);
}

it('shows the menu and footer design page to layout managers', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('menu-footer-design.edit'))
        ->assertOk()
        ->assertSee('মেনু ও ফুটারের ডিজাইন')
        ->assertSee('লাইভ প্রিভিউ')
        ->assertSee('প্রস্তুত থিম')
        ->assertSee('ড্রপডাউন ও মেগা মেনু');
});

it('renders every option the design stores', function () {
    $page = $this->actingAs(User::factory()->admin()->create())
        ->get(route('menu-footer-design.edit'))
        ->assertOk();

    foreach (MenuFooterDesign::LABELS as $label) {
        $page->assertSee($label);
    }
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
        'menu_accent' => '#ff0000',
        'menu_size' => 'lg',
        'menu_align' => 'left',
        'menu_sticky' => '0',
        'menu_gap' => 'wide',
        'menu_hover_style' => 'underline',
        'drop_width' => 'wide',
        'footer_bg' => '#303030',
        'footer_pad' => 'slim',
        'social_shape' => 'square',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('#101010 55%')
        ->toContain('border-top:6px solid #ff0000')
        ->toContain('position:static')
        ->toContain('justify-content:flex-start !important')
        ->toContain('gap:16px')
        ->toContain('padding:12px 0')
        ->toContain('font-size:17px')
        ->toContain('font-weight:700')
        ->toContain('border-radius:999px')
        ->toContain('box-shadow:0 14px 38px rgba(8,20,35,.28)')
        ->toContain('inset 0 -2px 0 #ff0000')
        ->toContain('background:#111111')
        ->toContain('min-width:280px')
        ->toContain('color:#eeeeee')
        ->toContain('background:#303030')
        ->toContain('border-top:2px solid #00aa00')
        ->toContain('padding-top:24px !important')
        ->toContain('gap:20px 16px')
        ->toContain('font-size:14px')
        ->toContain('.sa-footer-bottom{background:#202020');
});

it('rejects an invalid color and keeps the saved design', function () {
    Settings::set(['menu_bg' => '#112233']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('menu-footer-design.update'), menuFooterPayload(['menu_bg' => 'red']))
        ->assertSessionHasErrors('menu_bg');

    expect(Settings::get('menu_bg'))->toBe('#112233');
});

it('rejects a choice that is not on the whitelist', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('menu-footer-design.update'), menuFooterPayload(['menu_gap' => 'huge', 'drop_width' => '9999']))
        ->assertSessionHasErrors(['menu_gap', 'drop_width']);
});

it('keeps the current look as the default design', function () {
    expect(MenuFooterDesign::all()['opt'])->toMatchArray([
        'menu_bg' => '#123b68',
        'menu_text' => '#ffffff',
        'menu_hover_bg' => '#0b2745',
        'menu_accent' => '#d60000',
        'menu_sticky' => '1',
        'menu_gap' => 'tight',
        'menu_radius' => 'soft',
        'menu_weight' => '600',
        'menu_hover_style' => 'fill',
        'menu_top_line' => 'thin',
        'menu_shadow' => 'soft',
        'drop_bg' => '#ffffff',
        'drop_radius' => 'soft',
        'footer_bg' => '#0b2745',
        'footer_text' => '#cfd8e3',
        'footer_accent' => '#d60000',
        'footer_pad' => 'normal',
        'footer_top_line' => 'normal',
        'social_shape' => 'pill',
    ]);
});

it('falls back to the default when a stored choice is no longer offered', function () {
    Settings::set(['menu_hover_style' => 'slant', 'menu_bg' => 'not-a-color']);

    expect(MenuFooterDesign::all()['opt'])
        ->toMatchArray(['menu_hover_style' => 'fill', 'menu_bg' => '#123b68']);
});

it('resets the whole design back to the defaults', function () {
    Settings::set(['menu_bg' => '#101010', 'footer_pad' => 'slim']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('menu-footer-design.reset'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(MenuFooterDesign::all()['opt'])
        ->toMatchArray(['menu_bg' => '#123b68', 'footer_pad' => 'normal']);
});
