<?php

namespace Tests\Feature\Mro;

use App\Models\AircraftType;
use App\Models\FunctionalLocation;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkPackagesScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_work_packages_and_filters_by_status(): void
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);
        $fl = FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
        WorkPackage::create(['code' => 'WP-001', 'functional_location_id' => $fl->id, 'status' => 'Planned']);
        WorkPackage::create(['code' => 'WP-002', 'functional_location_id' => $fl->id, 'status' => 'Completed']);

        Livewire::test('mro.work-packages')
            ->assertOk()
            ->assertSee('WP-001')
            ->assertSee('WP-002')
            ->set('statusFilter', 'Completed')
            ->assertSee('WP-002')
            ->assertDontSee('WP-001');
    }
}
