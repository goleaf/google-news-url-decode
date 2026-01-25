<?php

namespace Tests\Feature;

use App\Filament\Pages\NewsProcessor;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsProcessorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_render_news_processor_page()
    {
        Livewire::test(NewsProcessor::class)
            ->assertSuccessful();
    }

    #[Test]
    public function it_loads_categories_with_rss_on_mount()
    {
        // On mount, config categories are synced to DB.
        $configCategoriesCount = count(config('news.categories', []));

        Livewire::test(NewsProcessor::class)
            ->assertSet('categories', function ($categories) use ($configCategoriesCount) {
                return count($categories) === $configCategoriesCount;
            });
    }

    #[Test]
    public function it_can_clean_and_save_articles()
    {
        $category = Category::factory()->create(['rss_url' => 'https://example.com/rss']);

        $component = Livewire::test(NewsProcessor::class);

        // We use a helper method to test the protected logic if we can't easily trigger it.
        // But let's try to use the component's internal state.

        $packet = [
            'guid' => 'test-guid',
            'pubDate' => now()->toRssString(),
            'main' => [
                'title' => 'Test Article Title - Test Source',
                'original_url' => 'https://news.google.com/rss/articles/123',
                'decoded_url' => 'https://destination.com/article',
                'source' => 'Test Source',
                'source_url' => 'https://source.com',
            ],
            'related' => [
                [
                    'title' => 'Related Article',
                    'original_url' => 'https://news.google.com/rss/articles/456',
                    'decoded_url' => 'https://destination.com/related',
                    'source' => 'Related Source',
                ],
            ],
        ];

        // Since savePacket was moved to NewsService, we test the service directly
        $service = new \App\Services\NewsService;
        $service->saveArticleCluster($category->id, $packet);

        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article Title', // Suffix should be removed by NewsService
            'decoded_url' => 'https://destination.com/article',
            'source_name' => 'Test Source',
        ]);

        $this->assertDatabaseHas('sources', [
            'name' => 'Test Source',
            'domain' => 'source.com',
        ]);

        $this->assertDatabaseHas('articles', [
            'title' => 'Related Article',
            'decoded_url' => 'https://destination.com/related',
        ]);

        $mainArticle = Article::where('title', 'Test Article Title')->first();
        $relatedArticle = Article::where('title', 'Related Article')->first();

        $this->assertEquals($mainArticle->id, $relatedArticle->parent_id);
    }
}
