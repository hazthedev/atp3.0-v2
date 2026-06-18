<?php

namespace Tests\Feature\Airworthiness;

use App\Models\AircraftType;
use App\Models\ApplicableConfiguration;
use App\Models\ApplicableConfigurationItem;
use App\Models\ConfigurationVariant;
use App\Models\CounterRef;
use App\Models\Equipment;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use App\Models\Item;
use App\Models\MaintenanceProgram;
use App\Models\MaintenanceProgramItem;
use App\Models\MaintenanceProgramItemCounter;
use App\Models\PublicationCompliance;
use App\Models\PublicationType;
use App\Models\TechnicalPublication;
use App\Models\WorkPackage;
use App\Services\Airworthiness\AirworthinessReviewService;
use App\Services\Maintenance\MaintenanceDueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirworthinessTest extends TestCase
{
    use RefreshDatabase;

    private function aircraft(): FunctionalLocation
    {
        $type = AircraftType::create(['code' => 'AW139', 'name' => 'AW139']);

        return FunctionalLocation::create(['registration' => '9M-WBD', 'code' => 'FL-1', 'aircraft_type_id' => $type->id]);
    }

    private function review(): AirworthinessReviewService
    {
        return app(AirworthinessReviewService::class);
    }

    public function test_clean_aircraft_with_no_data_is_review_incomplete(): void
    {
        $fl = $this->aircraft();
        $out = $this->review()->getReview($fl->registration);

        // WP + Defects pass (count 0); AMP/Pubs/Config have no data -> NOT EVALUATED
        $this->assertSame(AirworthinessReviewService::REVIEW_INCOMPLETE, $out['verdict']);
    }

    public function test_open_work_package_makes_not_airworthy(): void
    {
        $fl = $this->aircraft();
        WorkPackage::create(['code' => 'WP-001', 'functional_location_id' => $fl->id, 'status' => 'Planned']);

        $out = $this->review()->getReview($fl->registration);
        $this->assertSame(AirworthinessReviewService::NOT_AIRWORTHY, $out['verdict']);
        $this->assertSame(AirworthinessReviewService::FAIL, $out['criteria']['work_packages']['result']);
    }

    public function test_maintenance_due_flags_overdue_when_reading_exceeds_threshold(): void
    {
        $fl = $this->aircraft();
        $fh = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);
        FunctionalLocationCounter::create(['functional_location_id' => $fl->id, 'counter_ref_id' => $fh->id, 'value_dec' => 1200]);

        $program = MaintenanceProgram::create(['code' => 'AMP-1', 'title' => 'AMP', 'status' => 'Approved']);
        $fl->maintenancePrograms()->attach($program->id, ['date_assigned' => '2026-01-01', 'approval_status' => 'Approved']);
        $item = MaintenanceProgramItem::create(['maintenance_program_id' => $program->id, 'item_type' => 'Task', 'code' => 'T1']);
        MaintenanceProgramItemCounter::create(['maintenance_program_item_id' => $item->id, 'counter_ref_id' => $fh->id, 'threshold' => 1000]);

        $due = app(MaintenanceDueService::class)->dueItemsForLocation($fl);
        $this->assertTrue($due['has_program']);
        $this->assertSame(1, $due['overdue']); // reading 1200 > threshold 1000
    }

    public function test_fully_compliant_aircraft_is_airworthy(): void
    {
        $fl = $this->aircraft();
        $fh = CounterRef::create(['code' => 'CTR-FH', 'counter_code' => 'FH']);
        FunctionalLocationCounter::create(['functional_location_id' => $fl->id, 'counter_ref_id' => $fh->id, 'value_dec' => 500]);

        // AMP: threshold 1000 > reading 500 -> OK (not overdue)
        $program = MaintenanceProgram::create(['code' => 'AMP-1', 'title' => 'AMP', 'status' => 'Approved']);
        $fl->maintenancePrograms()->attach($program->id, ['date_assigned' => '2026-01-01', 'approval_status' => 'Approved']);
        $item = MaintenanceProgramItem::create(['maintenance_program_id' => $program->id, 'item_type' => 'Task', 'code' => 'T1']);
        MaintenanceProgramItemCounter::create(['maintenance_program_item_id' => $item->id, 'counter_ref_id' => $fh->id, 'threshold' => 1000]);

        // Tech Pubs: one applicable AD, embodied -> satisfied
        $adType = PublicationType::create(['code' => 'AD', 'label' => 'Airworthiness Directive']);
        $pub = TechnicalPublication::create(['reference' => 'AD-1', 'publication_type_id' => $adType->id, 'status' => 'Applicable', 'applicable_aircraft_type_id' => $fl->aircraft_type_id]);
        PublicationCompliance::create(['functional_location_id' => $fl->id, 'technical_publication_id' => $pub->id, 'compliance_status' => 'Embodied']);

        // Config: expect PN-1 x1, install one PN-1 -> in sync
        $cfg = ApplicableConfiguration::create(['code' => 'CFG-1', 'name' => 'Base']);
        ApplicableConfigurationItem::create(['applicable_configuration_id' => $cfg->id, 'item_name' => 'Engine', 'allowable_part_number' => 'PN-1', 'expected_quantity' => 1]);
        $variant = ConfigurationVariant::create(['applicable_configuration_id' => $cfg->id, 'code' => 'VAR-1']);
        $fl->configurationVariants()->attach($variant->id);
        $item1 = Item::create(['code' => 'PN-1']);
        Equipment::create(['functional_location_id' => $fl->id, 'item_id' => $item1->id, 'hierarchy_level' => 'L1']);

        $out = $this->review()->getReview($fl->registration);

        $this->assertSame(AirworthinessReviewService::AIRWORTHY, $out['verdict'], 'criteria: '.json_encode(array_map(fn ($c) => $c['result'], $out['criteria'])));
    }
}
