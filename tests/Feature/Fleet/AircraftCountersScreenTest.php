<?php

namespace Tests\Feature\Fleet;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AircraftCountersScreenTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(): array
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);
        $fl = FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
        $fh = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);
        FunctionalLocationCounter::create(['functional_location_id' => $fl->id, 'counter_ref_id' => $fh->id, 'value_dec' => 100]);

        return [$fl, $fh];
    }

    public function test_screen_lists_counters(): void
    {
        $this->makeFixture();

        Livewire::test('fleet.aircraft-counters', ['registration' => '9M-WBD'])
            ->assertOk()
            ->assertSee('FH')
            ->assertSee('Update Counter');
    }

    public function test_update_counter_drives_the_engine(): void
    {
        [$fl, $fh] = $this->makeFixture();

        Livewire::test('fleet.aircraft-counters', ['registration' => '9M-WBD'])
            ->call('enterEdit')
            ->set("inputs.{$fh->id}", '150')
            ->call('save')
            ->assertSet('editing', false);

        $this->assertSame(150.0, (float) FunctionalLocationCounter::first()->value_dec);
        $this->assertDatabaseHas('counter_history', ['counter_ref_id' => $fh->id, 'source_type' => 'manual']);
    }
}
