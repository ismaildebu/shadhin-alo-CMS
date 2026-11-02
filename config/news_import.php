<?php

/*
 |--------------------------------------------------------------------------
 | স্বয়ংক্রিয় সংবাদ সংগ্রহ (RSS ও Google News সাইটম্যাপ)
 |--------------------------------------------------------------------------
 |
 | প্রতিটি সোর্সের ফিড, কোন সাইটের লিংক নেওয়া হবে, সোর্সের বিভাগ কোন স্থানীয়
 | ক্যাটাগরিতে (slug) যাবে, ছবি কোথা থেকে নেওয়া হবে এবং কতটি আইটেম নেওয়া হবে
 | এখানে ঠিক করা হয়। নতুন সোর্স যোগ করতে নিচের sources তালিকায় একটি এন্ট্রি দিন।
 | `feed`-এ RSS ঠিকানা অথবা Google News সাইটম্যাপ (news_sitemap.xml) — দুটোই চলবে।
 | সারাদেশ (saradesh) সংবাদে বিভাগ, জেলা ও উপজেলা বাধ্যতামূলক; আনা খসড়ায়
 | শিরোনামে থাকা স্থানের নাম দেখে বিভাগ/জেলা/উপজেলা নিজে থেকে বসিয়ে দেওয়া হয়।
 |
 | সব সংবাদ খসড়া (draft) হিসেবে আসে; সম্পাদক দেখে প্রকাশ করেন। প্রতিটি খসড়ার
 | শেষে সোর্সের মূল লিংক থাকে। `content` ব্লকে সোর্স পেজের কোন কনটেইনারে আসল
 | প্রতিবেদন থাকে তা দেওয়া হয়; সেটি না থাকলে ফিডের সারাংশই বডি হিসেবে বসে।
 |
 | এই সাইটে বর্তমানে থাকা ক্যাটাগরির slug (অ্যাডমিন → ক্যাটাগরি থেকে মিলিয়ে নিন):
 | jatiyo, saradesh, padmar-epar, rajniti, agriculture, business, sports,
 | entertainment, international, others, law-court, lifestyle, education, health,
 | opinion, literature, media, technology, special-report, jobs, photo-gallery,
 | video-gallery, archives
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
        'max_per_run' => 20,       // প্রতি চালে সব সোর্স মিলিয়ে সর্বোচ্চ নতুন সংবাদ
        'priority_sources' => ['khulnagazette', 'gramerkagoj'], // খুলনা বিভাগের সোর্স আগে
        'priority_per_source' => 6, // প্রায়োরিটি সোর্সে সর্বোচ্চ কতটি
        'other_per_source' => 2,    // বাকি সোর্সে সর্বোচ্চ কতটি (বাজেট থাকলে বাড়ে)
        'per_source_limit' => 8,    // --limit না দিলে সোর্সের সর্বোচ্চ সীমা
        'max_age_hours' => 10,     // এর চেয়ে পুরোনো সংবাদ বাদ
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
        'required' => true,      // ছবি না পেলে সংবাদটি খসড়াও করা হবে না
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
                'বাংলাদেশ' => 'jatiyo',
                'bangladesh' => 'jatiyo',
                'জাতীয়' => 'jatiyo',
                'জেলা' => 'saradesh',
                'সারাদেশ' => 'saradesh',
                'রাজনীতি' => 'rajniti',
                'বাণিজ্য' => 'business',
                'অর্থনীতি' => 'business',
                'খেলা' => 'sports',
                'ক্রিকেট' => 'sports',
                'ফুটবল' => 'sports',
                'বিনোদন' => 'entertainment',
                'টেলিভিশন' => 'entertainment',
                'আন্তর্জাতিক' => 'international',
                'যুক্তরাষ্ট্র' => 'international',
                'ইউরোপ' => 'international',
                'দূর পরবাস' => 'international',
                'শিক্ষা' => 'education',
                'বৃত্তি' => 'education',
                'স্বাস্থ্য' => 'health',
                'বিজ্ঞান' => 'technology',
                'প্রযুক্তি' => 'technology',
                'biology' => 'technology',
                'অপরাধ' => 'jatiyo', // আদালতের আলাদা ক্যাটাগরি নেই
                'মতামত' => 'opinion',
                'সম্পাদকীয়' => 'opinion',
                'কলাম' => 'opinion',
                'সাক্ষাৎকার' => 'opinion',
                'writings' => 'opinion',
                'সাহিত্য' => 'literature',
                'জীবনযাপন' => 'lifestyle',
                'লাইফস্টাইল' => 'lifestyle',
                'ফ্যাশন' => 'lifestyle',
                'লুক' => 'lifestyle',
                'কর্মখালি' => 'jobs',
                'নিয়োগ' => 'jobs',
                'গণমাধ্যম' => 'media',
                'মিডিয়া' => 'media',
                'বিশেষ প্রতিবেদন' => 'special-report',
                'feature' => 'lifestyle',
                'পরিবেশ' => 'jatiyo',
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
            'default_category' => 'others',
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
                'bangladesh' => 'jatiyo',
                'whole-country' => 'saradesh',
                'capital' => 'jatiyo',
                'politics' => 'rajniti',
                'economics' => 'business',
                'sports' => 'sports',
                'entertainment' => 'entertainment',
                'international' => 'international',
                'technology' => 'technology',
                'education' => 'education',
                'health' => 'health',
                'law-court' => 'jatiyo',
                'opinion' => 'opinion',
                'lifestyle' => 'lifestyle',
            ],
            'content' => [
                'class' => 'dNewsDesc', // মূল প্রতিবেদনের কনটেইনার
                'exclude' => [],
            ],
            'default_category' => 'others',
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
                'national' => 'jatiyo',
                'country' => 'saradesh',
                'politics' => 'rajniti',
                'economy' => 'business',
                'sports' => 'sports',
                'international' => 'international',
                'probash' => 'international',
                'entertainment' => 'entertainment',
                'education' => 'education',
                'campus' => 'education',
                'law-courts' => 'jatiyo',
                'mass-media' => 'media',
                'jago-jobs' => 'jobs',
                'religion' => 'others',
            ],
            // সংবাদ পেজ Cloudflare-এ আটকানো; পূর্ণ বডি আনা যায় না, সারাংশই থাকে
            'content' => ['class' => null, 'exclude' => []],
            'default_category' => 'others',
            'image' => [
                'from' => ['media_content', 'enclosure'],
                // সাইটটি Cloudflare দিয়ে সুরক্ষিত, সংবাদ পেজ থেকে ছবি আনা যায় না;
                // ছবি না থাকলে সাইটের অটো ম্যাপ/গ্রাফ ছবি ব্যবহার হবে
                'og' => false,
            ],
        ],

        'banglanews24' => [
            // www হোস্টে Cloudflare 403 দেয়, কিন্তু www ছাড়া ফিডটি খোলে; সংবাদ পেজ দুভাবেই
            // আটকানো, তাই পূর্ণ বডি আসে না — ফিডের সারাংশ ও থাম্বনেইলই থাকে।
            'enabled' => true,
            'name' => 'বাংলানিউজ ২৪',
            'feed' => 'https://banglanews24.com/rss.xml',
            'allowed_hosts' => ['www.banglanews24.com', 'banglanews24.com'],
            'category_from' => 'url',
            'category_map' => [
                'national' => 'jatiyo',
                'saradesh' => 'saradesh',
                'daily-chittagong' => 'saradesh',
                'international' => 'international',
                'economics-business' => 'business',
                'corporatecorner' => 'business',
                'politics' => 'rajniti',
                'sports' => 'sports',
                'education' => 'education',
                'law-court' => 'jatiyo',
                'lifestyle' => 'lifestyle',
                'islam' => 'others',
                'horoscopes' => 'entertainment',
                'banglanews-special' => 'special-report',
            ],
            'default_category' => 'others',
            'image' => [
                'from' => ['enclosure'], // ফিডে থাম্বনেইল আছে
                'og' => false,           // সংবাদ পেজ খোলা যায় না, বড় ছবি পাওয়া যাবে না
            ],
            'content' => [
                'class' => null, // পেজ খোলা যায় না, তাই বডি কনটেইনার যাচাই করা যায়নি
                'exclude' => [],
            ],
        ],

        'bdnews24' => [
            // আরএসএস নেই; বিডিনিউজ বাংলা Google News সাইটম্যাপ প্রকাশ করে, তাই সেটিই পড়া হয়।
            'enabled' => true,
            'name' => 'বিডিনিউজ ২৪',
            'feed' => 'https://bangla.bdnews24.com/news_sitemap.xml',
            'allowed_hosts' => ['bangla.bdnews24.com'],
            'category_from' => 'url', // সাইটম্যাপে বিভাগ নেই; লিংকের প্রথম অংশ
            'category_map' => [
                'bangladesh' => 'jatiyo',
                'samagrabangladesh' => 'jatiyo',
                'politics' => 'rajniti',
                'ctg' => 'saradesh',
                'sport' => 'sports',
                'cricket' => 'sports',
                'world' => 'international',
                'probash' => 'international',
                'business' => 'business',
                'economy' => 'business',
                'corporate' => 'business',
                'stocks' => 'business',
                'tech' => 'technology',
                'health' => 'health',
                'education' => 'education',
                'campus' => 'education',
                'opinion' => 'opinion',
                'amarkotha' => 'opinion',
                'glitz' => 'entertainment',
                'lifestyle' => 'lifestyle',
                'arts' => 'lifestyle',
                'onyachokhe' => 'lifestyle',
                'media_bn' => 'media',
                'photostory' => 'photo-gallery',
            ],
            'content' => [
                'class' => 'details-brief',
                'exclude' => ['jsembed-wrapper'], // ইনস্টাগ্রাম/সোশ্যাল এমবেডের লেখা বডিতে ঢোকে না
            ],
            'default_category' => 'others',
            'image' => [
                'from' => [], // সাইটম্যাপের image:loc
                'og' => true, // সাইটম্যাপে ছবি না থাকলে সংবাদ পেজ
            ],
        ],

        'khulnagazette' => [
            'enabled' => true,
            'name' => 'খুলনা গেজেট',
            'feed' => 'https://khulnagazette.com/feed/',
            'allowed_hosts' => ['khulnagazette.com', 'www.khulnagazette.com'],
            'category_from' => 'tag',
            'category_map' => [
                'বাংলাদেশ' => 'jatiyo',
                'টপ নিউজ' => 'others',
                'রাজনীতি' => 'rajniti',
                'আন্তর্জাতিক' => 'international',
                'খেলা' => 'sports',
                'শিক্ষা' => 'education',
                'বিনোদন' => 'entertainment',
                'খুলনা' => 'saradesh',
                'সাতক্ষীরা' => 'saradesh',
                'মেহেরপুর' => 'saradesh',
                'মুক্ত ভাবনা' => 'opinion',
            ],
            'content' => [
                'class' => 'entry-content',
                'exclude' => ['kg-article-tags'],
            ],
            'default_category' => 'others',
            'image' => [
                'from' => ['media_content', 'enclosure'],
                'og' => true,
            ],
        ],

        'gramerkagoj' => [
            // আরএসএস নেই; গ্রামের কাগজ Google News সাইটম্যাপ প্রকাশ করে।
            'enabled' => true,
            'name' => 'গ্রামের কাগজ',
            'feed' => 'https://www.gramerkagoj.com/sitemap/news-sitemap.xml',
            'allowed_hosts' => ['gramerkagoj.com', 'www.gramerkagoj.com'],
            'category_from' => 'url',
            'category_map' => [
                'national' => 'jatiyo',
                'country-news' => 'saradesh',
                'south-west' => 'saradesh', // খুলনা বিভাগের জেলা ও উপজেলার সংবাদ
                'politics' => 'rajniti',
                'economy' => 'business',
                'sports' => 'sports',
                'international' => 'international',
                'entertainment' => 'entertainment',
                'information-technology' => 'technology',
                'educational-message' => 'education',
                'islamic-world' => 'international',
                'court-law' => 'jatiyo',
            ],
            'content' => [
                'class' => 'dtl_content_section',
                'exclude' => [],
            ],
            'default_category' => 'others',
            'image' => [
                'from' => [], // সাইটম্যাপের image:loc
                'og' => true,
            ],
        ],

        'kalbela' => [
            // পুরো সাইট Cloudflare-এর চ্যালেঞ্জে আটকানো (PHP cURL ও সাধারণ curl — সব 403)।
            // সাইটটি অনুমতি দিলে enabled => true করলেই চলবে।
            'enabled' => false,
            'name' => 'কালবেলা',
            'feed' => 'https://www.kalbela.com/rss',
            'allowed_hosts' => ['www.kalbela.com', 'kalbela.com'],
            'category_from' => 'url',
            'category_map' => [
                'saradesh' => 'saradesh',
                'bangladesh' => 'jatiyo',
                'politics' => 'rajniti',
                'economics' => 'business',
                'sports' => 'sports',
                'international' => 'international',
                'entertainment' => 'entertainment',
                'technology' => 'technology',
                'education' => 'education',
                'health' => 'health',
                'opinion' => 'opinion',
            ],
            'default_category' => 'others',
            'image' => [
                'from' => ['media_content', 'enclosure'],
                'og' => false,
            ],
            'content' => [
                'class' => null, // পেজ খোলা যায় না, বডি কনটেইনার যাচাই করা যায়নি
                'exclude' => [],
            ],
        ],

    ],
];
