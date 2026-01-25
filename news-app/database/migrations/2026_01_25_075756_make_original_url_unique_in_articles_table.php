<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Change to string to allow indexing (SQLite handles long strings in unique index fine)
            $table->string('original_url', 1024)->change();
            $table->string('guid', 1024)->nullable()->change();

            // Add unique index to original_url to prevent duplicates during concurrent parsing
            $table->unique('original_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['original_url']);
            $table->text('original_url')->change();
            $table->text('guid')->nullable()->change();
        });
    }
};
