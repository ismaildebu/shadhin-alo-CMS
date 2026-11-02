<?php

it('সব রেসপন্সে নিরাপত্তা হেডার থাকে', function () {
    $this->get('/login')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});
