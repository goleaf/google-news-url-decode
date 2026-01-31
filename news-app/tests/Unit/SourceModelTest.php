<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SourceModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_article_relationships(): void
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        $article = Article::factory()->create([
            'source_name' => 'Test Source',
        ]);

        $source->articles()->attach($article);

        $this->assertTrue($source->fresh()->articles->contains($article));
        $this->assertTrue($article->fresh()->sources->contains($source));
    }
}
