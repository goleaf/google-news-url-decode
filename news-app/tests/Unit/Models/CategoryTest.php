<?php

namespace Tests\Unit\Models;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_factory()
    {
        $category = Category::factory()->create();
        $this->assertInstanceOf(Category::class, $category);
    }

    #[Test]
    public function it_can_have_a_parent_and_children()
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($parent->children->contains($child));
        $this->assertEquals($parent->id, $child->parent->id);
    }

    #[Test]
    public function it_belongs_to_many_articles()
    {
        $category = Category::factory()->create();
        $articles = Article::factory()->count(2)->create();

        $category->articles()->attach($articles);

        $this->assertCount(2, $category->articles);
    }
}
