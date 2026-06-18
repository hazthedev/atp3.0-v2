<?php

namespace Tests\Feature\Counters;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The concurrency proof v1 left SKIPPED. On MySQL it shows the counter row is genuinely
 * locked FOR UPDATE: a second connection cannot acquire the lock and times out (1205).
 *
 * No RefreshDatabase here on purpose — its per-test transaction would hide the committed
 * row from the second connection. Instead we migrate fresh and commit the fixture, so the
 * two connections genuinely contend for the same row. Runs only on MySQL.
 */
class CounterLostUpdateMysqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concurrency proof runs only on MySQL (the production engine).');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_counter_row_is_locked_for_update(): void
    {
        // committed fixture (autocommit — visible to the second connection)
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);
        $fl = FunctionalLocation::create(['registration' => 'CC-1', 'code' => 'FL-CC', 'aircraft_type_id' => $type->id]);
        $ref = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);
        $counter = FunctionalLocationCounter::create([
            'functional_location_id' => $fl->id, 'counter_ref_id' => $ref->id, 'value_dec' => 100,
        ]);

        // Connection A holds the row lock inside an open transaction.
        DB::connection()->beginTransaction();
        DB::table('functional_location_counters')->where('id', $counter->id)->lockForUpdate()->first();

        // Connection B (separate) must block, then fail with lock-wait-timeout (1205).
        $second = DB::connection('mysql_second');
        $second->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        try {
            $second->beginTransaction();
            $second->table('functional_location_counters')->where('id', $counter->id)->lockForUpdate()->first();
            $second->rollBack();
        } catch (\Illuminate\Database\QueryException $e) {
            $blocked = str_contains((string) $e->getMessage(), '1205') || str_contains(strtolower($e->getMessage()), 'lock wait');
            $second->rollBack();
        }

        DB::connection()->rollBack();

        // clean up the committed fixture
        FunctionalLocationCounter::query()->delete();

        $this->assertTrue($blocked, 'Second connection should be blocked by the FOR UPDATE row lock on MySQL.');
    }
}
