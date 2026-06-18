<?php

namespace Tests\Feature\Mro;

use App\Models\AircraftType;
use App\Models\CounterRef;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use App\Models\MaintenanceProgram;
use App\Models\MaintenanceProgramItem;
use App\Models\MaintenanceProgramItemCounter;
use App\Models\WorkPackage;
use App\Models\WorkPackageTask;
use App\Services\Maintenance\MaintenanceDueService;
use App\Services\Mro\MaintenanceCompletionRecorder;
use App\Services\Mro\WorkPackageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MroWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function aircraft(): FunctionalLocation
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);

        return FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
    }

    public function test_build_creates_work_package_with_sequential_codes(): void
    {
        $fl = $this->aircraft();
        $program = MaintenanceProgram::create(['code' => 'AMP-1', 'title' => 'AMP', 'status' => 'Approved']);
        $i1 = MaintenanceProgramItem::create(['maintenance_program_id' => $program->id, 'item_type' => 'Task', 'code' => 'T1']);
        $i2 = MaintenanceProgramItem::create(['maintenance_program_id' => $program->id, 'item_type' => 'Task', 'code' => 'T2']);
        $builder = app(WorkPackageBuilder::class);

        $wp1 = $builder->build($fl, [['item_id' => $i1->id, 'counter_ref_id' => null, 'status' => 'Overdue']]);
        $wp2 = $builder->build($fl, [['item_id' => $i2->id, 'counter_ref_id' => null, 'status' => 'OK']]);

        $this->assertSame('WP-001', $wp1->code);
        $this->assertSame('WP-002', $wp2->code);
        $this->assertSame(1, WorkPackageTask::where('work_package_id', $wp1->id)->count());
        $this->assertSame('Overdue', WorkPackageTask::where('work_package_id', $wp1->id)->first()->status);
    }

    public function test_build_rejects_empty_selection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(WorkPackageBuilder::class)->build($this->aircraft(), []);
    }

    public function test_record_completion_closes_the_due_loop(): void
    {
        $fl = $this->aircraft();
        $fh = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);
        FunctionalLocationCounter::create(['functional_location_id' => $fl->id, 'counter_ref_id' => $fh->id, 'value_dec' => 1200]);

        $program = MaintenanceProgram::create(['code' => 'AMP-1', 'title' => 'AMP', 'status' => 'Approved']);
        $fl->maintenancePrograms()->attach($program->id, ['date_assigned' => '2026-01-01', 'approval_status' => 'Approved']);
        $item = MaintenanceProgramItem::create(['maintenance_program_id' => $program->id, 'item_type' => 'Task', 'code' => 'T1']);
        MaintenanceProgramItemCounter::create([
            'maintenance_program_item_id' => $item->id, 'counter_ref_id' => $fh->id,
            'threshold' => 1000, 'interval' => 500,
        ]);

        $due = app(MaintenanceDueService::class);
        $before = $due->dueItemsForLocation($fl);
        $this->assertSame(1, $before['overdue']);   // 1200 > threshold 1000

        // build a WP from the overdue items, then record completion
        $wp = app(WorkPackageBuilder::class)->build($fl, $before['items']);
        $recorded = app(MaintenanceCompletionRecorder::class)->recordForWorkPackage($wp);
        $this->assertSame(1, $recorded);

        // next-due now = reading_at_completion(1200) + interval(500) = 1700 -> remaining 500 -> OK
        $after = $due->dueItemsForLocation($fl);
        $this->assertSame(0, $after['overdue']);
        $this->assertSame('interval', $after['items'][0]['source']);
    }
}
