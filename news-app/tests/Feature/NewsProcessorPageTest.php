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
    public function it_can_render_news_processor_page(): void
    {
        $this->get(NewsProcessor::getUrl())->assertSuccessful();
    }

    #[Test]
    public function it_mounts_categories_with_rss_urls(): void
    {
        $configCategoriesCount = count(config('news.categories', []));

        Livewire::test(NewsProcessor::class)
            ->assertSet('categories', fn($categories): bool => count($categories) === $configCategoriesCount);
    }

    #[Test]
    public function it_can_start_processing(): void
    {
        Category::factory()->create(['rss_url' => 'https://example.com/rss']);

        Livewire::test(NewsProcessor::class)
            ->call('startProcessing')
            ->assertSet('isProcessing', true)
            ->assertSet('status', 'processing');
    }
}
