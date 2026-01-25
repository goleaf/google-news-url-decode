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
    public function it_has_correct_table_columns()
    {
        $this->assertTrue(
            Schema::hasColumns('articles', [
                'id', 'title', 'original_url', 'decoded_url', 'source_name',
                'source_url', 'source_id', 'guid', 'published_at', 'parent_id',
                'created_at', 'updated_at',
            ])
        );
    }

    #[Test]
    public function it_can_be_created_using_factory()
    {
        $article = Article::factory()->create();
        $this->assertModelExists($article);
    }

    #[Test]
    public function it_has_category_relationships()
    {
        $article = Article::factory()->create();
        $category = Category::factory()->create();

        $article->categories()->attach($category);

        $this->assertTrue($article->categories->contains($category));
        $this->assertTrue($category->articles->contains($article));
    }

    #[Test]
    public function it_has_source_relationship()
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        $article = Article::factory()->create(['source_id' => $source->id]);

        $this->assertInstanceOf(Source::class, $article->source);
        $this->assertEquals($source->id, $article->source->id);
    }

    #[Test]
    public function it_has_parent_child_relationship()
    {
        $parent = Article::factory()->create();
        $child = Article::factory()->create(['parent_id' => $parent->id]);

        $this->assertInstanceOf(Article::class, $child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));
    }

    #[Test]
    public function it_guards_id_column()
    {
        $article = new Article(['id' => 999, 'title' => 'Test Title']);
        $this->assertNull($article->id);
    }

    #[Test]
    public function it_has_timestamps()
    {
        $article = Article::factory()->create();
        $this->assertNotNull($article->created_at);
        $this->assertNotNull($article->updated_at);
    }
}
