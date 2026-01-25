<?php

namespace Tests\Feature;

use App\Filament\Pages\NewsProcessor;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsProcessorPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_render_news_processor_page()
    {
        $this->get(NewsProcessor::getUrl())->assertSuccessful();
    }

    #[Test]
    public function it_mounts_categories_with_rss_urls()
    {
        $configCategoriesCount = count(config('news.categories', []));

        Livewire::test(NewsProcessor::class)
            ->assertSet('categories', function ($categories) use ($configCategoriesCount) {
                return count($categories) === $configCategoriesCount;
            });
    }

    #[Test]
    public function it_can_start_processing()
    {
        Category::factory()->create(['rss_url' => 'https://example.com/rss']);

        Livewire::test(NewsProcessor::class)
            ->call('startProcessing')
            ->assertSet('isProcessing', true)
            ->assertSet('status', 'processing');
    }
}

/**
 * Subclass to test protected methods of NewsProcessor
 */
class NewsProcessorTestable extends NewsProcessor
{
    public function publicCleanUrl($url)
    {
        return $this->cleanUrl($url);
    }

    public function publicIsActuallyDecoded($orig, $dec)
    {
        return $this->isActuallyDecoded($orig, $dec);
    }
}

class NewsProcessorLogicTest extends TestCase
{
    #[Test]
    public function it_cleans_urls_correctly()
    {
        $processor = new NewsProcessorTestable;

        $this->assertEquals(
            'https://example.com/path',
            $processor->publicCleanUrl('https://example.com/path?utm_source=foo')
        );

        $this->assertEquals(
            'https://example.com/watch?v=123',
            $processor->publicCleanUrl('https://example.com/watch?v=123&other=bar')
        );
    }

    #[Test]
    public function it_identifies_decoded_urls()
    {
        $processor = new NewsProcessorTestable;

        // Same URL should not be considered decoded
        $this->assertFalse(
            $processor->publicIsActuallyDecoded(
                'https://news.google.com/articles/123',
                'https://news.google.com/articles/123'
            )
        );

        // Google News internal URLs should not be considered decoded
        $this->assertFalse(
            $processor->publicIsActuallyDecoded(
                'https://news.google.com/articles/123',
                'https://news.google.com/rss/articles/456'
            )
        );

        // Real destination URL should be considered decoded
        $this->assertTrue(
            $processor->publicIsActuallyDecoded(
                'https://news.google.com/articles/123',
                'https://nytimes.com/article/789'
            )
        );
    }
}
