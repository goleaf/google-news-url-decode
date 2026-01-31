<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categories = \DB::table('categories')
            ->whereNotNull('parent_id')
            ->select('id', 'parent_id', 'created_at', 'updated_at')
            ->get();

        foreach ($categories as $category) {
            \DB::table('category_related')->insertOrIgnore([
                'parent_id' => $category->parent_id,
                'category_id' => $category->id,
                'created_at' => $category->created_at ?: now(),
                'updated_at' => $category->updated_at ?: now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::table('category_related')->truncate();
    }
};
