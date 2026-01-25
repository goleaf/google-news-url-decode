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
            ->assertSee('Related Articles');
    }

    public function test_edit_page_shows_parent_and_siblings_when_editing_child()
    {
        $user = User::factory()->create();
        $parent = Article::factory()->create(['title' => 'Parent Article']);
        $child1 = Article::factory()->create(['parent_id' => $parent->id, 'title' => 'Child 1']);
        $child2 = Article::factory()->create(['parent_id' => $parent->id, 'title' => 'Child 2']);

        $this->actingAs($user);

        // Edit Child 1, should see Parent and Child 2
        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $child1->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Parent Article')
            ->assertSee('Child 2')
            ->assertDontSee('Child 1', false); // Should not see itself in the related list (excluding search results/other context)
    }
}
