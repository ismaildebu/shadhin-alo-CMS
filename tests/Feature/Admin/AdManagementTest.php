<?php

use App\Models\Ad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Never write uploads into the real public/ directory during tests.
    $this->publicDir = sys_get_temp_dir().'/shadhinalo-ads-test-'.uniqid();
    File::makeDirectory($this->publicDir.'/uploads/ads', 0755, true);
    $this->app->usePublicPath($this->publicDir);

    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    File::deleteDirectory($this->publicDir);
});

function adPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Test advertisement',
        'slot' => 'sidebar_top',
        'type' => 'image',
        'image' => UploadedFile::fake()->image('ad.jpg'),
        'link_url' => 'https://example.com',
        'status' => '1',
    ], $overrides);
}

it('creates an image advertisement and stores its uploaded file', function () {
    $this->actingAs($this->admin)
        ->post('/admin/ads', adPayload())
        ->assertRedirect(route('ads.index'))
        ->assertSessionHasNoErrors();

    $ad = Ad::firstOrFail();

    expect($ad->title)->toBe('Test advertisement')
        ->and($ad->slot)->toBe('sidebar_top')
        ->and($ad->type)->toBe('image')
        ->and($ad->created_by)->toBe($this->admin->id)
        ->and($ad->image)->toMatch('/^[0-9a-f-]{36}\.jpg$/')
        ->and(File::exists($this->publicDir.'/uploads/ads/'.$ad->image))->toBeTrue();
});

it('rejects a non-image upload without creating an advertisement', function () {
    $this->actingAs($this->admin)
        ->post('/admin/ads', adPayload([
            'image' => UploadedFile::fake()->create(
                'shell.php',
                10,
                'application/x-php'
            ),
        ]))
        ->assertSessionHasErrors('image');

    expect(Ad::count())->toBe(0);
});

it('requires an image when creating an image advertisement', function () {
    $this->actingAs($this->admin)
        ->post('/admin/ads', adPayload(['image' => null]))
        ->assertSessionHasErrors('image');

    expect(Ad::count())->toBe(0);
});

it('replaces an image and deletes the previous file', function () {
    $this->actingAs($this->admin)
        ->post('/admin/ads', adPayload())
        ->assertRedirect(route('ads.index'));

    $ad = Ad::firstOrFail();
    $oldImage = $ad->image;
    $oldPath = $this->publicDir.'/uploads/ads/'.$oldImage;

    expect(File::exists($oldPath))->toBeTrue();

    $this->actingAs($this->admin)
        ->put("/admin/ads/{$ad->id}", adPayload([
            'title' => 'Updated advertisement',
            'image' => UploadedFile::fake()->image('replacement.png'),
        ]))
        ->assertRedirect(route('ads.index'))
        ->assertSessionHasNoErrors();

    $updated = $ad->fresh();
    $newPath = $this->publicDir.'/uploads/ads/'.$updated->image;

    expect($updated->title)->toBe('Updated advertisement')
        ->and($updated->image)->not->toBe($oldImage)
        ->and(File::exists($newPath))->toBeTrue()
        ->and(File::exists($oldPath))->toBeFalse();
});

it('keeps the existing image when updating without a new upload', function () {
    $this->actingAs($this->admin)
        ->post('/admin/ads', adPayload())
        ->assertRedirect(route('ads.index'));

    $ad = Ad::firstOrFail();
    $image = $ad->image;
    $path = $this->publicDir.'/uploads/ads/'.$image;

    $this->actingAs($this->admin)
        ->put("/admin/ads/{$ad->id}", [
            'title' => 'Updated without image',
            'slot' => $ad->slot,
            'type' => 'image',
            'status' => '1',
        ])
        ->assertRedirect(route('ads.index'))
        ->assertSessionHasNoErrors();

    expect($ad->fresh()->image)->toBe($image)
        ->and(File::exists($path))->toBeTrue();
});

it('denies reporters access to advertisement management', function () {
    $reporter = User::factory()->reporter()->create();

    $this->actingAs($reporter)
        ->get('/admin/ads')
        ->assertForbidden();

    expect(Ad::count())->toBe(0);
});