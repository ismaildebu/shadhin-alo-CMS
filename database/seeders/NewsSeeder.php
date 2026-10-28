<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\News;
use App\Models\Category;
use Illuminate\Support\Str;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        if ($categories->count() == 0) {
            return;
        }

        for ($i = 1; $i <= 50; $i++) {

            $title = "ডেমো সংবাদ {$i}";

            News::create([

                'category_id' => $categories->random()->id,

                'title' => $title,

                'slug' => Str::slug($title.'-'.$i),

                'description' =>
                'এটি স্বাধীন আলো CMS-এর ডেমো সংবাদ। এখানে পরবর্তীতে আসল সংবাদ প্রকাশ করা হবে।',

                'reporter' => 'স্বাধীন আলো',

                'image' => null,

                'breaking' => rand(0,1),

                'featured' => rand(0,1),

                'status' => News::STATUS_PUBLISHED,

                'published_at' => now()->subHours($i),

            ]);
        }
    }
}