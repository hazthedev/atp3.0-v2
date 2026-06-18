<?php

namespace Tests\Feature\Counters;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\Equipment;
use App\Models\EquipmentCounter;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use App\Models\Item;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Services\Counters\CounterRemainingCalculator;
use App\Services\Counters\FunctionalLocationCounterUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterEngineTest extends TestCase
{
    use RefreshDatabase;

    private function updater(): FunctionalLocationCounterUpdater
    {
        return app(FunctionalLocationCounterUpdater::class);
    }

    private function aircraft(): FunctionalLocation
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);

        return FunctionalLocation::create([
            'registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id,
        ]);
    }

    private function ref(string $code, array $attrs = []): CounterRef
    {
        return CounterRef::create(array_merge([
            'code' => 'CTR-'.$code, 'counter_code' => $code,
        ], $attrs));
    }

    public function test_basic_write_records_value_and_history(): void
    {
        $fl = $this->aircraft();
        $fh = $this->ref('FH');

        $this->updater()->applyRows($fl, [
            ['counter_ref_id' => $fh->id, 'value_dec' => 1240.5],
        ]);

        $counter = FunctionalLocationCounter::first();
        $this->assertSame(1240.5, (float) $counter->value_dec);
        $this->assertTrue($counter->is_used);
        $this->assertSame(1, $counter->lock_version);
        $this->assertDatabaseHas('counter_history', ['counter_ref_id' => $fh->id, 'source_type' => 'manual']);
    }

    public function test_value_is_clamped_to_max(): void
    {
        $fl = $this->aircraft();
        $fh = $this->ref('FH', ['max_value' => 100]);

        $this->updater()->applyRows($fl, [['counter_ref_id' => $fh->id, 'value_dec' => 150]]);

        $this->assertSame(100.0, (float) FunctionalLocationCounter::first()->value_dec);
    }

    public function test_direction_lock_defers_wrong_way_row(): void
    {
        $fl = $this->aircraft();
        $fh = $this->ref('FH', ['allow_incr_decr' => true, 'incr_decr' => 1]); // up only

        $this->updater()->applyRows($fl, [['counter_ref_id' => $fh->id, 'value_dec' => 10]]);
        $result = $this->updater()->applyRows($fl, [['counter_ref_id' => $fh->id, 'delta' => -5]]);

        $this->assertContains($fh->id, $result->directionViolations);
        $this->assertSame(10.0, (float) FunctionalLocationCounter::first()->value_dec); // unchanged
    }

    public function test_penalty_formula_ages_monitoring_counter(): void
    {
        // spec example: rate 4.5 * Δ4 + (static 2 + Δ1) = 21
        $fl = $this->aircraft();
        $fh = $this->ref('FH');
        $lands = $this->ref('LANDINGS');
        $tsn = $this->ref('TSN');

        $penalty = Penalty::create(['code' => 'P1', 'is_active' => true]);
        PenaltyRule::create([
            'penalty_id' => $penalty->id,
            'aircraft_type_id' => $fl->aircraft_type_id,
            'monitoring_counter_ref_id' => $tsn->id,
            'rate_counter_ref_id' => $fh->id,
            'static_counter_ref_id' => $lands->id,
            'rate_value' => 4.5,
            'static_value' => 2,
            'is_active' => true,
        ]);

        $this->updater()->applyRows($fl, [
            ['counter_ref_id' => $fh->id, 'delta' => 4],
            ['counter_ref_id' => $lands->id, 'delta' => 1],
        ]);

        $monitoring = FunctionalLocationCounter::where('counter_ref_id', $tsn->id)->first();
        $this->assertSame(21.0, (float) $monitoring->value_dec);
        $this->assertDatabaseHas('counter_history', ['counter_ref_id' => $tsn->id, 'source_type' => 'penalty_cascade']);
    }

    public function test_penalty_threshold_static_only_on_crossing_edge(): void
    {
        $fl = $this->aircraft();
        $fh = $this->ref('FH');
        $tsn = $this->ref('TSN');
        $penalty = Penalty::create(['code' => 'P2', 'is_active' => true]);
        PenaltyRule::create([
            'penalty_id' => $penalty->id,
            'aircraft_type_id' => $fl->aircraft_type_id,
            'monitoring_counter_ref_id' => $tsn->id,
            'rate_counter_ref_id' => $fh->id,
            'rate_value' => 1, 'static_value' => 5,
            'threshold_value' => 10, 'is_active' => true,
        ]);

        // event 1: FH +12 crosses threshold → rate 12 + static 5 = 17
        $this->updater()->applyRows($fl, [['counter_ref_id' => $fh->id, 'delta' => 12]]);
        $this->assertSame(17.0, (float) FunctionalLocationCounter::where('counter_ref_id', $tsn->id)->first()->value_dec);

        // event 2: FH +3, already past threshold → rate 3 only, no static
        $this->updater()->applyRows($fl, [['counter_ref_id' => $fh->id, 'delta' => 3]]);
        $this->assertSame(20.0, (float) FunctionalLocationCounter::where('counter_ref_id', $tsn->id)->first()->value_dec);
    }

    public function test_propagation_reaches_all_installed_levels(): void
    {
        $fl = $this->aircraft();
        $fh = $this->ref('FH');
        $item = Item::create(['code' => 'PN-1']);

        // L1 under the FL, L2 under L1 (the tree the cascade must walk)
        $l1 = Equipment::create(['functional_location_id' => $fl->id, 'item_id' => $item->id, 'hierarchy_level' => 'L1']);
        $l2 = Equipment::create(['parent_equipment_id' => $l1->id, 'item_id' => $item->id, 'hierarchy_level' => 'L2']);
        EquipmentCounter::create(['equipment_id' => $l1->id, 'counter_ref_id' => $fh->id, 'value_dec' => 0]);
        EquipmentCounter::create(['equipment_id' => $l2->id, 'counter_ref_id' => $fh->id, 'value_dec' => 0]);

        $this->updater()->applyRows($fl, [
            ['counter_ref_id' => $fh->id, 'delta' => 10, 'propagate' => true],
        ]);

        $this->assertSame(10.0, (float) EquipmentCounter::where('equipment_id', $l1->id)->first()->value_dec);
        $this->assertSame(10.0, (float) EquipmentCounter::where('equipment_id', $l2->id)->first()->value_dec);
    }

    public function test_remaining_calculator_is_conservative(): void
    {
        $calc = new CounterRemainingCalculator();
        $this->assertSame(60.0, $calc->remaining(100, 40));               // max - own
        $this->assertSame(50.0, $calc->remaining(100, 40, 50));           // subtracts the larger (parent)
        $this->assertNull($calc->remaining(null, 40));                    // no max
        $this->assertNull($calc->remaining(100, 40, null, 4, false));     // residual opted out
    }
}
