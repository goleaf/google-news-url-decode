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
    public function it_has_factory(): void
    {
        $category = Category::factory()->create();
        $this->assertInstanceOf(Category::class, $category);
    }

    #[Test]
    public function it_can_have_parent_and_subcategories(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create();

        $parent->subCategories()->attach($child);

        $this->assertTrue($parent->subCategories->contains($child));
        $this->assertTrue($child->parentCategories->contains($parent));
    }

    #[Test]
    public function it_belongs_to_many_articles(): void
    {
        $category = Category::factory()->create();
        $articles = Article::factory()->count(2)->create();

        $category->articles()->attach($articles);

        $this->assertCount(2, $category->articles);
    }
}
