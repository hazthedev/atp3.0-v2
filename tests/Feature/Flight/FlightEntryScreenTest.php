<?php

namespace Tests\Feature\Flight;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\Flight;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlightEntryScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_flight_drives_the_counter_pipeline(): void
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);
        $fl = FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
        $fh = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);

        Livewire::test('flight.flight-entry')
            ->set('flId', $fl->id)
            ->set('scheduledDate', '2026-06-18')
            ->set('hoursAfter', '600')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flights', ['functional_location_id' => $fl->id, 'ac_hours_after_minutes' => 600]);
        // absolute reading handed to the engine -> FL FH counter set to 600
        $this->assertSame(600.0, (float) FunctionalLocationCounter::where('counter_ref_id', $fh->id)->first()->value_dec);
    }

    public function test_validation_requires_aircraft_and_date(): void
    {
        Livewire::test('flight.flight-entry')
            ->call('save')
            ->assertHasErrors(['flId', 'scheduledDate']);
    }
}
