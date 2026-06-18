<?php

namespace App\Events;

use App\Enums\CounterSubject;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by every counter write path (incl. penalty + propagation cascades).
 * After-commit so cross-module projections run only once the write transaction lands
 * and the FOR UPDATE locks release.
 */
class CounterUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public CounterSubject $subjectType,
        public int $subjectId,
        public int $counterRefId,
        public float $delta,
        public string $sourceType,
    ) {}
}
