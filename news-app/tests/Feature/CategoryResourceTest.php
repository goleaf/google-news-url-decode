<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Tables\Actions\DeleteBulkAction;
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
        $this->actingAs(\App\Models\User::factory()->create());
    }

    #[Test]
    public function it_can_render_list_page(): void
    {
        $this->get(CategoryResource::getUrl('index'))
            ->assertSuccessful();
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
    public function it_can_search_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Specific Unique Name']);
        $otherCategory = Category::factory()->create(['name' => 'Other Name']);

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->call('loadTable')
            ->searchTable('Specific Unique Name')
            ->assertCanSeeTableRecords([$category])
            ->assertCanNotSeeTableRecords([$otherCategory]);
    }

    #[Test]
    public function it_can_render_create_page(): void
    {
        $this->get(CategoryResource::getUrl('create'))
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_create_category(): void
    {
        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->set('data.name', 'New Category')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
        ]);
    }

    #[Test]
    public function it_validates_required_name_when_creating_category(): void
    {
        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->set('data.name')
            ->call('create')
            ->assertHasErrors(['data.name' => 'required']);
    }

    #[Test]
    public function it_can_render_edit_page(): void
    {
        $category = Category::factory()->create();

        $this->get(CategoryResource::getUrl('edit', ['record' => $category]))
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_update_category(): void
    {
        $category = Category::factory()->create();
        $newData = Category::factory()->make();

        Livewire::test(CategoryResource\Pages\EditCategory::class, [
            'record' => $category->getRouteKey(),
        ])
            ->set('data.name', $newData->name)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => $newData->name,
        ]);
    }

    #[Test]
    public function it_can_delete_category(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CategoryResource\Pages\EditCategory::class, [
            'record' => $category->getRouteKey(),
        ])
            ->callAction('delete');

        $this->assertModelMissing($category);
    }

    #[Test]
    public function it_can_bulk_delete_categories(): void
    {
        $categories = Category::factory()->count(3)->create();

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->callTableBulkAction(DeleteBulkAction::class, $categories);

        foreach ($categories as $category) {
            $this->assertModelMissing($category);
        }
    }
}
