<?php

use App\Support\HtmlSanitizer;

it('keeps safe formatting and Bengali text untouched', function () {
    $html = '<p>বাংলা <strong>সংবাদ</strong> — আজকের খবর &amp; আরও</p>';

    expect(HtmlSanitizer::clean($html))->toBe($html);
});

it('keeps CKEditor images and tables', function () {
    $html = '<figure class="image"><img src="/uploads/a.jpg" alt="x"></figure>'
        .'<figure class="table"><table><tbody><tr><td>1</td></tr></tbody></table></figure>';

    expect(HtmlSanitizer::clean($html))->toBe($html);
});

it('removes dangerous markup', function (string $payload) {
    $clean = strtolower(HtmlSanitizer::clean($payload));

    expect($clean)->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('onload')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe')
        ->not->toContain('<svg')
        ->not->toContain('data:text');
})->with([
    'script tag' => '<p>hi</p><script>alert(1)</script>',
    'img onerror' => '<img src="https://x.com/a.jpg" onerror="alert(1)">',
    'javascript href' => '<a href="javascript:alert(1)">x</a>',
    'javascript href with newline' => "<a href=\"java\nscript:alert(1)\">x</a>",
    'javascript href with entity' => '<a href="jav&#x61;script:alert(1)">x</a>',
    'data url image' => '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">',
    'svg onload' => '<svg onload=alert(1)><circle/></svg><p>ok</p>',
    'iframe' => '<iframe src="https://evil.example"></iframe>',
    'inline handler and style' => '<p style="background:url(javascript:alert(1))" onclick="x()">t</p>',
    'html comment' => '<p>a</p><!-- <script>alert(1)</script> -->',
]);

it('adds rel="noopener" to links that open a new tab', function () {
    $clean = HtmlSanitizer::clean('<a href="https://ok.example" target="_blank">l</a>');

    expect($clean)->toContain('rel="noopener noreferrer nofollow"');
});

it('unwraps unknown tags but keeps their text', function () {
    expect(HtmlSanitizer::clean('<custom-tag>keep <b>me</b></custom-tag>'))->toBe('keep <b>me</b>');
});

it('returns an empty string for empty or fully malicious input', function () {
    expect(HtmlSanitizer::clean(null))->toBe('')
        ->and(HtmlSanitizer::clean('   '))->toBe('')
        ->and(HtmlSanitizer::clean('<script>alert(1)</script>'))->toBe('');
});
