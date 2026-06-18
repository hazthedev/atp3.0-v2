<?php

namespace Tests\Feature\TechnicalData;

use App\Models\MaintenanceProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceProgramsScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_programmes(): void
    {
        MaintenanceProgram::create(['code' => 'AMP-AW139', 'title' => 'AW139 Programme', 'status' => 'Approved']);
        MaintenanceProgram::create(['code' => 'AMP-DRAFT', 'title' => 'Draft Programme', 'status' => 'Draft']);

        Livewire::test('technical-data.maintenance-programs')
            ->assertOk()
            ->assertSee('AMP-AW139')
            ->assertSee('AW139 Programme')
            ->assertSee('Approved')
            ->assertSee('AMP-DRAFT');
    }
}
