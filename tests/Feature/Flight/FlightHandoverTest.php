<?php

namespace Tests\Feature\Flight;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\Flight;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Services\Flight\FlightCounterHandover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightHandoverTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);
        $fl = FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
        $fh = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);

        return [$fl, $fh];
    }

    private function handover(): FlightCounterHandover
    {
        return app(FlightCounterHandover::class);
    }

    public function test_absolute_reading_sets_the_fl_counter(): void
    {
        [$fl, $fh] = $this->fixture();
        $flight = Flight::create([
            'functional_location_id' => $fl->id, 'scheduled_date' => '2026-06-18',
            'ac_hours_after_minutes' => 600,
        ]);

        $this->handover()->handover($flight);

        $this->assertSame(600.0, (float) FunctionalLocationCounter::where('counter_ref_id', $fh->id)->first()->value_dec);
    }

    public function test_re_handover_of_same_flight_is_idempotent(): void
    {
        [$fl, $fh] = $this->fixture();
        $flight = Flight::create([
            'functional_location_id' => $fl->id, 'scheduled_date' => '2026-06-18', 'ac_hours_after_minutes' => 600,
        ]);

        $this->handover()->handover($flight);
        $this->handover()->handover($flight); // same absolute value again

        // value stays 600 (absolute), not doubled — the point of the absolute model
        $this->assertSame(600.0, (float) FunctionalLocationCounter::where('counter_ref_id', $fh->id)->first()->value_dec);
    }

    public function test_editing_a_flight_does_not_over_count(): void
    {
        // the v1 over-count bug: editing duration added a second delta. Absolute model fixes it.
        [$fl, $fh] = $this->fixture();
        $flight = Flight::create([
            'functional_location_id' => $fl->id, 'scheduled_date' => '2026-06-18', 'ac_hours_after_minutes' => 600,
        ]);
        $this->handover()->handover($flight);

        $flight->update(['ac_hours_after_minutes' => 650]);
        $this->handover()->handover($flight);

        // 650 absolute, NOT 600 + 650 = 1250
        $this->assertSame(650.0, (float) FunctionalLocationCounter::where('counter_ref_id', $fh->id)->first()->value_dec);
    }

    public function test_selected_penalty_fires_on_handover(): void
    {
        [$fl, $fh] = $this->fixture();
        $tsn = CounterRef::create(['code' => 'CTR-TSN', 'counter_code' => 'TSN']);
        $penalty = Penalty::create(['code' => 'P1', 'is_active' => true]);
        PenaltyRule::create([
            'penalty_id' => $penalty->id, 'aircraft_type_id' => $fl->aircraft_type_id,
            'monitoring_counter_ref_id' => $tsn->id, 'rate_counter_ref_id' => $fh->id,
            'rate_value' => 2, 'static_value' => 0, 'is_active' => true,
        ]);
        $flight = Flight::create([
            'functional_location_id' => $fl->id, 'scheduled_date' => '2026-06-18', 'ac_hours_after_minutes' => 10,
        ]);

        $this->handover()->handover($flight, [$penalty->id]);

        // FH 0->10 (delta 10); penalty ages TSN by 2*10 = 20
        $this->assertSame(20.0, (float) FunctionalLocationCounter::where('counter_ref_id', $tsn->id)->first()->value_dec);
    }
}
