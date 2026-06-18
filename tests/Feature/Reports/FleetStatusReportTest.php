<?php

namespace Tests\Feature\Reports;

use App\Models\AircraftType;
use App\Models\FunctionalLocation;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetStatusReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_airworthiness_across_fleet(): void
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);
        $clean = FunctionalLocation::create(['registration' => '9M-AAA', 'code' => 'FL-A', 'aircraft_type_id' => $type->id]);
        $bad = FunctionalLocation::create(['registration' => '9M-BBB', 'code' => 'FL-B', 'aircraft_type_id' => $type->id]);
        WorkPackage::create(['code' => 'WP-001', 'functional_location_id' => $bad->id, 'status' => 'Planned']);

        Livewire::test('reports.fleet-status')
            ->assertOk()
            ->assertSee('9M-AAA')
            ->assertSee('9M-BBB')
            ->assertSee('NOT AIRWORTHY')      // 9M-BBB has an open WP
            ->assertSee('REVIEW INCOMPLETE');  // 9M-AAA clean but no AMP/config/pubs
    }
}
