<?php

namespace Tests\Feature;

use App\Filament\Widgets\ArticleStatsOverview;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_render_widget(): void
    {
        Article::factory()->count(10)->create();
        Article::factory()->count(5)->create(['created_at' => today()]);
        Article::factory()->create(['source_name' => 'Unique Source']);

        Livewire::test(ArticleStatsOverview::class)
            ->assertSee('Total Articles')
            ->assertSee('16')
            ->assertSee('Saved Today')
            ->assertSee('Sources');
    }
}
