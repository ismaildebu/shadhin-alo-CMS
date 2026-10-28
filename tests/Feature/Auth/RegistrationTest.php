<?php

use App\Models\User;

// Public self-registration is disabled on purpose: every account can reach the
// CMS, so admins are created with `php artisan cms:create-admin`.

test('registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration is disabled', function () {
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'intruder@example.com')->exists())->toBeFalse();
});
