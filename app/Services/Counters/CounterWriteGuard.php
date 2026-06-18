<?php

namespace App\Services\Counters;

use App\Models\CounterRef;

/**
 * The shared min/max clamp + direction lock. All counter writers use this so the
 * guards are enforced identically (v1 had them only on the UI path).
 */
class CounterWriteGuard
{
    public function clamp(?float $value, CounterRef $ref): ?float
    {
        if ($value === null) {
            return null;
        }
        $min = $ref->min_value !== null ? (float) $ref->min_value : null;
        $max = $ref->max_value !== null ? (float) $ref->max_value : null;

        if ($min !== null && $value < $min) {
            return $min;
        }
        if ($max !== null && $value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * True when the counter is direction-locked and the move goes the wrong way.
     * allow_incr_decr = enable toggle; incr_decr = locked direction (1=up, 2=down).
     */
    public function violatesDirection(CounterRef $ref, ?float $prev, ?float $new): bool
    {
        if (! $ref->allow_incr_decr) {
            return false;
        }
        if ($prev === null || $new === null || $new === $prev) {
            return false;
        }
        $movesUp = $new > $prev;

        return ($ref->incr_decr === 1 && ! $movesUp)
            || ($ref->incr_decr === 2 && $movesUp);
    }
}
