<?php

namespace App\Services\Counters;

/**
 * Conservative linked-measure remaining. With both own and parent present it
 * subtracts the larger (most-aged) reading; with no parent it reduces to max - own
 * (byte-identical to legacy, so unlinked counters are unaffected).
 */
class CounterRemainingCalculator
{
    public function remaining(
        ?float $max,
        ?float $own,
        ?float $parent = null,
        int $precision = 4,
        bool $usedForResidualCalc = true,
    ): ?float {
        if ($max === null || ! $usedForResidualCalc) {
            return null;
        }

        $effective = match (true) {
            $own !== null && $parent !== null => max($own, $parent),
            $own !== null => $own,
            $parent !== null => $parent,
            default => null,
        };

        if ($effective === null) {
            return round($max, $precision);
        }

        return round($max - $effective, $precision);
    }
}
