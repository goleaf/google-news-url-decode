<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CleanArticlesAndPopulateSourcesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_cleans_titles_and_populates_sources()
    {
        $sourceName = 'Example News';
        $sourceUrl = 'https://example.com/news';

        $article = Article::factory()->create([
            'title' => 'Important Event - Example News',
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
        ]);

        $this->assertEquals(0, Source::count());

        Artisan::call('news:clean-and-populate');

        $article->refresh();
        $this->assertEquals('Important Event', $article->title);
        $this->assertEquals('example.com', $article->source_domain);

        $this->assertDatabaseHas('sources', [
            'name' => $sourceName,
            'url' => $sourceUrl,
            'domain' => 'example.com',
        ]);
    }

    #[Test]
    public function it_inherits_guid_from_parent()
    {
        $parent = Article::factory()->create(['guid' => 'parent-guid']);
        $child = Article::factory()->create([
            'parent_id' => $parent->id,
            'guid' => null,
            'source_name' => 'Some Source', // Trigger update logic
        ]);

        Artisan::call('news:clean-and-populate');

        $child->refresh();
        $this->assertEquals('parent-guid', $child->guid);
    }
}
