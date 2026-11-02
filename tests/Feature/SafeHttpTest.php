<?php

use App\Support\NewsImport\SafeHttp;

test('ইন্টারনাল বা প্রতারক ঠিকানা ব্লক হয়', function (string $url) {
    expect(fn () => SafeHttp::inspect($url))->toThrow(RuntimeException::class);
})->with([
    'loopback v4' => 'http://127.0.0.1/x',
    'private 10/8' => 'http://10.0.0.5/x',
    'private 192.168/16' => 'http://192.168.1.10/x',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback v6' => 'http://[::1]/x',
    'mapped loopback v6' => 'http://[::ffff:127.0.0.1]/x',
    'decimal ip' => 'http://2130706433/x',
    'hex ip' => 'http://0x7f000001/x',
    'octal ip' => 'http://0177.0.0.1/x',
    'localhost' => 'http://localhost/x',
    'localhost with dot' => 'http://localhost./x',
    'localhost subdomain' => 'http://foo.localhost/x',
    'mdns name' => 'http://nas.local/x',
    'single label host' => 'http://intranet/x',
    'unusual port' => 'http://example.com:6379/x',
    'non http scheme' => 'ftp://example.com/x',
    'credentials in url' => 'http://user:pass@example.com/x',
]);

test('পাবলিক আইপি ঠিকানা অনুমোদিত', function () {
    $target = SafeHttp::inspect('https://8.8.8.8/news');

    expect($target['host'])->toBe('8.8.8.8')
        ->and($target['port'])->toBe(443)
        ->and($target['ips'])->toBe(['8.8.8.8']);
});

test('অনুমোদিত হোস্টের বাইরের লিংক ব্লক হয়', function () {
    expect(fn () => SafeHttp::inspect('https://evil.example.com/x', ['www.prothomalo.com']))
        ->toThrow(RuntimeException::class);
});

test('রিডাইরেক্টের গন্তব্য মূল ঠিকানার সাথে মেলানো হয়', function () {
    $base = 'https://example.com/news/story?id=7';

    expect(SafeHttp::resolveUrl('https://cdn.example.com/x', $base))->toBe('https://cdn.example.com/x')
        ->and(SafeHttp::resolveUrl('//cdn.example.com/x', $base))->toBe('https://cdn.example.com/x')
        ->and(SafeHttp::resolveUrl('/next/page', $base))->toBe('https://example.com/next/page')
        ->and(SafeHttp::resolveUrl('?page=2', $base))->toBe('https://example.com/news/story?page=2')
        ->and(SafeHttp::resolveUrl('sister.html', $base))->toBe('https://example.com/news/sister.html');
});

test('fetch ইন্টারনাল ঠিকানায় রিকোয়েস্ট পাঠায় না', function () {
    expect(fn () => SafeHttp::fetch('http://127.0.0.1/', ['request' => []]))
        ->toThrow(RuntimeException::class);
});
