<?php

/*
 |--------------------------------------------------------------------------
 | আলোচিত সার্চ থেকে সংবাদ (Google Trends BD) — স্বতন্ত্র সিস্টেম
 |--------------------------------------------------------------------------
 |
 | trends.google.com বাংলাদেশে এখন কোন শব্দগুলো বেশি searched হচ্ছে তা একটি RSS
 | ফিডে প্রকাশ করে (১০টি আইটেম, প্রতিটির সাথে ৩টি সত্যিকারের সংবাদ লিংক)। এই
 | সিস্টেম সেই ফিড পড়ে, প্রতিটি লিংকের নিজের পেজ থেকে প্রকাশ-তারিখ যাচাই করে এবং
 | শুধু শর্ত পূরণ হলে সংবাদটি খসড়া হিসেবে আনে।
 |
 | এটি config/news_import.php-এর পাশেই দাঁড়ানো আলাদা ব্যবস্থা: ওই কনফিগ, FeedReader
 | বা NewsImporter-এর কোনো নিয়ম এটি বদলায় না। সংবাদ সংরক্ষণের জন্য একই
 | NewsImporter::save() ব্যবহার করা হয়, তাই ডুপ্লিকেট-রক্ষা, ক্যাটাগরি সমাধান,
 | সারাদেশের অবস্থান-নিয়ম ও ছবি নামানো — সব আগের মতোই চলে।
 |
 | খবর না মিললে সম্পাদকের হাত খালি না থাকে: প্রতিটি ট্রেন্ডের জন্য তিনটি সম্ভাব্য
 | বাংলা শিরোনাম পরামর্শ হিসেবে জমা হয়, আর আগামী ২৪–৪৮ ঘণ্টায় ট্রেন্ড হওয়া
 | ঘটনাগুলোর একটি তালিকা আলাদা ট্যাবে দেখানো হয়।
 */

