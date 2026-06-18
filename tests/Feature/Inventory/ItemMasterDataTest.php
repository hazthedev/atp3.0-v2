<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_search(): void
    {
        Livewire::test('inventory.item-master-data')
            ->call('new')
            ->set('code', 'PT6C-67C')
            ->set('description', 'Turboshaft engine')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('items', ['code' => 'PT6C-67C']);

        Item::create(['code' => 'BLADE-1', 'description' => 'Main rotor blade']);

        Livewire::test('inventory.item-master-data')
            ->set('search', 'PT6C')
            ->assertSee('PT6C-67C')
            ->assertDontSee('BLADE-1');
    }

    public function test_code_is_unique(): void
    {
        Item::create(['code' => 'PN-1']);

        Livewire::test('inventory.item-master-data')
            ->call('new')
            ->set('code', 'PN-1')
            ->call('save')
            ->assertHasErrors(['code']);
    }
}
