<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_render_dashboard()
    {
        $this->get('/')->assertStatus(200);
    }

    #[Test]
    public function it_displays_article_stats_on_dashboard()
    {
        \Illuminate\Support\Facades\Cache::forget('article_stats');
        $this->actingAs(\App\Models\User::factory()->create());

        Article::factory()->count(5)->create();
        Category::factory()->count(3)->create();
        Source::factory()->count(2)->create();

        \Livewire\Livewire::test(\App\Filament\Widgets\ArticleStatsOverview::class)
            ->assertSee('Total Articles')
            ->assertSee('Sources')
            ->assertSee('5');
    }
}
