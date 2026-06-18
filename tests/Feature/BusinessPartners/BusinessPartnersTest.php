<?php

namespace Tests\Feature\BusinessPartners;

use App\Models\BusinessPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessPartnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_with_type_and_unique_code(): void
    {
        Livewire::test('business-partners.index')
            ->call('new')
            ->set('code', 'BP-001')
            ->set('name', 'Weststar Aviation')
            ->set('partnerType', 'Operator')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_partners', ['code' => 'BP-001', 'partner_type' => 'Operator']);

        Livewire::test('business-partners.index')
            ->call('new')
            ->set('code', 'BP-001')
            ->set('name', 'Dup')
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_edit(): void
    {
        $p = BusinessPartner::create(['code' => 'BP-9', 'name' => 'Old', 'partner_type' => 'Customer']);
        Livewire::test('business-partners.index')
            ->call('edit', $p->id)
            ->set('name', 'New Name')
            ->call('save');
        $this->assertDatabaseHas('business_partners', ['id' => $p->id, 'name' => 'New Name']);
    }
}
