<?php

/*
 |--------------------------------------------------------------------------
 | স্বয়ংক্রিয় সংবাদ সংগ্রহ (RSS)
 |--------------------------------------------------------------------------
 |
 | প্রতিটি সোর্সের ফিড, কোন সাইটের লিংক নেওয়া হবে, সোর্সের বিভাগ কোন স্থানীয়
 | ক্যাটাগরিতে (slug) যাবে, ছবি কোথা থেকে নেওয়া হবে এবং কতটি আইটেম নেওয়া হবে
 | এখানে ঠিক করা হয়। নতুন সোর্স যোগ করতে নিচের sources তালিকায় একটি এন্ট্রি দিন।
 |
 | সব সংবাদ খসড়া (draft) হিসেবে আসে; সম্পাদক দেখে প্রকাশ করেন। প্রতিটি খসড়ার
 | শেষে সোর্সের মূল লিংক থাকে। `content` ব্লকে সোর্স পেজের কোন কনটেইনারে আসল
 | প্রতিবেদন থাকে তা দেওয়া হয়; সেটি না থাকলে ফিডের সারাংশই বডি হিসেবে বসে।
 |
 | এই সাইটে বর্তমানে থাকা ক্যাটাগরির slug (অ্যাডমিন → ক্যাটাগরি থেকে মিলিয়ে নিন):
 | Jayato, International, Entertainment, Sports, Politics, saradesh, Opinion,
 | Editorial, Sub-editorial, Latest, Economics, Special-Report, Feature, Job,
 | Agriculture-Laboring-Life, Science-Technology, Health, Religion-Festivals, Education
 |
 */

