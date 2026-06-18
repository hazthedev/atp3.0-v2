<?php

namespace App\Services\Counters;

class CounterUpdateResult
{
    /** @var array<int,int> counter_ref_ids deferred for a direction violation */
    public array $directionViolations = [];

    /** @var array<int,float> counter_ref_id => applied delta */
    public array $appliedDeltas = [];

    public int $written = 0;
}
