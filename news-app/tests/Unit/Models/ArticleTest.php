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
    public function it_has_factory()
    {
        $article = Article::factory()->create();
        $this->assertInstanceOf(Article::class, $article);
    }

    #[Test]
    public function it_belongs_to_a_source()
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        $article = Article::factory()->create(['source_id' => $source->id]);

        $this->assertInstanceOf(Source::class, $article->source);
        $this->assertEquals($source->id, $article->source->id);
    }

    #[Test]
    public function it_belongs_to_many_categories()
    {
        $article = Article::factory()->create();
        $categories = Category::factory()->count(3)->create();

        $article->categories()->attach($categories);

        $this->assertCount(3, $article->categories);
    }

    #[Test]
    public function it_can_have_a_parent_and_children()
    {
        $parent = Article::factory()->create();
        $child = Article::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($parent->children->contains($child));
        $this->assertEquals($parent->id, $child->parent->id);
    }
}
