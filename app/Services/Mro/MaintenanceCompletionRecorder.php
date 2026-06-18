<?php

namespace App\Services\Mro;

use App\Models\MaintenanceProgramCompliance;
use App\Models\WorkPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records completion of a Work Package's tasks: snapshots the live FL reading per
 * (item, counter) and writes MaintenanceProgramCompliance, which the due engine then
 * consumes to roll next-due forward. updateOrCreate is race-safe via the UNIQUE
 * (fl,item,counter) constraint (M4/H9) and the whole loop runs in one transaction (H11).
 */
class MaintenanceCompletionRecorder
{
    public function recordForWorkPackage(WorkPackage $wp): int
    {
        $fl = $wp->functional_location_id;
        if ($fl === null) {
            return 0;
        }

        return DB::transaction(function () use ($wp, $fl) {
            $readings = \App\Models\FunctionalLocationCounter::query()
                ->where('functional_location_id', $fl)
                ->whereNotNull('value_dec')
                ->pluck('value_dec', 'counter_ref_id');

            $recorded = 0;
            $today = Carbon::now()->toDateString();

            foreach ($wp->tasks()->whereNotNull('maintenance_program_item_id')->get() as $task) {
                $reading = $task->counter_ref_id !== null ? ($readings[$task->counter_ref_id] ?? null) : null;

                MaintenanceProgramCompliance::updateOrCreate(
                    [
                        'functional_location_id' => $fl,
                        'maintenance_program_item_id' => $task->maintenance_program_item_id,
                        'counter_ref_id' => $task->counter_ref_id,
                    ],
                    [
                        'reading_at_completion' => $reading,
                        'completed_date' => $today,
                        'work_reference' => $wp->code,
                    ],
                );
                $recorded++;
            }

            return $recorded;
        });
    }
}