return [

    // মাস্টার সুইচ: false করলে কোনো ফিডই পড়া হবে না।
    'enabled' => env('NEWS_IMPORT_ENABLED', true),

    'request' => [
        'user_agent' => 'ShadhinAloNewsBot/1.0 (+'.env('APP_URL', 'http://localhost').')',
        'timeout' => 20,          // ফিড/পেজ পড়ার সর্বোচ্চ সময় (সেকেন্ড)
        'connect_timeout' => 8,
        'max_bytes' => 6_000_000,          // এর চেয়ে বড় ফিড নেওয়া হবে না
        'article_max_bytes' => 8_000_000,  // সংবাদ পেজ (প্রথম আলোর পেজ প্রায় ৩ MB)
    ],

    'collect' => [
        'per_source_limit' => 8,   // প্রতি সোর্সে প্রতি চালে সর্বোচ্চ নতুন সংবাদ
        'max_age_hours' => 36,     // এর চেয়ে পুরোনো সংবাদ বাদ
        'min_title_chars' => 12,   // খুব ছোট/ফাঁকা শিরোনাম বাদ
        'dedupe_window_days' => 30, // এই সময়ের শিরোনামের সাথে মিললে ডুপ্লিকেট
        'scan_factor' => 4,        // ডুপ্লিকেট এড়াতে ফিডে সর্বোচ্চ limit × এতটি আইটেম দেখা হবে
    ],

    'text' => [
        'excerpt_chars' => 500,  // সোর্সের সারাংশ থেকে সর্বোচ্চ কত অক্ষর রাখা হবে
        'og_lookup' => true,     // ফিডে সারাংশ না থাকলে সংবাদ পেজের og:description
        'full_article' => true,  // সংবাদ পেজ থেকে পূর্ণাঙ্গ প্রতিবেদন (মূল বডি) আনা হবে
        'full_article_max_chars' => 30_000, // একটি প্রতিবেদনের সর্বোচ্চ দৈর্ঘ্য
    ],

    'images' => [
        'download' => true,      // ছবি নিজের সার্ভারে নামানো হবে
        'og_lookup' => true,     // ফিডে ছবি না থাকলে সংবাদ পেজের og:image
        'min_width' => 480,      // এর চেয়ে ছোট ছবি বাদ (অটো ম্যাপ/গ্রাফ ফলব্যাক বসবে)
        'max_bytes' => 5_000_000,
    ],

    'import' => [
        'author_id' => env('NEWS_IMPORT_AUTHOR_ID'), // null হলে লেখক ছাড়া খসড়া
    ],

    'sources' => [

        'prothomalo' => [
            'enabled' => true,
            'name' => 'প্রথম আলো',
            'feed' => 'https://www.prothomalo.com/feed',
            // ফিডে প্রথম আলোর সহযোগী সাইটের লিংকও থাকে (কিশোর আলো, বন্ধুশভা...); শুধু এই হোস্ট নেওয়া হবে
            'allowed_hosts' => ['www.prothomalo.com', 'nagorik.prothomalo.com'],
            'category_from' => 'tag', // item-এর <category>
            'category_map' => [
                'বাংলাদেশ' => 'Jayato',
                'bangladesh' => 'Jayato',
                'জাতীয়' => 'Jayato',
                'জেলা' => 'saradesh',
                'সারাদেশ' => 'saradesh',
                'রাজনীতি' => 'Politics',
                'বাণিজ্য' => 'Economics',
                'অর্থনীতি' => 'Economics',
                'খেলা' => 'Sports',
                'ক্রিকেট' => 'Sports',
                'ফুটবল' => 'Sports',
                'বিনোদন' => 'Entertainment',
                'টেলিভিশন' => 'Entertainment',
                'আন্তর্জাতিক' => 'International',
                'যুক্তরাষ্ট্র' => 'International',
                'ইউরোপ' => 'International',
                'দূর পরবাস' => 'International',
                'শিক্ষা' => 'Education',
                'বৃত্তি' => 'Education',
                'স্বাস্থ্য' => 'Health',
                'বিজ্ঞান' => 'Science-Technology',
                'প্রযুক্তি' => 'Science-Technology',
                'biology' => 'Science-Technology',
                'অপরাধ' => 'Jayato', // আদালতের আলাদা ক্যাটাগরি নেই
                'মতামত' => 'Opinion',
                'সম্পাদকীয়' => 'Editorial',
                'কলাম' => 'Opinion',
                'সাক্ষাৎকার' => 'Opinion',
                'writings' => 'Opinion',
                'সাহিত্য' => 'Feature',
                'জীবনযাপন' => 'Feature',
                'লাইফস্টাইল' => 'Feature',
                'ফ্যাশন' => 'Feature',
                'লুক' => 'Feature',
                'কর্মখালি' => 'Job',
                'নিয়োগ' => 'Job',
                'গণমাধ্যম' => 'Feature',
                'মিডিয়া' => 'Feature',
                'বিশেষ প্রতিবেদন' => 'Special-Report',
                'feature' => 'Feature',
                'পরিবেশ' => 'Jayato',
            ],
            // রস+আলো (হাস্যরস) সংবাদ নয়, তাই বাদ
            'skip_categories' => ['রস+আলো'],
            'content' => [
                'class' => 'story-content', // মূল প্রতিবেদনের কনটেইনার
                'exclude' => [
                    'story-head', 'story-page-hero', 'story-metadata-wrapper',
                    'print-tags', 'print-none',
                    'story-element-text-also-read', // "আরও পড়ুন" কার্ডের শিরোনাম বডিতে ঢোকে না
                ],
            ],
            'default_category' => 'Latest',
            'image' => [
                'from' => ['media_content', 'media_thumbnail'],
                'og' => true, // ফিডে ছবি না থাকলে সংবাদ পেজ
            ],
        ],

        'samakal' => [
            'enabled' => true,
            'name' => 'সমকাল',
            'feed' => 'https://samakal.com/rss',
            'allowed_hosts' => ['samakal.com', 'www.samakal.com'],
            'category_from' => 'url', // ফিডে বিভাগ নেই; লিংকের প্রথম অংশ
            'category_map' => [
                'bangladesh' => 'Jayato',
                'whole-country' => 'saradesh',
                'capital' => 'Jayato',
                'politics' => 'Politics',
                'economics' => 'Economics',
                'sports' => 'Sports',
                'entertainment' => 'Entertainment',
                'international' => 'International',
                'technology' => 'Science-Technology',
                'education' => 'Education',
                'health' => 'Health',
                'law-court' => 'Jayato',
                'opinion' => 'Opinion',
                'lifestyle' => 'Feature',
            ],
            'content' => [
                'class' => 'dNewsDesc', // মূল প্রতিবেদনের কনটেইনার
                'exclude' => [],
            ],
            'default_category' => 'Latest',
            'image' => [
                'from' => [], // ফিডে ছবি নেই
                'og' => true, // সংবাদ পেজের og:image
            ],
        ],

        'jagonews24' => [
            'enabled' => true,
            'name' => 'জাগোনিউজ ২৪',
            'feed' => 'https://www.jagonews24.com/rss/rss.xml',
            'allowed_hosts' => ['www.jagonews24.com'],
            'category_from' => 'tag',
            'category_map' => [
                'national' => 'Jayato',
                'country' => 'saradesh',
                'politics' => 'Politics',
                'economy' => 'Economics',
                'sports' => 'Sports',
                'international' => 'International',
                'probash' => 'International',
                'entertainment' => 'Entertainment',
                'education' => 'Education',
                'campus' => 'Education',
                'law-courts' => 'Jayato',
                'mass-media' => 'Feature',
                'jago-jobs' => 'Job',
                'religion' => 'Religion-Festivals',
            ],
            // সংবাদ পেজ Cloudflare-এ আটকানো; পূর্ণ বডি আনা যায় না, সারাংশই থাকে
            'content' => ['class' => null, 'exclude' => []],
            'default_category' => 'Latest',
            'image' => [
                'from' => ['media_content', 'enclosure'],
                // সাইটটি Cloudflare দিয়ে সুরক্ষিত, সংবাদ পেজ থেকে ছবি আনা যায় না;
                // ছবি না থাকলে সাইটের অটো ম্যাপ/গ্রাফ ছবি ব্যবহার হবে
                'og' => false,
            ],
        ],

        'banglanews24' => [
            // ফিডটি Cloudflare-এর চ্যালেঞ্জ দেখায় (PHP cURL → HTTP 403), তাই আপাতত বন্ধ।
            // সাইটটি অনুমতি দিলে enabled => true করলেই আবার চলবে।
            'enabled' => false,
            'name' => 'বাংলানিউজ ২৪',
            'feed' => 'https://www.banglanews24.com/rss.xml',
            'allowed_hosts' => ['www.banglanews24.com'],
            'category_from' => 'url',
            'category_map' => [
                'national' => 'Jayato',
                'saradesh' => 'saradesh',
                'daily-chittagong' => 'saradesh',
                'international' => 'International',
                'economics-business' => 'Economics',
                'corporatecorner' => 'Economics',
                'politics' => 'Politics',
                'sports' => 'Sports',
                'law-court' => 'Jayato',
                'lifestyle' => 'Feature',
                'islam' => 'Religion-Festivals',
                'horoscopes' => 'Feature',
                'banglanews-special' => 'Special-Report',
            ],
            'default_category' => 'Latest',
            'image' => [
                'from' => ['enclosure'], // ফিডে থাম্বনেইল আছে
                'og' => true,            // সংবাদ পেজে বড় (৮০০x৬০০) ছবি
            ],
            'content' => [
                'class' => null, // পেজ খোলা যায় না, তাই বডি কনটেইনার যাচাই করা যায়নি
                'exclude' => [],
            ],
        ],

    ],
];
