<?php

namespace Tests\Feature\Fleet;

use App\Models\AircraftType;
use App\Models\FunctionalLocation;
use App\Models\WorkPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AirworthinessScreenTest extends TestCase
{
    use RefreshDatabase;

    private function aircraft(): FunctionalLocation
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);

        return FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
    }

    public function test_clean_aircraft_shows_review_incomplete(): void
    {
        $this->aircraft();

        Livewire::test('fleet.airworthiness', ['registration' => '9M-WBD'])
            ->assertOk()
            ->assertSee('REVIEW INCOMPLETE')
            ->assertSee('Maintenance Programme');
    }

    public function test_open_work_package_shows_not_airworthy(): void
    {
        $fl = $this->aircraft();
        WorkPackage::create(['code' => 'WP-001', 'functional_location_id' => $fl->id, 'status' => 'Planned']);

        Livewire::test('fleet.airworthiness', ['registration' => '9M-WBD'])
            ->assertSee('NOT AIRWORTHY');
    }
}
