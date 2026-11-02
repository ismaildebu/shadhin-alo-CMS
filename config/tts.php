<?php

return [
    'driver' => env('TTS_DRIVER', 'google'), // google, amazon, or mock

    'google' => [
        'api_key' => env('GOOGLE_TTS_API_KEY', ''),
        'language_code' => [
            'bn' => 'bn-BD',
            'en' => 'en-US',
        ],
        'voice_name' => [
            'bn' => 'bn-BD-Standard-A',
            'en' => 'en-US-Neural2-F',
        ],
        'audio_encoding' => 'MP3',
    ],

    // শুধু লোকাল ডেভেলপমেন্টে .env-এ TTS_VERIFY_SSL=false দেওয়া যাবে।
    'verify_ssl' => env('TTS_VERIFY_SSL', true),

    'audio_disk' => env('TTS_AUDIO_DISK', 'public'),
    'audio_path' => 'audio-news/',
];