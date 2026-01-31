<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_table_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('articles', [
                'id',
                'title',
                'original_url',
                'decoded_url',
                'source_name',
                'source_url',
                'guid',
                'published_at',
                'created_at',
                'updated_at',
            ])
        );
    }

    #[Test]
    public function it_can_be_created_using_factory(): void
    {
        $article = Article::factory()->create();
        $this->assertModelExists($article);
    }

    #[Test]
    public function it_has_category_relationships(): void
    {
        $article = Article::factory()->create();
        $category = Category::factory()->create();

        $article->categories()->attach($category);

        $this->assertTrue($article->categories->contains($category));
        $this->assertTrue($category->articles->contains($article));
    }

    #[Test]
    public function it_has_source_relationship(): void
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        $article = Article::factory()->create();

        $article->sources()->attach($source);

        $this->assertTrue($article->sources->contains($source));
        $this->assertEquals($source->id, $article->sources->first()->id);
    }

    #[Test]
    public function it_has_parent_child_relationship(): void
    {
        $parent = Article::factory()->create();
        $child = Article::factory()->create();

        $parent->relatedArticles()->attach($child);

        $this->assertTrue($parent->relatedArticles->contains($child));
        $this->assertTrue($child->parentArticles->contains($parent));
    }

    #[Test]
    public function it_guards_id_column(): void
    {
        $article = new Article(['id' => 999, 'title' => 'Test Title']);
        $this->assertNull($article->id);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $article = Article::factory()->create();
        $this->assertNotNull($article->created_at);
        $this->assertNotNull($article->updated_at);
    }
}
