<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Source::factory(10)->create();

        \App\Models\Article::factory(50)->create()->each(function ($article): void {
            $categories = \App\Models\Category::inRandomOrder()->limit(random_int(1, 3))->get();
            $article->categories()->attach($categories);
        });
    }
}
