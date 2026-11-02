<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Services\TextToSpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AudioNewsController extends Controller
{
    protected TextToSpeechService $ttsService;

    public function __construct(TextToSpeechService $ttsService)
    {
        $this->ttsService = $ttsService;
    }

    public function generateAudio(Request $request, int $id): JsonResponse
    {
        // দীর্ঘ খবরে অনেকগুলো TTS অনুরোধ লাগে, তাই সময়সীমা বাড়ানো হয়।
        @set_time_limit(120);

        $news = News::published()->findOrFail($id);

        if (trim((string) $news->audio_url) !== '') {
            return $this->existingAudioResponse($news);
        }

        // একই খবরের জন্য একসাথে একাধিক TTS অনুরোধ যেন না চলে।
        $lock = Cache::lock('tts:'.$news->id, 120);

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'এই সংবাদের অডিও এখনই তৈরি হচ্ছে। একটু পরে আবার চেষ্টা করুন।',
            ], 429);
        }

        try {
            $news->refresh();

            if (trim((string) $news->audio_url) !== '') {
                return $this->existingAudioResponse($news);
            }

            $text = collect([
                $news->localized_content,
                $news->content,
                $news->description,
                $news->summary,
                $news->localized_title,
                $news->title,
            ])->first(fn ($value) => trim(strip_tags((string) $value)) !== '');

            if (! $text) {
                return response()->json([
                    'success' => false,
                    'message' => 'এই সংবাদে পড়ার মতো কোনো লেখা নেই।',
                ], 422);
            }

            $news->update(['tts_status' => 'processing']);

            // বাংলা লেখা যেন ইংরেজি ভয়েসে না পড়া হয়, তাই সংবাদের নিজের ভাষা ব্যবহার হয়।
            $language = $news->lang === 'en' ? 'en' : 'bn';

            $audioUrl = $this->ttsService->generateAudioFromText(
                (string) $text,
                $language
            );

            if ($audioUrl) {
                $news->update([
                    'audio_url' => $audioUrl,
                    'tts_status' => 'completed',
                ]);

                return response()->json([
                    'success' => true,
                    'audio_url' => $audioUrl,
                    'message' => 'অডিও সফলভাবে তৈরি হয়েছে',
                ]);
            }

            $news->update(['tts_status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'অডিও জেনারেট করতে সমস্যা হয়েছে। দয়া করে লোগ ফাইলটি চেক করুন।',
            ], 500);
        } finally {
            $lock->release();
        }
    }

    private function existingAudioResponse(News $news): JsonResponse
    {
        return response()->json([
            'success' => true,
            'audio_url' => $news->audio_url,
            'message' => 'এই সংবাদের অডিও আগেই তৈরি আছে।',
        ]);
    }
}
