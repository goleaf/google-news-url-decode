<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create());
    }

    #[Test]
    public function it_can_render_list_page(): void
    {
        $this->get(ArticleResource::getUrl('index'))
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_list_articles(): void
    {
        $articles = Article::factory()->count(5)->create();

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->call('loadTable')
            ->assertCanSeeTableRecords($articles);
    }

    #[Test]
    public function it_can_search_articles(): void
    {
        $article = Article::factory()->create(['title' => 'Specific Unique Title']);
        $otherArticle = Article::factory()->create(['title' => 'Other Title']);

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->call('loadTable')
            ->searchTable('Specific Unique Title')
            ->assertCanSeeTableRecords([$article])
            ->assertCanNotSeeTableRecords([$otherArticle]);
    }

    #[Test]
    public function it_can_filter_articles_by_category(): void
    {
        $category = Category::factory()->create();
        $article = Article::factory()->create();
        $article->categories()->attach($category);

        $otherArticle = Article::factory()->create();

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->call('loadTable')
            ->filterTable('categories', $category->id)
            ->assertCanSeeTableRecords([$article])
            ->assertCanNotSeeTableRecords([$otherArticle]);
    }

    #[Test]
    public function it_can_filter_articles_by_decoded_url_presence(): void
    {
        $articleWithDecoded = Article::factory()->create(['decoded_url' => 'https://example.com']);
        $articleWithoutDecoded = Article::factory()->create(['decoded_url' => null]);

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->call('loadTable')
            ->filterTable('has_decoded_url', true)
            ->assertCanSeeTableRecords([$articleWithDecoded])
            ->assertCanNotSeeTableRecords([$articleWithoutDecoded]);
    }

    #[Test]
    public function it_can_filter_articles_by_publication_date(): void
    {
        $today = now();
        $yesterday = now()->subDay();
        $lastWeek = now()->subWeek();

        $articleToday = Article::factory()->create(['published_at' => $today]);
        $articleYesterday = Article::factory()->create(['published_at' => $yesterday]);
        $articleLastWeek = Article::factory()->create(['published_at' => $lastWeek]);

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->call('loadTable')
            ->filterTable('published_at', [
                'published_from' => $yesterday->format('Y-m-d'),
                'published_until' => $today->format('Y-m-d'),
            ])
            ->assertCanSeeTableRecords([$articleToday, $articleYesterday])
            ->assertCanNotSeeTableRecords([$articleLastWeek]);
    }

    #[Test]
    public function it_can_render_create_page(): void
    {
        $this->get(ArticleResource::getUrl('create'))
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_create_article(): void
    {
        $category = Category::factory()->create();
        $newData = Article::factory()->make();

        Livewire::test(ArticleResource\Pages\CreateArticle::class)
            ->set('data.title', $newData->title)
            ->set('data.categories', [$category->id])
            ->set('data.original_url', $newData->original_url)
            ->set('data.decoded_url', $newData->decoded_url)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('articles', [
            'title' => $newData->title,
            'original_url' => $newData->original_url,
        ]);
    }

    #[Test]
    public function it_can_render_edit_page(): void
    {
        $article = Article::factory()->create();

        $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_update_article(): void
    {
        $article = Article::factory()->create();
        $category = Category::factory()->create();
        $newData = Article::factory()->make();

        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $article->getRouteKey(),
        ])
            ->set('data.title', $newData->title)
            ->set('data.categories', [$category->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => $newData->title,
        ]);
    }

    #[Test]
    public function it_can_delete_article(): void
    {
        $article = Article::factory()->create();

        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $article->getRouteKey(),
        ])
            ->callAction('delete');

        $this->assertModelMissing($article);
    }

    #[Test]
    public function it_can_bulk_delete_articles(): void
    {
        $articles = Article::factory()->count(3)->create();

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->callTableBulkAction(DeleteBulkAction::class, $articles);

        foreach ($articles as $article) {
            $this->assertModelMissing($article);
        }
    }

    #[Test]
    public function it_can_list_children_in_relation_manager(): void
    {
        $parent = Article::factory()->create();
        $children = Article::factory()->count(3)->create();

        // Create relationships via pivot table
        foreach ($children as $child) {
            $parent->relatedArticles()->attach($child);
        }

        Livewire::test(ArticleResource\RelationManagers\RelatedArticlesRelationManager::class, [
            'ownerRecord' => $parent,
            'pageClass' => ArticleResource\Pages\EditArticle::class,
        ])
            ->call('loadTable')
            ->assertCanSeeTableRecords($children);
    }
}
