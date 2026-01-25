<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleResourceRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_shows_related_articles_relation_manager()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();

        // Create some children
        Article::factory()->count(3)->create([
            'parent_id' => $article->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $article->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Related Articles'); // The title of the relation manager
    }
}
