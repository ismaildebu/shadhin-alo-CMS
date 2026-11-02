<?php

use App\Models\Category;
use App\Models\News;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);
    $this->news = News::create([
        'category_id' => $this->category->id,
        'title' => 'অডিও পরীক্ষার সংবাদ',
        'slug' => 'audio-test',
        'description' => '<p>পরীক্ষার জন্য কিছু লেখা।</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);
});

it('আগেই তৈরি করা অডিও থাকলে নতুন করে তৈরি করে না', function () {
    $this->news->update(['audio_url' => '/storage/audio-news/existing.mp3', 'tts_status' => 'completed']);

    $this->mock(TextToSpeechService::class, function ($mock) {
        $mock->shouldNotReceive('generateAudioFromText');
    });

    $response = $this->postJson("/news/{$this->news->id}/generate-audio")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'audio_url' => '/storage/audio-news/existing.mp3',
        ]);

    expect($response->json('message'))->toContain('আগেই তৈরি');
});

it('একই খবরে একাধিক অনুরোধ একসাথে চললে ৪২৯ ফেরত দেয়', function () {
    $lock = Cache::lock('tts:'.$this->news->id, 60);
    expect($lock->get())->toBeTrue();

    try {
        $this->mock(TextToSpeechService::class, function ($mock) {
            $mock->shouldNotReceive('generateAudioFromText');
        });

        $this->postJson("/news/{$this->news->id}/generate-audio")
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    } finally {
        $lock->release();
    }
});

it('এক মিনিটে চার নম্বর অনুরোধ থ্রটল হয়', function () {
    $this->news->update(['audio_url' => '/storage/audio-news/existing.mp3', 'tts_status' => 'completed']);

    $this->postJson("/news/{$this->news->id}/generate-audio")->assertOk();
    $this->postJson("/news/{$this->news->id}/generate-audio")->assertOk();
    $this->postJson("/news/{$this->news->id}/generate-audio")->assertOk();
    $this->postJson("/news/{$this->news->id}/generate-audio")->assertStatus(429);
});

it('অডিও তৈরি হলে সংরক্ষণ করে এবং লক ছেড়ে দেয়', function () {
    $this->mock(TextToSpeechService::class, function ($mock) {
        $mock->shouldReceive('generateAudioFromText')
            ->once()
            ->andReturn('/storage/audio-news/generated.mp3');
    });

    $this->postJson("/news/{$this->news->id}/generate-audio")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'audio_url' => '/storage/audio-news/generated.mp3',
        ]);

    $this->news->refresh();
    expect($this->news->audio_url)->toBe('/storage/audio-news/generated.mp3')
        ->and($this->news->tts_status)->toBe('completed');

    $lock = Cache::lock('tts:'.$this->news->id, 5);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

it('অডিও তৈরি ব্যর্থ হলে স্টেটাস ফেইলড হয় এবং লক ছেড়ে দেয়', function () {
    $this->mock(TextToSpeechService::class, function ($mock) {
        $mock->shouldReceive('generateAudioFromText')->once()->andReturn(null);
    });

    $this->postJson("/news/{$this->news->id}/generate-audio")
        ->assertStatus(500)
        ->assertJson(['success' => false]);

    expect($this->news->fresh()->tts_status)->toBe('failed');

    $lock = Cache::lock('tts:'.$this->news->id, 5);
    expect($lock->get())->toBeTrue();
    $lock->release();
});
