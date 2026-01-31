<?php

namespace Tests\Unit\Models;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_factory(): void
    {
        $source = Source::factory()->create();
        $this->assertInstanceOf(Source::class, $source);
    }

    #[Test]
    public function it_has_many_articles(): void
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        $articles = Article::factory()->count(3)->create();

        foreach ($articles as $article) {
            $source->articles()->attach($article);
        }

        $this->assertCount(3, $source->articles);
    }
}