return [

    'enabled' => env('NEWS_TRENDS_ENABLED', true),

    'geo' => env('NEWS_TRENDS_GEO', 'BD'),

    'feed' => 'https://trends.google.com/trending/rss?geo=%s',

    'source_key' => 'news-trends',

    'request' => [
        'user_agent' => 'ShadhinAloNewsBot/1.0 (+' . env('APP_URL', 'http://localhost') . ')',
        'timeout' => 15,
        'connect_timeout' => 6,
        'max_bytes' => 2_000_000,
        'article_max_bytes' => 6_000_000,
    ],

    /*
     | ছাঁকনিগুলো এই সিস্টেমের নিজের এবং কঠোরের দিকে থাকে — সংখ্যা বাড়াতে এগুলো
     | শিথিল করা হবে না (news_import.collect-এর গেটগুলোর মতোই এগুলো স্থির)।
     |   max_age_hours      : নিজের পেজে যাচাই করা প্রকাশ-তারিখ এর চেয়ে পুরোনো হলে বাদ
     |   min_title_chars    : খুব ছোট শিরোনাম বাদ
     |   min_body_chars     : পূর্ণ প্রতিবেদন না মিললে সারাংশ এত অক্ষরের নিচে হলে বাদ
     |   dedupe_hours       : একই ট্রেন্ড কীওয়ার্ড এত ঘণ্টার মধ্যে আবার আমদানি হবে না
     | ফিডে তারিখ-মেটা না থাকলে আইটেমটি আমদানি হয় না — ফাঁকি দিয়ে পুরোনো তারিখ বসানো হয় না।
     */
    'collect' => [
        'max_per_run' => 10,
        'max_foreign_per_run' => 4,
        'max_age_hours' => 24,
        'min_title_chars' => 12,
        'min_body_chars' => 400,
        'news_items_per_trend' => 3,
        'dedupe_hours' => 72,
        'body_max_chars' => 20_000,
    ],

    /*
     | শুধু এই ডোমেইনগুলোর পেজ খোলা হবে — SSRF-রক্ষার সাথে দ্বিতীয় স্তর, এবং
     | ভুয়া/নকল সংবাদ সাইট এড়ানোর উপায়। তালিকা বাংলাদেশি ও বিদেশি সব সংবাদ
     | মাধ্যমের জন্য খোলা; নতুন যোগ করার আগে অ্যাডমিন → «আলোচিত সার্চ» পেজের
     | «উৎস যাচাই» বাটন দিয়ে পেজটি সত্যিই খোলে কি না দেখে নিন।
     | 'block' তালিকা সবার আগে দেখা হয় — জুয়া/অডস সাইট কখনো আনা হবে না।
     */
    'hosts' => [
        'allow' => [
            // বাংলাদেশ
            'www.ittefaq.com.bd', 'ittefaq.com.bd',
            'www.prothomalo.com', 'prothomalo.com',
            'www.dailypoint.com', 'dailypoint.com',
            'bangla.bdnews24.com', 'bdnews24.com',
            'www.jagonews24.com', 'jagonews24.com',
            'dailyinqilab.com', 'www.dailyinqilab.com',
            'www.bd-pratidin.com', 'bd-pratidin.com',
            'www.dailystar.net', 'dailystar.net',
            'en.prothomalo.com',
            'www.observerbd.com', 'observerbd.com',
            'www.thefinancialexpress.com.bd', 'thefinancialexpress.com.bd',
            'www.newagebd.net', 'newagebd.net',
            'thesunglas.com', 'www.thesunglas.com',
            'kholamaataphurtulana.com',

            // বিদেশি সংবাদ মাধ্যম
            'www.bbc.com', 'www.bbc.co.uk',
            'apnews.com', 'www.reuters.com',
            'www.aljazeera.com', 'www.theguardian.com',
            'www.france24.com', 'www.dw.com',
            'www.nytimes.com', 'www.cnn.com',
            'www.espn.com', 'www.espn.in',
            'www.goal.com', 'onefootball.com',
            'www.sportsmole.co.uk', 'football-italia.net',
            'www.lanacion.com.ar', 'www.ole.com.ar',
            'milanreports.com', 'www.telecomasia.net',
            'www.timesofindia.com', 'www.ndtv.com',
            'www.straitstimes.com', 'www.channelnewsasia.com',
            'www.scmp.com', 'www.abs-cbn.com',
            'www.independent.co.uk', 'www.bbc.in',
        ],

        // বাজি/অডস সাইট: এগুলো সংবাদ নয়, খেলার প্রতিবেদনের আড়ালে প্রচারণা।
        'block' => [
            'sportsbook.fanduel.com', 'fanduel.com',
            'www.oddspedia.com', 'oddspedia.com',
            'www.oddschecker.com', 'oddschecker.com',
            'bet365.com', 'www.bet365.com',
            '10bet.com', 'www.10bet.com', 'unibet.com', 'www.unibet.com',
            'www.sportybet.com', 'betway.com', 'www.draftkings.com',
        ],
    ],

    /*
     | খসড়া কোন বিভাগে যাবে। বাংলা/বাংলাদেশি খবর 'saradesh' — এই slug-এর
     | সংবাদে NewsImporter নিজে জেলা/উপজেলা খুঁজে বসায় (LocationMatcher)।
     | শনাক্ত না হলে সেটি আমদানি হয় না, পরামর্শ-তালিকায় চলে যায়।
     */
    'categories' => [
        'bangladesh' => 'saradesh',
        'foreign_sports' => 'sports',
        'foreign' => 'international',
        'fallback' => 'others',
    ],

    // বিদেশি খবরের খেলা-আইটেম 'খেলা' বিভাগে যায়, বাকি সব 'আন্তর্জাতিক'।
    'sports_keywords' => [
        'football', 'soccer', 'cricket', 'match', 'vs', 'v ', 'versus', 'league', 'cup',
        'fifa', 'uefa', 'premier', 'la liga', 'serie a', 'xi', 'goal', 'team', 'win',
        'defeat', 'draw', 'half', 'innings', 't20', 'odi', 'test', 'tournament', 'khel',
    ],

    'translate' => [
        'enabled' => env('NEWS_TRENDS_TRANSLATE', true),
        // এক চালে অনুবাদের সর্বোচ্চ আয়তন; শেষ হলে বাকিগুলো «অনুবাদ প্রয়োজন» ট্যাগ পায়।
        'max_chars_per_run' => 6000,
        'max_chars_per_request' => 1500,
        'request' => [
            'timeout' => 12,
            'connect_timeout' => 6,
            'max_bytes' => 200_000,
        ],
    ],

    /*
     | «আগামী ট্রেন্ড» ট্যাবের উৎস। এখানে ভবিষ্যৎবাণী বানানো হয় না—  সাইটের নিজের
     | প্রকাশিত সংবাদে যেসব ঘটনার তারিখ এগিয়ে রাখা আছে (আগামীকাল, পরশু, আসন্ন
     | বার) সেগুলোই আবার ফিরে আসে, আর যেসব কীওয়ার্ড গত কয়েক চাল বারবার ট্রেন্ড
     | হয়েও আমদানি হয়নি সেগুলো। দুটোই জমা তথ্য থেকে গণনা হয়।
     */
    'forecast' => [
        'lookback_hours' => 48,
        'max_items' => 10,
        'min_mentions' => 1,
        'markers' => [
            'আগামীকাল', 'পরশু', 'আগামী', 'আসন্ন', 'হবে', 'অনুষ্ঠিত', 'পালন', 'বন্ধ', 'ধর্মতাল', 'হরতাল',
            'শুক্রবার', 'শনিবার', 'রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার',
        ],
    ],
];
