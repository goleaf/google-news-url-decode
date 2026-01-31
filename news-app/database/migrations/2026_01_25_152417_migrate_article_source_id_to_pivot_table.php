<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $articles = \DB::table('articles')
            ->whereNotNull('source_id')
            ->select('id', 'source_id', 'created_at', 'updated_at')
            ->get();

        foreach ($articles as $article) {
            \DB::table('article_source')->insertOrIgnore([
                'article_id' => $article->id,
                'source_id' => $article->source_id,
                'created_at' => $article->created_at ?: now(),
                'updated_at' => $article->updated_at ?: now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::table('article_source')->truncate();
    }
};
