<?php

namespace App\Services\Flight;

use App\Models\CounterRef;
use App\Models\Flight;
use App\Services\Counters\CounterUpdateResult;
use App\Services\Counters\FunctionalLocationCounterUpdater;

/**
 * Hands a flight's ABSOLUTE after-flight readings to the counter write path
 * (fork F2). Because readings are absolute, re-handing the same flight is a no-op
 * (delta 0) and editing a flight reverse-and-replaces naturally — no over-count,
 * unlike v1's additive-delta model. Aircraft is a real FK (no registration matching).
 */
class FlightCounterHandover
{
    public function __construct(private FunctionalLocationCounterUpdater $updater) {}

    /** @param array<int> $selectedPenaltyIds */
    public function handover(Flight $flight, array $selectedPenaltyIds = []): CounterUpdateResult
    {
        $fl = $flight->functionalLocation;
        $rows = [];

        if ($flight->ac_hours_after_minutes !== null) {
            if ($ref = $this->refFor(config('counters.flight.hours_counter_code'))) {
                $rows[] = [
                    'counter_ref_id' => $ref->id,
                    'value_dec' => (float) $flight->ac_hours_after_minutes,
                    'reading_date' => $flight->scheduled_date,
                    'propagate' => true,
                ];
            }
        }

        if ($flight->ac_cycle_after !== null) {
            if ($ref = $this->refFor(config('counters.flight.cycles_counter_code'))) {
                $rows[] = [
                    'counter_ref_id' => $ref->id,
                    'value_dec' => (float) $flight->ac_cycle_after,
                    'reading_date' => $flight->scheduled_date,
                    'propagate' => true,
                ];
            }
        }

        if ($fl === null || $rows === []) {
            return new CounterUpdateResult();   // unresolved aircraft / nothing to write
        }

        return $this->updater->applyRows($fl, $rows, [
            'selected_penalty_ids' => $selectedPenaltyIds,
            'source_ref' => 'flight:'.$flight->id,
        ]);
    }

    private function refFor(?string $counterCode): ?CounterRef
    {
        return $counterCode ? CounterRef::where('counter_code', $counterCode)->first() : null;
    }
}
