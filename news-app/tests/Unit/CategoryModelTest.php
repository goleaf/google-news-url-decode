<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_parent_child_relationships()
    {
        $parent = Category::factory()->create(['name' => 'Parent']);
        $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));
    }

    #[Test]
    public function it_has_article_relationships()
    {
        $category = Category::factory()->create();
        $article = Article::factory()->create();

        $category->articles()->attach($article);

        $this->assertTrue($category->articles->contains($article));
        $this->assertTrue($article->categories->contains($category));
    }
}
