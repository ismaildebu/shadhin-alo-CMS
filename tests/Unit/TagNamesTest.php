<?php

use App\Models\Tag;

it('cleans a raw tag name', function () {
    expect(Tag::cleanName("  রাজনীতি   ও \n  নির্বাচন  "))->toBe('রাজনীতি ও নির্বাচন')
        ->and(Tag::cleanName('<b>বোল্ড</b><script>alert(1)</script>'))->toBe('বোল্ডalert(1)')
        ->and(Tag::cleanName("tab\there"))->toBe('tab here')
        ->and(Tag::cleanName("line\nbreak\x07bell"))->toBe('line break bell')
        ->and(Tag::cleanName("nul\x00byte"))->not->toContain("\x00")
        ->and(Tag::cleanName(null))->toBe('')
        ->and(Tag::cleanName('   '))->toBe('');
});

it('splits input at ASCII, Arabic and full-width commas and line breaks', function () {
    expect(Tag::splitNames("রাজনীতি, নির্বাচন\u{060C} ঢাকা\u{FF0C}খেলা\nআইন"))
        ->toBe(['রাজনীতি', ' নির্বাচন', ' ঢাকা', 'খেলা', 'আইন'])
        ->and(Tag::splitNames(',,  ,'))->toBe([])
        ->and(Tag::splitNames(null))->toBe([]);
});

it('gives equal-looking and equal-meaning names the same slug', function () {
    // Latin: case and punctuation
    expect(Tag::slugFor('Bangladesh'))->toBe(Tag::slugFor('bangladesh'))
        ->and(Tag::slugFor('COVID-19'))->toBe('covid-19')
        ->and(Tag::slugFor('COVID 19'))->toBe('covid-19')
        ->and(Tag::slugFor('  Dhaka   City '))->toBe('dhaka-city');

    // Bengali য় typed as one character or as য + nukta
    expect(Tag::slugFor("জাতী\u{09DF}"))->toBe(Tag::slugFor("জাতী\u{09AF}\u{09BC}"));
});

it('keeps Bengali letters, vowel signs and conjuncts in the slug', function () {
    expect(Tag::slugFor('বাংলাদেশ'))->toBe('বাংলাদেশ')
        ->and(Tag::slugFor('রাজনীতি ও নির্বাচন'))->toBe('রাজনীতি-ও-নির্বাচন')
        ->and(Tag::slugFor('র‍্যাব'))->not->toContain('-')      // ZWJ inside a word must not split it
        ->and(Tag::slugFor("শব্দ\u{200C}টি"))->toBe(Tag::slugFor('শব্দটি'));
});

it('falls back to "tag" when nothing usable is left', function () {
    expect(Tag::slugFor('!!!'))->toBe('tag')->and(Tag::slugFor('😀'))->toBe('tag');
});

it('limits the slug length', function () {
    expect(mb_strlen(Tag::slugFor(str_repeat('অ', 300))))->toBeLessThanOrEqual(100);
});
