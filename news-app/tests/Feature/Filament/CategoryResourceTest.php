<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function it_can_render_list_page(): void
    {
        $this->get(CategoryResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function it_can_list_categories(): void
    {
        $categories = Category::factory()->count(5)->create();

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->call('loadTable')
            ->assertCanSeeTableRecords($categories);
    }

    #[Test]
    public function it_can_create_category(): void
    {
        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->set('data.name', 'New Category')
            ->set('data.rss_url', 'http://example.com/rss')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
        ]);
    }

    #[Test]
    public function it_can_edit_category(): void
    {
        $category = Category::factory()->create();
        $newName = 'Updated Category Name';

        Livewire::test(CategoryResource\Pages\EditCategory::class, [
            'record' => $category->getRouteKey(),
        ])
            ->set('data.name', $newName)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => $newName,
        ]);
    }
}
