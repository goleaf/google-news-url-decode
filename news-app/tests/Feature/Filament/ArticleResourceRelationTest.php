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

    public function test_edit_page_shows_related_articles_relation_manager(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();

        // Create some related articles via pivot
        $related = Article::factory()->count(3)->create();
        foreach ($related as $child) {
            $article->relatedArticles()->attach($child);
        }

        $this->actingAs($user);

        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $article->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Related News (Children)');
    }

    public function test_edit_page_shows_parent_articles_relation_manager(): void
    {
        $user = User::factory()->create();
        $parent = Article::factory()->create(['title' => 'Parent Article']);
        $child1 = Article::factory()->create(['title' => 'Child 1']);
        $child2 = Article::factory()->create(['title' => 'Child 2']);

        // Link via pivot table
        $parent->relatedArticles()->attach([$child1->id, $child2->id]);

        $this->actingAs($user);

        // Edit Child 1, should see Parent in parentArticles
        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $child1->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Parent Article');
    }
}
