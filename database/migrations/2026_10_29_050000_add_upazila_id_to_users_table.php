<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'upazila_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('upazila_id')
                    ->nullable()
                    ->index()
                    ->constrained('locations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally do not drop this column automatically.
        // Existing reporter assignments must not be lost.
    }
};