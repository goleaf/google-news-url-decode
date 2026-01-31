<?php

namespace Tests\Feature\Models;

use App\Models\Article;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleDatabaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enforces_unique_original_url(): void
    {
        $url = 'http://example.com/unique';
        Article::factory()->create(['original_url' => $url]);

        $this->expectException(UniqueConstraintViolationException::class);

        Article::factory()->create(['original_url' => $url]);
    }
}
