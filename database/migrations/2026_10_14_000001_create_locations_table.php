<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('type', 10); // division | district | upazila
            $table->string('name_bn');
            $table->string('name_en');
            // English slug, unique among siblings only: the same upazila name exists in several districts.
            $table->string('slug', 100);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
            $table->index(['type', 'parent_id']);
        });

        Schema::table('news', function (Blueprint $table) {
            // The most specific place the article is about: a division, a district or an upazila.
            $table->foreignId('location_id')->nullable()->after('category_id')
                ->constrained('locations')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            // A district correspondent: may only report for this district (and its upazilas).
            $table->foreignId('district_id')->nullable()->after('role')
                ->constrained('locations')->nullOnDelete();
        });
        // The geography itself is loaded with: php artisan locations:import
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('district_id');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
        });
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('location_id');
        });

        // Every row can be the RESTRICTed parent of other rows, so a populated table cannot be dropped
        // as it is (the drop deletes rows one by one and trips the foreign key). Cut the tree apart first.
        if (Schema::hasTable('locations')) {
            DB::table('locations')->update(['parent_id' => null]);
        }

        Schema::dropIfExists('locations');
    }
};
