<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TextToSpeechService
{
    /**
     * Generate Audio file from Text using Google Translate Public TTS Endpoint
     */
    public function generateAudioFromText(string $text, string $lang = 'bn'): ?string
    {
        $cleanText = trim(strip_tags($text));
        
        if (empty($cleanText)) {
            return null;
        }

        // গুগল ফ্রি টিটিএস এর জন্য প্রথম ২০০ অক্ষর নেওয়া হচ্ছে
        $cleanText = Str::limit($cleanText, 200, '');

        try {
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&q=" . urlencode($cleanText) . "&tl={$lang}&client=tw-ob";

            // withoutVerifying() যোগ করা হয়েছে যাতে লোকাল পিসিতে SSL সার্টিফিকেট এরর না দেয়
            $response = Http::withoutVerifying()->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->timeout(15)->get($url);

            if ($response->successful() && strlen($response->body()) > 0) {
                $fileName = 'audio-news/news_' . time() . '_' . Str::random(6) . '.mp3';

                Storage::disk('public')->put($fileName, $response->body());

                return Storage::disk('public')->url($fileName);
            }

            \Log::error('TTS HTTP Request Failed with status: ' . $response->status());
            return null;

        } catch (Exception $e) {
            \Log::error('TTS Exception: ' . $e->getMessage());
            return null;
        }
    }
}