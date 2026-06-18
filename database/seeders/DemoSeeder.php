<?php

namespace Database\Seeders;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use Illuminate\Database\Seeder;

// Idempotent dev/demo data so the Fleet screens have something to show. NOT for prod.
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $type = AircraftType::firstOrCreate(['code' => 'AW139'], ['name' => 'Leonardo AW139']);

        $fl = FunctionalLocation::firstOrCreate(
            ['registration' => '9M-WBD'],
            ['code' => 'FL-9MWBD', 'aircraft_type_id' => $type->id],
        );

        $defs = [
            ['FH', 1240.5, 5000],
            ['FC', 980, null],
            ['TSN', null, null],
        ];

        foreach ($defs as [$code, $value, $max]) {
            $ref = CounterRef::firstOrCreate(['counter_code' => $code], ['code' => 'CTR-'.$code, 'max_value' => $max]);
            FunctionalLocationCounter::firstOrCreate(
                ['functional_location_id' => $fl->id, 'counter_ref_id' => $ref->id],
                ['value_dec' => $value, 'max_dec' => $max, 'remaining' => $max !== null && $value !== null ? $max - $value : null, 'is_used' => $value !== null],
            );
        }
    }
}
