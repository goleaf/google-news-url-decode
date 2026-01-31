<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MergeDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_merges_articles_with_duplicate_urls(): void
    {
        \Illuminate\Support\Facades\Schema::table('articles', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropUnique(['original_url']);
        });

        $url = 'https://news.google.com/articles/duplicate';

        $category1 = Category::factory()->create(['name' => 'Cat1']);
        $category2 = Category::factory()->create(['name' => 'Cat2']);

        $master = Article::factory()->create([
            'original_url' => $url,
            'title' => 'Master Article',
            'guid' => 'guid-master',
        ]);
        $master->categories()->attach($category1);

        $duplicate = Article::factory()->create([
            'original_url' => $url,
            'title' => 'Duplicate Article',
            'guid' => 'guid-duplicate',
        ]);
        $duplicate->categories()->attach($category2);

        $child = Article::factory()->create([
            'original_url' => 'https://news.google.com/articles/child',
        ]);

        // Create relationship via pivot table
        $duplicate->relatedArticles()->attach($child);

        $this->assertEquals(2, Article::where('original_url', $url)->count());

        Artisan::call('news:merge-duplicates');

        $remaining = Article::where('original_url', $url)->first();
        $this->assertNotNull($remaining);
        $this->assertEquals(1, Article::where('original_url', $url)->count());

        // Verify categories merged
        $this->assertTrue($remaining->categories->contains($category1));
        $this->assertTrue($remaining->categories->contains($category2));

        // Verify children moved via pivot
        $this->assertTrue($remaining->relatedArticles->contains($child));
    }
}
