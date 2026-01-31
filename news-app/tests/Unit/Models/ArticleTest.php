<?php

namespace Tests\Unit\Models;

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_factory(): void
    {
        $article = Article::factory()->create();
        $this->assertInstanceOf(Article::class, $article);
    }

    #[Test]
    public function it_belongs_to_many_sources(): void
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        $article = Article::factory()->create();

        $article->sources()->attach($source);

        $this->assertTrue($article->sources->contains($source));
        $this->assertEquals($source->id, $article->sources->first()->id);
    }

    #[Test]
    public function it_belongs_to_many_categories(): void
    {
        $article = Article::factory()->create();
        $categories = Category::factory()->count(3)->create();

        $article->categories()->attach($categories);

        $this->assertCount(3, $article->categories);
    }

    #[Test]
    public function it_can_have_parent_and_related_articles(): void
    {
        $parent = Article::factory()->create();
        $child = Article::factory()->create();

        $parent->relatedArticles()->attach($child);

        $this->assertTrue($parent->relatedArticles->contains($child));
        $this->assertTrue($child->parentArticles->contains($parent));
    }
}
