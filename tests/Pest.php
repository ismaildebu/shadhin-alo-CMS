<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * তালিকার পেজের মূল অংশ (সাইডবার বাদে)।
 *
 * সাইডবার সারা সাইটের সর্বশেষ ও জনপ্রিয় খবর দেখায়, তাই "এই খবরটি পেজে নেই"
 * ধরনের দাবি পুরো HTML নিয়ে করলে ভুল ফেল করে — শুধু তালিকাটি দেখতে হয়।
 */
function newsList(string $html): string
{
    $from = strpos($html, 'sa-list-main');
    $to = $from === false ? false : strpos($html, 'sa-sidebar', $from);

    if ($from === false || $to === false) {
        throw new RuntimeException('তালিকার পেজে sa-list-main / sa-sidebar চিহ্ন নেই।');
    }

    return substr($html, $from, $to - $from);
}
