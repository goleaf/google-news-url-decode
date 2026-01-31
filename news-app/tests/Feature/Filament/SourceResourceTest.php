<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SourceResource;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SourceResourceTest extends TestCase
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
        $this->get(SourceResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function it_can_list_sources(): void
    {
        $sources = Source::factory()->count(5)->create();

        Livewire::test(SourceResource\Pages\ListSources::class)
            ->assertCanSeeTableRecords($sources);
    }

    #[Test]
    public function it_can_create_source(): void
    {
        Livewire::test(SourceResource\Pages\CreateSource::class)
            ->set('data.name', 'New Source')
            ->set('data.domain', 'example.com')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sources', [
            'name' => 'New Source',
        ]);
    }

    #[Test]
    public function it_can_edit_source(): void
    {
        $source = Source::factory()->create();
        $newName = 'Updated Source Name';

        Livewire::test(SourceResource\Pages\EditSource::class, [
            'record' => $source->getRouteKey(),
        ])
            ->set('data.name', $newName)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sources', [
            'id' => $source->id,
            'name' => $newName,
        ]);
    }
}
