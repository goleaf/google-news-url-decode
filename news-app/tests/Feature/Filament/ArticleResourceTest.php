<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
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

        // Authenticate as a user since policies might be active
        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function it_can_render_list_page()
    {
        $this->get(ArticleResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function it_can_list_articles()
    {
        $articles = Article::factory()->count(10)->create();

        Livewire::test(ArticleResource\Pages\ListArticles::class)
            ->call('loadTable') // Needed because of deferLoading()
            ->assertCanSeeTableRecords($articles);
    }

    #[Test]
    public function it_can_render_create_page()
    {
        $this->get(ArticleResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function it_can_create_article()
    {
        $categories = Category::factory()->count(2)->create();
        $newData = Article::factory()->make();

        Livewire::test(ArticleResource\Pages\CreateArticle::class)
            ->set('data.title', $newData->title)
            ->set('data.categories', $categories->pluck('id')->toArray())
            ->set('data.original_url', $newData->original_url)
            ->set('data.decoded_url', $newData->decoded_url)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('articles', [
            'title' => $newData->title,
        ]);
    }

    #[Test]
    public function it_can_render_edit_page()
    {
        $article = Article::factory()->create();

        $this->get(ArticleResource::getUrl('edit', ['record' => $article]))->assertSuccessful();
    }

    #[Test]
    public function it_can_edit_article()
    {
        $article = Article::factory()->create();
        $categories = Category::factory()->count(2)->create();
        $newTitle = 'Updated Title';

        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $article->getRouteKey(),
        ])
            ->set('data.title', $newTitle)
            ->set('data.categories', $categories->pluck('id')->toArray())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => $newTitle,
        ]);
    }

    #[Test]
    public function it_can_delete_article()
    {
        $article = Article::factory()->create();

        Livewire::test(ArticleResource\Pages\EditArticle::class, [
            'record' => $article->getRouteKey(),
        ])
            ->callAction('delete');

        $this->assertModelMissing($article);
    }
}
