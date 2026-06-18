<?php

namespace App\Services\Counters;

use App\Enums\CounterSourceType;
use App\Enums\CounterSubject;
use App\Events\CounterUpdated;
use App\Models\CounterRef;
use App\Models\EquipmentCounter;
use App\Models\FunctionalLocation;

/**
 * Replays a delta recorded on the aircraft onto every installed Component carrying the
 * same counter (matched by counter_ref_id only). Because allInstalledEquipment() covers
 * the whole tree, this naturally reaches L1 -> L2 -> L3. Missing-counter components are
 * skipped silently. Runs inside the caller's transaction.
 */
class CounterPropagationEngine
{
    public function __construct(private CounterWriteGuard $guard) {}

    /** @param array{reading_date?:mixed,reading_hour?:mixed,info_source?:mixed} $meta */
    public function propagateFromFl(FunctionalLocation $fl, int $counterRefId, float $delta, int $flCounterId, array $meta = []): void
    {
        if ($delta === 0.0) {
            return;
        }
        $ref = CounterRef::find($counterRefId);
        if ($ref === null || $ref->propagation_flag === false) {
            return;   // definition-level opt-out
        }

        foreach ($fl->allInstalledEquipment() as $equipment) {
            $counter = EquipmentCounter::query()
                ->where('equipment_id', $equipment->id)
                ->where('counter_ref_id', $counterRefId)
                ->lockForUpdate()->first();

            if ($counter === null) {
                continue;   // component doesn't carry this counter — skip silently
            }

            $prev = $counter->value_dec !== null ? (float) $counter->value_dec : 0.0;
            $new = $this->guard->clamp(round($prev + $delta, 4), $ref);
            $applied = round(($new ?? $prev) - $prev, 4);
            if ($applied === 0.0) {
                continue;
            }

            $counter->value_dec = $new;
            $counter->is_used = true;
            $counter->reading_date = $meta['reading_date'] ?? $counter->reading_date;
            $counter->reading_hour = $meta['reading_hour'] ?? $counter->reading_hour;
            $counter->info_source = $meta['info_source'] ?? $counter->info_source;
            $counter->lock_version = (int) $counter->lock_version + 1;
            $counter->save();

            \App\Models\CounterHistory::create([
                'subject_type' => CounterSubject::Equipment,
                'subject_id' => $equipment->id,
                'counter_ref_id' => $counterRefId,
                'prev_value_dec' => $prev,
                'new_value_dec' => $new,
                'delta_dec' => $applied,
                'reading_date' => $meta['reading_date'] ?? null,
                'reading_hour' => $meta['reading_hour'] ?? null,
                'info_source' => $meta['info_source'] ?? null,
                'source_type' => CounterSourceType::Propagated,
                'source_ref' => 'fl_counter:'.$flCounterId,
            ]);

            CounterUpdated::dispatch(CounterSubject::Equipment, (int) $equipment->id, $counterRefId, $applied, CounterSourceType::Propagated->value);
        }
    }
}
