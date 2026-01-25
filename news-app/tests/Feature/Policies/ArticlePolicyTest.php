<?php

namespace Tests\Feature\Policies;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticlePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function a_user_can_view_any_articles()
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('viewAny', Article::class));
    }

    #[Test]
    public function a_user_can_view_an_article()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();
        $this->assertTrue($user->can('view', $article));
    }

    #[Test]
    public function a_user_can_create_an_article()
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('create', Article::class));
    }

    #[Test]
    public function a_user_can_update_an_article()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();
        $this->assertTrue($user->can('update', $article));
    }

    #[Test]
    public function a_user_can_delete_an_article()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create();
        $this->assertTrue($user->can('delete', $article));
    }
}
