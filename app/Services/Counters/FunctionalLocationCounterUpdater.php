<?php

namespace App\Services\Counters;

use App\Enums\CounterSourceType;
use App\Enums\CounterSubject;
use App\Events\CounterUpdated;
use App\Models\CounterHistory;
use App\Models\CounterRef;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use Illuminate\Support\Facades\DB;

/**
 * THE single source of truth for FL counter-row saves. New writers must go through
 * this — never re-implement the lock -> fill -> save -> history -> cascade chain.
 *
 * Concurrency: the whole batch runs in one transaction (3 deadlock retries); each row
 * is loaded FOR UPDATE and bumps lock_version (optimistic line, fork C5). Penalties fire
 * once for the accumulated batch delta map; propagation runs per row.
 */
class FunctionalLocationCounterUpdater
{
    public function __construct(
        private CounterWriteGuard $guard,
        private CounterPropagationEngine $propagation,
        private PenaltyEngine $penalty,
    ) {}

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array{selected_penalty_ids?:array<int>,source_ref?:string,user_id?:int}  $opts
     */
    public function applyRows(FunctionalLocation $fl, array $rows, array $opts = []): CounterUpdateResult
    {
        $result = new CounterUpdateResult();

        DB::transaction(function () use ($fl, $rows, $opts, $result) {
            $deltas = [];

            foreach ($rows as $row) {
                $refId = (int) ($row['counter_ref_id'] ?? 0);
                $ref = $refId ? CounterRef::find($refId) : null;
                if ($ref === null) {
                    continue;
                }

                $counter = FunctionalLocationCounter::query()
                    ->where('functional_location_id', $fl->id)->where('counter_ref_id', $refId)
                    ->lockForUpdate()->first()
                    ?? FunctionalLocationCounter::create(['functional_location_id' => $fl->id, 'counter_ref_id' => $refId]);

                $prev = $counter->value_dec !== null ? (float) $counter->value_dec : null;

                // reset-to-null: clear, bypass guard/clamp/cascade, still audit
                if (! empty($row['reset_to_null'])) {
                    $counter->value_dec = null;
                    $counter->value_hhmm = null;
                    $counter->is_used = false;
                    $counter->lock_version++;
                    $counter->save();
                    $this->history($fl->id, $refId, $prev, null, null, $opts, 'Reset to Null');
                    continue;
                }

                // resolve the new value from an absolute reading or a delta
                if (array_key_exists('value_dec', $row) && $row['value_dec'] !== null) {
                    $new = (float) $row['value_dec'];
                } elseif (array_key_exists('delta', $row) && $row['delta'] !== null) {
                    $new = ($prev ?? 0.0) + (float) $row['delta'];
                } else {
                    continue;   // nothing to write this row
                }

                // direction guard — defer the row unless explicitly overridden
                if ($this->guard->violatesDirection($ref, $prev, $new) && empty($row['allow_direction_override'])) {
                    $result->directionViolations[] = $refId;
                    continue;
                }

                $new = $this->guard->clamp($new, $ref);
                $delta = round(($new ?? 0.0) - ($prev ?? 0.0), 4);

                $counter->value_dec = $new;
                $counter->is_used = $new !== null;
                $counter->reading_date = $row['reading_date'] ?? $counter->reading_date;
                $counter->reading_hour = $row['reading_hour'] ?? $counter->reading_hour;
                $counter->info_source = $row['info_source'] ?? $counter->info_source;
                if (array_key_exists('propagate', $row)) {
                    $counter->propagate = (bool) $row['propagate'];
                }
                $counter->lock_version++;
                $counter->save();
                $result->written++;

                $this->history($fl->id, $refId, $prev, $new, $delta, $opts, null);
                CounterUpdated::dispatch(CounterSubject::FunctionalLocation, (int) $fl->id, $refId, $delta, CounterSourceType::Manual->value);

                if ($delta !== 0.0) {
                    $deltas[$refId] = $delta;

                    if ($counter->propagate) {
                        $this->propagation->propagateFromFl($fl, $refId, $delta, (int) $counter->id, [
                            'reading_date' => $counter->reading_date,
                            'reading_hour' => $counter->reading_hour,
                            'info_source' => $counter->info_source,
                        ]);
                    }
                }
            }

            $result->appliedDeltas = $deltas;

            // penalties fire once for the whole batch delta map
            if ($deltas !== []) {
                $this->penalty->applyForEvent(
                    CounterSubject::FunctionalLocation,
                    (int) $fl->id,
                    $deltas,
                    $opts['selected_penalty_ids'] ?? [],
                    $opts['source_ref'] ?? null,
                );
            }
        }, 3);

        return $result;
    }

    private function history(int $flId, int $refId, ?float $prev, ?float $new, ?float $delta, array $opts, ?string $note): void
    {
        CounterHistory::create([
            'subject_type' => CounterSubject::FunctionalLocation,
            'subject_id' => $flId,
            'counter_ref_id' => $refId,
            'prev_value_dec' => $prev,
            'new_value_dec' => $new,
            'delta_dec' => $delta,
            'source_type' => CounterSourceType::Manual,
            'source_ref' => $opts['source_ref'] ?? null,
            'user_id' => $opts['user_id'] ?? null,
            'note' => $note,
        ]);
    }
}
