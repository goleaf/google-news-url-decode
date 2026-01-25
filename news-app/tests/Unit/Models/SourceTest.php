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
    public function it_has_factory()
    {
        $source = Source::factory()->create();
        $this->assertInstanceOf(Source::class, $source);
    }

    #[Test]
    public function it_has_many_articles()
    {
        $source = Source::factory()->create(['name' => 'Test Source']);
        Article::factory()->count(3)->create(['source_id' => $source->id]);

        $this->assertCount(3, $source->articles);
    }
}
