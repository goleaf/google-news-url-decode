<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate pairs
            $table->unique(['article_id', 'category_id']);
        });

        // Migrate existing data
        if (Schema::hasColumn('articles', 'category_id')) {
            $articles = DB::table('articles')->whereNotNull('category_id')->get();
            $records = [];
            foreach ($articles as $article) {
                $records[] = [
                    'article_id' => $article->id,
                    'category_id' => $article->category_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Chunk insert to avoid memory issues
                if (count($records) > 1000) {
                    DB::table('article_category')->insertOrIgnore($records);
                    $records = [];
                }
            }
            if (! empty($records)) {
                DB::table('article_category')->insertOrIgnore($records);
            }
        }

        // Drop old column
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['category_id']); // Assuming constraint name follows convention
            $table->dropColumn('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained();
        });

        // Restore data (roughly - takes first category)
        $pairs = DB::table('article_category')->get();
        foreach ($pairs as $pair) {
            DB::table('articles')
                ->where('id', $pair->article_id)
                ->update(['category_id' => $pair->category_id]);
        }

        Schema::dropIfExists('article_category');
    }
};
