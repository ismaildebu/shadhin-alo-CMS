<?php

/*
 |--------------------------------------------------------------------------
 | বাক্য অনুবাদ (ইংরেজি/অন্য ভাষা → বাংলা)
 |--------------------------------------------------------------------------
 |
 | 'google_web' কোনো API কী চায় না: গুগল ট্রান্সলেটের উন্মুক্ত ওয়েব এন্ডপয়েন্ট,
 | যা সাইটের নিজের HTTP ক্লায়েন্ট দিয়েই চলে। মান-quality দরকার হলে 'google_cloud'
 | বা 'deepl' কী বসালেই সেই driver-এ চলে যাবে, কোড বদলাতে হবে না।
 |
 | শুধু লোকাল ডেভেলপমেন্টে .env-এ TRANSLATE_VERIFY_SSL=false দেওয়া যাবে।
 */

return [
    'driver' => env('TRANSLATE_DRIVER', 'google_web'), // google_web, google_cloud, deepl, mock

    'source_language' => env('TRANSLATE_SOURCE_LANGUAGE', 'en'),
    'target_language' => env('TRANSLATE_TARGET_LANGUAGE', 'bn'),

    'google_web' => [
        'endpoint' => 'https://translate.googleapis.com/translate_a/single',
        'client' => 'gtx',
    ],

    'google_cloud' => [
        'api_key' => env('GOOGLE_TRANSLATE_API_KEY', ''),
        'endpoint' => 'https://translation.googleapis.com/language/translate/v2',
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY', ''),
        'endpoint' => 'https://api-free.deepl.com/v2/translate',
    ],

    'verify_ssl' => env('TRANSLATE_VERIFY_SSL', true),

    // একই বাক্য বারবার অনুবাদ না হয়; নতুন subsystem-এর নিজের টেবিলেই ফল জমে।
    'cache_hours' => 24 * 30,
];
