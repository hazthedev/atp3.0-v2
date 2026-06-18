<?php

namespace App\Services\Maintenance;

use App\Models\FunctionalLocation;
use App\Models\MaintenanceProgramCompliance;
use App\Services\Counters\CounterRemainingCalculator;

/**
 * Per-aircraft maintenance-due engine. For the FL's Approved + still-assigned
 * programmes, compares each item-counter's next-due against the live FL reading:
 *   - no completion        -> next-due = threshold (first-due)
 *   - completion + interval -> next-due = reading_at_completion + interval (repeat)
 *   - completion + one-time -> satisfied (skipped)
 * remaining < 0 => Overdue; remaining <= alarm => Due Soon; else OK.
 */
class MaintenanceDueService
{
    public function __construct(private CounterRemainingCalculator $remainingCalc) {}

    public function dueItemsForLocation(FunctionalLocation $fl): array
    {
        $programs = $fl->maintenancePrograms()
            ->where('maintenance_programs.status', 'Approved')
            ->wherePivotNull('date_unassigned')
            ->with('items.counters')
            ->get();

        if ($programs->isEmpty()) {
            return ['has_program' => false, 'overdue' => 0, 'due_soon' => 0, 'ok' => 0, 'items' => []];
        }

        $readings = $fl->counters()->whereNotNull('value_dec')->pluck('value_dec', 'counter_ref_id');
        $completions = $this->latestCompletions($fl);

        $items = [];
        $overdue = $dueSoon = $ok = 0;

        foreach ($programs as $program) {
            foreach ($program->items as $item) {
                foreach ($item->counters as $ic) {
                    if ($ic->threshold === null) {
                        continue;
                    }
                    $reading = $readings[$ic->counter_ref_id] ?? null;
                    if ($reading === null) {
                        continue;
                    }

                    $key = $item->id.':'.$ic->counter_ref_id;
                    $completion = $completions[$key] ?? null;

                    if ($completion !== null && $item->apply_one_time) {
                        continue;   // one-time item already done
                    }

                    $threshold = (float) $ic->threshold;
                    $interval = $ic->interval !== null ? (float) $ic->interval : 0.0;
                    $nextDue = ($completion !== null && $interval > 0)
                        ? (float) $completion->reading_at_completion + $interval
                        : $threshold;

                    $remaining = $this->remainingCalc->remaining($nextDue, (float) $reading);
                    $alarm = $ic->alarm !== null ? (float) $ic->alarm : 0.0;

                    $status = match (true) {
                        $remaining < 0 => 'Overdue',
                        $remaining <= $alarm => 'Due Soon',
                        default => 'OK',
                    };
                    $status === 'Overdue' ? $overdue++ : ($status === 'Due Soon' ? $dueSoon++ : $ok++);

                    $items[] = [
                        'item_id' => $item->id,
                        'counter_ref_id' => $ic->counter_ref_id,
                        'next_due' => $nextDue,
                        'remaining' => $remaining,
                        'status' => $status,
                        'source' => ($completion !== null && $interval > 0) ? 'interval' : 'threshold',
                    ];
                }
            }
        }

        return ['has_program' => true, 'overdue' => $overdue, 'due_soon' => $dueSoon, 'ok' => $ok, 'items' => $items];
    }

    /** @return array<string,MaintenanceProgramCompliance> keyed "itemId:counterRefId", newest wins */
    private function latestCompletions(FunctionalLocation $fl): array
    {
        $rows = MaintenanceProgramCompliance::query()
            ->where('functional_location_id', $fl->id)
            ->whereNotNull('reading_at_completion')
            ->whereNotNull('counter_ref_id')
            ->orderBy('completed_date')->orderBy('id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->maintenance_program_item_id.':'.$row->counter_ref_id] = $row;   // last (newest) wins
        }

        return $map;
    }
}
