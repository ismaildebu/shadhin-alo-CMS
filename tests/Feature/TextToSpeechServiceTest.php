<?php

use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

it('সঠিক MP3 রেসপন্স হলেই ফাইল সংরক্ষণ করে', function () {
    Http::fake([
        '*' => Http::response("\xFF\xFB\x90\x64".str_repeat("\0", 100), 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    $url = app(TextToSpeechService::class)->generateAudioFromText('পরীক্ষার সংবাদ।', 'bn');

    expect($url)->not->toBeNull();

    Storage::disk('public')->assertExists(Str::after($url, '/storage/'));
});

it('অডিও ছাড়া অন্য কনটেন্ট টাইপ প্রত্যাখ্যান করে', function () {
    Http::fake([
        '*' => Http::response('not audio', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(TextToSpeechService::class)->generateAudioFromText('পরীক্ষার সংবাদ।', 'bn'))->toBeNull();
});

it('MP3 সিগনেচার ছাড়া বডি প্রত্যাখ্যান করে', function () {
    Http::fake([
        '*' => Http::response('<html>fake</html>', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    expect(app(TextToSpeechService::class)->generateAudioFromText('পরীক্ষার সংবাদ।', 'bn'))->toBeNull();
});

it('রিডাইরেক্ট ফলো করে না', function () {
    Http::fake([
        '*' => Http::response('', 302, ['Location' => 'https://evil.example/audio.mp3']),
    ]);

    expect(app(TextToSpeechService::class)->generateAudioFromText('পরীক্ষার সংবাদ।', 'bn'))->toBeNull();
});

it('নির্ধারিত আকারের চেয়ে বড় রেসপন্স প্রত্যাখ্যান করে', function () {
    Http::fake([
        '*' => Http::response("\xFF\xFB".str_repeat("\0", 2 * 1024 * 1024), 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    expect(app(TextToSpeechService::class)->generateAudioFromText('পরীক্ষার সংবাদ।', 'bn'))->toBeNull();
});

it('ডিফল্টে SSL সার্টিফিকেট যাচাই চালু থাকে', function () {
    expect(config('tts.verify_ssl'))->toBeTrue();
});
