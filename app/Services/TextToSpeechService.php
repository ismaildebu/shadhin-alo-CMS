<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TextToSpeechService
{
    /**
     * গুগলের ফ্রি এন্ডপয়েন্ট একবারে ~২০০ অক্ষর নেয়, তাই লেখা ভাগ করে পাঠানো হয়।
     */
    private const MAX_CHUNK_LENGTH = 180;

    private const MAX_CHUNKS = 60;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Generate one MP3 file from the full text using Google Translate TTS.
     */
    public function generateAudioFromText(string $text, string $lang = 'bn'): ?string
    {
        $cleanText = trim(strip_tags($text));

        if ($cleanText === '') {
            return null;
        }

        $chunks = array_slice($this->splitIntoChunks($cleanText), 0, self::MAX_CHUNKS);
        $audio = '';

        foreach ($chunks as $chunk) {
            $part = $this->fetchChunk($chunk, $lang);

            if ($part === null) {
                return null;
            }

            $audio .= $this->stripId3($part);
        }

        if ($audio === '') {
            return null;
        }

        $disk = (string) config('tts.audio_disk', 'public');
        $directory = trim((string) config('tts.audio_path', 'audio-news'), '/');
        $fileName = $directory.'/news_'.time().'_'.Str::random(6).'.mp3';

        Storage::disk($disk)->put($fileName, $audio);

        return Storage::disk($disk)->url($fileName);
    }

    private function fetchChunk(string $text, string $lang): ?string
    {
        try {
            $url = 'https://translate.google.com/translate_tts?ie=UTF-8'
                .'&q='.urlencode($text)
                ."&tl={$lang}&client=tw-ob&total=1&idx=0&textlen=".mb_strlen($text);

            // withoutVerifying() যোগ করা হয়েছে যাতে লোকাল পিসিতে SSL সার্টিফিকেট এরর না দেয়
            $response = Http::withoutVerifying()->withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->timeout(15)->get($url);

            $isAudio = str_contains((string) $response->header('Content-Type'), 'audio');

            if ($response->successful() && $isAudio && $response->body() !== '') {
                return $response->body();
            }

            \Log::error('TTS HTTP Request Failed with status: '.$response->status().' type: '.$response->header('Content-Type'));

            return null;
        } catch (Exception $e) {
            \Log::error('TTS Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * বাক্য অনুযায়ী ভাগ করে প্রতিটি টুকরা ১৮০ অক্ষরের নিচে রাখে।
     *
     * @return list<string>
     */
    private function splitIntoChunks(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[।.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];

        $chunks = [];
        $buffer = '';

        foreach ($sentences as $sentence) {
            while (mb_strlen($sentence) > self::MAX_CHUNK_LENGTH) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                $cut = mb_substr($sentence, 0, self::MAX_CHUNK_LENGTH);
                $space = mb_strrpos($cut, ' ');

                if ($space === false || $space < 40) {
                    $space = self::MAX_CHUNK_LENGTH;
                }

                $chunks[] = trim(mb_substr($sentence, 0, $space));
                $sentence = trim(mb_substr($sentence, $space));
            }

            if ($sentence === '') {
                continue;
            }

            if ($buffer === '') {
                $buffer = $sentence;
            } elseif (mb_strlen($buffer) + 1 + mb_strlen($sentence) <= self::MAX_CHUNK_LENGTH) {
                $buffer .= ' '.$sentence;
            } else {
                $chunks[] = $buffer;
                $buffer = $sentence;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return array_values(array_filter($chunks, fn ($chunk) => $chunk !== ''));
    }

    /**
     * মাঝখানে বসানো ID3 ট্যাগ ডিকোডার বিভ্রান্ত করতে পারে, তাই বাদ দেওয়া হয়।
     */
    private function stripId3(string $mp3): string
    {
        if (str_starts_with($mp3, 'ID3') && strlen($mp3) >= 10) {
            $size = ((ord($mp3[6]) & 0x7F) << 21)
                | ((ord($mp3[7]) & 0x7F) << 14)
                | ((ord($mp3[8]) & 0x7F) << 7)
                | (ord($mp3[9]) & 0x7F);

            return substr($mp3, 10 + $size);
        }

        return $mp3;
    }
}
