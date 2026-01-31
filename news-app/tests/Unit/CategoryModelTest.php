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
    public function it_has_parent_child_relationships(): void
    {
        $parent = Category::factory()->create(['name' => 'Parent']);
        $child = Category::factory()->create(['name' => 'Child']);

        $parent->subCategories()->attach($child);

        $this->assertTrue($parent->subCategories->contains($child));
        $this->assertTrue($child->parentCategories->contains($parent));
    }

    #[Test]
    public function it_has_article_relationships(): void
    {
        $category = Category::factory()->create();
        $article = Article::factory()->create();

        $category->articles()->attach($article);

        $this->assertTrue($category->articles->contains($article));
        $this->assertTrue($article->categories->contains($category));
    }
}
