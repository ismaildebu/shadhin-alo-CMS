<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Services\TextToSpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioNewsController extends Controller
{
    protected TextToSpeechService $ttsService;

    public function __construct(TextToSpeechService $ttsService)
    {
        $this->ttsService = $ttsService;
    }

    public function generateAudio(Request $request, int $id): JsonResponse
    {
        $news = News::findOrFail($id);

        $news->update(['tts_status' => 'processing']);

        // লেখা না পেলে মূল কনটেন্ট নিবে
        $contentToSpeak = $news->localized_content ?? $news->content ?? $news->title;

        $audioUrl = $this->ttsService->generateAudioFromText(
            $contentToSpeak,
            app()->getLocale()
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
            'message' => 'অডিও জেনারেট করতে সমস্যা হয়েছে। দয়া করে লোগ ফাইলটি চেক করুন।',
        ], 500);
    }
}