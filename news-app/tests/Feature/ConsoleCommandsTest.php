<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_merge_duplicate_articles()
    {
        \Illuminate\Support\Facades\Schema::table('articles', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropUnique(['original_url']);
        });

        $url = 'https://news.google.com/articles/duplicate';

        $category1 = Category::factory()->create(['name' => 'Cat 1']);
        $category2 = Category::factory()->create(['name' => 'Cat 2']);

        $master = Article::factory()->create([
            'original_url' => $url,
            'title' => 'Master Title',
            'guid' => 'guid-1',
        ]);
        $master->categories()->attach($category1);

        $duplicate = Article::factory()->create([
            'original_url' => $url,
            'title' => 'Duplicate Title',
            'guid' => 'guid-2',
        ]);
        $duplicate->categories()->attach($category2);

        // Add a child to the duplicate
        $child = Article::factory()->create([
            'parent_id' => $duplicate->id,
            'title' => 'Child Title',
        ]);

        Artisan::call('news:merge-duplicates');

        // One should be gone, one should remain
        $remaining = Article::where('original_url', $url)->first();
        $this->assertNotNull($remaining);
        $this->assertEquals(1, Article::where('original_url', $url)->count());

        // Check master has both categories
        $this->assertEquals(2, $remaining->categories()->count());
        $this->assertTrue($remaining->categories->contains($category1));
        $this->assertTrue($remaining->categories->contains($category2));

        // Check child is now linked to the remaining article
        $child->refresh();
        $this->assertEquals($remaining->id, $child->parent_id);
    }
}
