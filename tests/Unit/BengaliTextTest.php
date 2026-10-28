<?php

use App\Support\BengaliText;

it('treats the two spellings of য় as the same word', function () {
    $single = "জাতী\u{09DF}";           // য় as one character
    $pair = "জাতী\u{09AF}\u{09BC}";     // য + nukta

    expect($single === $pair)->toBeFalse()               // the problem
        ->and(BengaliText::same($single, $pair))->toBeTrue() // the fix
        ->and(BengaliText::in($pair, ['রাজনীতি', $single]))->toBeTrue();
});

it('handles the other composed Bengali letters (ড় ঢ় ো ৌ)', function () {
    $pairs = [
        ["\u{09DC}", "\u{09A1}\u{09BC}"],
        ["\u{09DD}", "\u{09A2}\u{09BC}"],
        ["ক\u{09CB}", "ক\u{09C7}\u{09BE}"],
        ["ক\u{09CC}", "ক\u{09C7}\u{09D7}"],
    ];

    foreach ($pairs as [$a, $b]) {
        expect(BengaliText::same($a, $b))->toBeTrue();
    }
});

it('does not confuse different words and tolerates null', function () {
    expect(BengaliText::same('জাতীয়', 'রাজনীতি'))->toBeFalse()
        ->and(BengaliText::in('জাতীয়', []))->toBeFalse()
        ->and(BengaliText::normalize(null))->toBe('');
});
