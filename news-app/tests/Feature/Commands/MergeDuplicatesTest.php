<?php

namespace Tests\Feature\Commands;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MergeDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_merge_duplicate_articles(): void
    {
        // Drop unique constraint temporarily to allow inserting duplicates for testing the command
        \Illuminate\Support\Facades\Schema::table('articles', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropUnique(['original_url']);
        });

        $url = 'http://example.com/article';

        $category1 = Category::factory()->create(['name' => 'Cat 1']);
        $category2 = Category::factory()->create(['name' => 'Cat 2']);

        $master = Article::factory()->create(['original_url' => $url]);
        $master->categories()->attach($category1);

        $duplicate = Article::factory()->create(['original_url' => $url]);
        $duplicate->categories()->attach($category2);

        $childOfDuplicate = Article::factory()->create([
            'original_url' => 'http://example.com/child',
        ]);

        // Link child to duplicate via pivot table
        $duplicate->relatedArticles()->attach($childOfDuplicate);

        $this->artisan('news:merge-duplicates')
            ->expectsOutput('Scanning for duplicates based on original_url...')
            ->expectsOutput('Found 1 URLs with duplicates. Merging...')
            ->assertExitCode(0);

        $remaining = Article::where('original_url', $url)->first();
        $this->assertNotNull($remaining);
        $this->assertEquals(1, Article::where('original_url', $url)->count());

        // Check categories were merged
        $this->assertCount(2, $remaining->categories);
        $this->assertTrue($remaining->categories->contains($category1));
        $this->assertTrue($remaining->categories->contains($category2));

        // Check child relationship was moved via pivot table
        $childOfDuplicate->refresh();
        $this->assertTrue($remaining->relatedArticles->contains($childOfDuplicate));
    }

    #[Test]
    public function it_outputs_no_duplicates_when_none_found(): void
    {
        $this->artisan('news:merge-duplicates')
            ->expectsOutput('No duplicates found.')
            ->assertExitCode(0);
    }
}
