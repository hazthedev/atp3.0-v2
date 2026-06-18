<?php

namespace Tests\Feature\Admin;

use App\Models\AircraftType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AircraftTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_validates_and_persists(): void
    {
        Livewire::test('admin.aircraft-types')
            ->call('new')
            ->set('code', '')
            ->call('save')
            ->assertHasErrors(['code' => 'required'])
            ->set('code', 'AW139')
            ->set('name', 'Leonardo AW139')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('aircraft_types', ['code' => 'AW139', 'name' => 'Leonardo AW139']);
    }

    public function test_edit_and_delete(): void
    {
        $type = AircraftType::create(['code' => 'B412', 'name' => 'Bell 412']);

        Livewire::test('admin.aircraft-types')
            ->call('edit', $type->id)
            ->assertSet('code', 'B412')
            ->set('name', 'Bell 412EPI')
            ->call('save');
        $this->assertDatabaseHas('aircraft_types', ['id' => $type->id, 'name' => 'Bell 412EPI']);

        Livewire::test('admin.aircraft-types')
            ->call('delete', $type->id);
        $this->assertDatabaseMissing('aircraft_types', ['id' => $type->id]);
    }

    public function test_code_is_unique(): void
    {
        AircraftType::create(['code' => 'AW139', 'name' => 'X']);

        Livewire::test('admin.aircraft-types')
            ->call('new')
            ->set('code', 'AW139')
            ->set('name', 'Dup')
            ->call('save')
            ->assertHasErrors(['code']);
    }
}
