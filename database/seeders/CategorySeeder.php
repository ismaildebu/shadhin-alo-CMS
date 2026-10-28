<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * name => slug. The slugs are explicit (not Str::slug of the Bengali name, which gives
     * unreadable results like "orth-banijz") because they are public URLs, and because the
     * home page sections look categories up by these exact slugs.
     */
    public const CATEGORIES = [
        'জাতীয়' => 'jatiyo',
        'সারাদেশ' => 'saradesh',
        'পদ্মার-এপার' => 'padmar-epar',
        'রাজনীতি' => 'rajniti',
        'কৃষি' => 'agriculture',
        'অর্থ-বাণিজ্য' => 'business',
        'খেলা' => 'sports',
        'বিনোদন' => 'entertainment',
        'আন্তর্জাতিক' => 'international',
        'অন্যান্য' => 'others',
        'আইন-আদালত' => 'law-court',
        'লাইফস্টাইল' => 'lifestyle',
        'শিক্ষা' => 'education',
        'স্বাস্থ্য' => 'health',
        'মতামত' => 'opinion',
        'সাহিত্য' => 'literature',
        'গণমাধ্যম' => 'media',
        'বিজ্ঞান-প্রযুক্তি' => 'technology',
        'বিশেষ রিপোর্ট' => 'special-report',
        'কর্মখালি' => 'jobs',
        'ফটোগ্যালারী' => 'photo-gallery',
        'ভিডিওগ্যালারী' => 'video-gallery',
        'আর্কাইভস' => 'archives',
    ];

    public function run(): void
    {
        $serial = 0;

        foreach (self::CATEGORIES as $name => $slug) {
            $serial++;

            // Safe to run again: an existing category (matched by slug) is left as the admin edited it.
            Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'serial' => $serial],
            );
        }
    }
}
