<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Indexes that reference the old boolean `status` column must go first
        //    (SQLite cannot drop an indexed column).
        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex('news_status_created_at_index');
            $table->dropIndex('news_category_status_created_at_index');
        });

        // 2. New columns. `workflow_status` temporarily sits next to the old `status`.
        Schema::table('news', function (Blueprint $table) {
            $table->string('shoulder')->nullable()->after('title');
            $table->foreignId('author_id')->nullable()->after('category_id')->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('news')->after('author_id');
            $table->string('image_caption')->nullable()->after('image');
            $table->string('image_credit')->nullable()->after('image_caption');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('workflow_status', 20)->default('draft');
            $table->softDeletes();
        });

        // 3. Convert existing data: 1 => published (keeping its original date), 0 => draft.
        DB::table('news')->where('status', 1)->update([
            'workflow_status' => 'published',
            'published_at' => DB::raw('created_at'),
        ]);
        DB::table('news')->where('status', 0)->update(['workflow_status' => 'draft']);

        // 4. Replace the boolean column with the new one.
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('news', function (Blueprint $table) {
            $table->renameColumn('workflow_status', 'status');
        });

        // 5. Indexes for the new access patterns.
        Schema::table('news', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'news_status_published_at_index');
            $table->index(['category_id', 'status', 'published_at'], 'news_category_status_published_at_index');
            $table->index(['type', 'status', 'published_at'], 'news_type_status_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex('news_status_published_at_index');
            $table->dropIndex('news_category_status_published_at_index');
            $table->dropIndex('news_type_status_published_at_index');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->boolean('old_status')->default(true);
        });

        DB::table('news')->where('status', 'published')->update(['old_status' => 1]);
        DB::table('news')->where('status', '!=', 'published')->update(['old_status' => 0]);

        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('news', function (Blueprint $table) {
            $table->renameColumn('old_status', 'status');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
        });
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn([
                'shoulder', 'author_id', 'type', 'image_caption', 'image_credit',
                'published_at', 'view_count', 'deleted_at',
            ]);
        });

        Schema::table('news', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'news_status_created_at_index');
            $table->index(['category_id', 'status', 'created_at'], 'news_category_status_created_at_index');
        });
    }
};
