<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_news', function (Blueprint $table) {
            $table->foreignId('news_id')
                ->constrained('news')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->primary(['news_id', 'category_id']);
            $table->index('category_id');
        });

        // আগের প্রতিটি খবরের ক্যাটাগরি নতুন টেবিলে সংরক্ষণ
        DB::table('news')
            ->select(['id', 'category_id'])
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                $pivotRows = [];

                foreach ($rows as $row) {
                    $pivotRows[] = [
                        'news_id' => $row->id,
                        'category_id' => $row->category_id,
                    ];
                }

                if ($pivotRows !== []) {
                    DB::table('category_news')
                        ->insertOrIgnore($pivotRows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_news');
    }
};