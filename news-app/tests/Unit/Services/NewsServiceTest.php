<?php

namespace Tests\Unit\Services;

use App\Models\Article;
use App\Models\Category;
use App\Services\NewsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NewsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NewsService;
    }

    #[Test]
    public function it_can_extract_domain_from_url()
    {
        $this->assertEquals('example.com', $this->service->extractDomain('https://example.com/path/to/page'));
        $this->assertEquals('news.google.com', $this->service->extractDomain('http://news.google.com/index.html'));
        $this->assertNull($this->service->extractDomain(null));
    }

    #[Test]
    public function it_can_save_a_single_article()
    {
        $category = Category::factory()->create();
        $data = [
            'title' => 'Test Article',
            'original_url' => 'https://news.google.com/rss/123',
            'decoded_url' => 'https://actual-news.com/123',
            'source_name' => 'Actual News',
            'published_at' => now(),
            'guid' => 'unique-guid-123',
        ];

        $article = $this->service->saveArticle($category->id, $data);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertEquals($data['title'], $article->title);
        $this->assertEquals('actual-news.com', $article->source_domain);
        $this->assertTrue($article->categories->contains($category));
    }

    #[Test]
    public function it_can_save_an_article_cluster()
    {
        $category = Category::factory()->create();
        $packet = [
            'guid' => 'cluster-guid',
            'pubDate' => 'Sun, 25 Jan 2026 12:00:00 GMT',
            'main' => [
                'title' => 'Main Article',
                'original_url' => 'https://google.com/main',
                'decoded_url' => 'https://source.com/main',
                'source' => 'Source',
            ],
            'related' => [
                [
                    'title' => 'Related 1',
                    'original_url' => 'https://google.com/rel1',
                    'decoded_url' => 'https://other.com/rel1',
                    'source' => 'Other',
                ],
            ],
        ];

        $saved = $this->service->saveArticleCluster($category->id, $packet);

        $this->assertCount(2, $saved);
        $this->assertEquals('Main Article', $saved[0]->title);
        $this->assertEquals('Related 1', $saved[1]->title);
        $this->assertEquals($saved[0]->id, $saved[1]->parent_id);
        $this->assertEquals('source.com', $saved[0]->source_domain);
        $this->assertEquals('other.com', $saved[1]->source_domain);
    }
}
