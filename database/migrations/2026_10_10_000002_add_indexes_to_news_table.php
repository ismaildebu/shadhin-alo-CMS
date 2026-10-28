<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Home page / category listings: WHERE status = 1 ORDER BY created_at DESC
            $table->index(['status', 'created_at'], 'news_status_created_at_index');
            // Category blocks and related news
            $table->index(['category_id', 'status', 'created_at'], 'news_category_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex('news_status_created_at_index');
            $table->dropIndex('news_category_status_created_at_index');
        });
    }
};
