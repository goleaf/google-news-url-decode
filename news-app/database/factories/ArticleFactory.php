<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'original_url' => $this->faker->unique()->url(),
            'decoded_url' => $this->faker->url(),
            'source_name' => $this->faker->company(),
            'source_url' => $this->faker->url(),
            'source_id' => null,
            'guid' => $this->faker->unique()->uuid(),
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'parent_id' => null,
        ];
    }
}
