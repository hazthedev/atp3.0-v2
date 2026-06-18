<?php

namespace App\Services\Counters;

use App\Enums\CounterSourceType;
use App\Enums\CounterSubject;
use App\Events\CounterUpdated;
use App\Models\CounterRef;
use App\Models\Equipment;
use App\Models\EquipmentCounter;
use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use App\Models\PenaltyRule;
use Illuminate\Support\Facades\DB;

/**
 * Weststar penalty engine. Ages a MONITORING (output) counter from the per-event
 * deltas of a rule's RATE and STATIC (input) counters:
 *
 *     increment = rate_value * Δrate + (static_value + Δstatic)
 *
 * - is_relative makes the static operand the monitoring counter itself.
 * - threshold_value (ATP extension): rate applies once monitoring >= threshold,
 *   static only on the crossing edge.
 * - Rules are aircraft-type scoped; forward-only; a (rule,target) ages at most once
 *   per cascade (edge-guard); genuine chains recurse to MAX_DEPTH.
 */
class PenaltyEngine
{
    private const MAX_DEPTH = 5;

    public function __construct(private CounterWriteGuard $guard) {}

    /** @param array<int,float> $deltas  counterRefId => delta for this event (the whole batch) */
    public function applyForEvent(
        CounterSubject $subjectType,
        int $subjectId,
        array $deltas,
        array $selectedPenaltyIds = [],
        ?string $sourceRef = null,
    ): void {
        $positive = array_filter($deltas, static fn ($d) => $d > 0);
        if ($positive === []) {
            return;
        }

        DB::transaction(function () use ($subjectType, $subjectId, $positive, $selectedPenaltyIds, $sourceRef) {
            $seen = [];
            $this->fire($subjectType, $subjectId, $positive, $selectedPenaltyIds, $sourceRef, 0, $seen);
        });
    }

    private function fire(
        CounterSubject $subjectType,
        int $subjectId,
        array $deltas,
        array $selectedPenaltyIds,
        ?string $sourceRef,
        int $depth,
        array &$seen,
    ): void {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        $typeId = $this->resolveAircraftTypeId($subjectType, $subjectId);
        if ($typeId === null) {
            return;
        }

        $query = PenaltyRule::query()->where('is_active', true)->where('aircraft_type_id', $typeId);
        if ($selectedPenaltyIds !== []) {
            $query->whereIn('penalty_id', $selectedPenaltyIds);
        }

        foreach ($query->get() as $rule) {
            $this->applyRule($rule, $subjectType, $subjectId, $deltas, $selectedPenaltyIds, $sourceRef, $depth, $seen);
        }
    }

    private function applyRule(
        PenaltyRule $rule,
        CounterSubject $subjectType,
        int $subjectId,
        array $deltas,
        array $selectedPenaltyIds,
        ?string $sourceRef,
        int $depth,
        array &$seen,
    ): void {
        $staticOperandRefId = $rule->is_relative
            ? (int) $rule->monitoring_counter_ref_id
            : ($rule->static_counter_ref_id !== null ? (int) $rule->static_counter_ref_id : null);

        $deltaRate = $rule->rate_counter_ref_id !== null ? ($deltas[(int) $rule->rate_counter_ref_id] ?? 0.0) : 0.0;
        $deltaStatic = $staticOperandRefId !== null ? ($deltas[$staticOperandRefId] ?? 0.0) : 0.0;

        // a rule fires only when one of its input counters moved this event
        if ($deltaRate <= 0 && $deltaStatic <= 0) {
            return;
        }

        $target = $this->resolveTarget($rule, $subjectType, $subjectId);
        if ($target === null) {
            return;   // target item not installed — skip silently (logged in v1)
        }
        [$targetType, $targetId] = $target;

        $seenKey = $rule->id.':'.$targetType->value.':'.$targetId;
        if (isset($seen[$seenKey])) {
            return;   // edge-guard: age a (rule,target) at most once per cascade
        }
        $seen[$seenKey] = true;

        $monRef = CounterRef::find($rule->monitoring_counter_ref_id);
        if ($monRef === null) {
            return;
        }
        $counter = $this->loadMonitoringCounter($targetType, $targetId, (int) $rule->monitoring_counter_ref_id);
        $prevMon = $counter->value_dec !== null ? (float) $counter->value_dec : 0.0;

        $ratePortion = (float) $rule->rate_value * $deltaRate;
        $staticPortion = (float) $rule->static_value + $deltaStatic;

        $increment = $this->computeIncrement($prevMon, $ratePortion, $staticPortion, $rule->threshold_value);
        $newMon = $this->guard->clamp(round($prevMon + $increment, 4), $monRef);
        $applied = round(($newMon ?? $prevMon) - $prevMon, 4);

        if ($applied === 0.0) {
            return;   // clamped to no-op
        }

        $counter->value_dec = $newMon;
        $counter->is_used = true;
        $counter->lock_version = (int) $counter->lock_version + 1;
        $counter->save();

        $this->writeHistory($targetType, $targetId, (int) $rule->monitoring_counter_ref_id, $prevMon, $newMon, $applied, $sourceRef, $rule);

        CounterUpdated::dispatch($targetType, $targetId, (int) $rule->monitoring_counter_ref_id, $applied, CounterSourceType::PenaltyCascade->value);

        // chain: downstream rules whose input is this monitoring counter
        $this->fire($targetType, $targetId, [(int) $rule->monitoring_counter_ref_id => $applied], $selectedPenaltyIds, $sourceRef, $depth + 1, $seen);
    }

    private function computeIncrement(float $prevMon, float $ratePortion, float $staticPortion, ?string $threshold): float
    {
        if ($threshold === null) {
            return round($ratePortion + $staticPortion, 4);
        }
        $threshold = (float) $threshold;
        $tentative = $prevMon + $ratePortion + $staticPortion;
        if ($tentative < $threshold) {
            return 0.0;   // not yet at threshold
        }
        // above threshold: rate every time; static only on the crossing edge
        $increment = $ratePortion + ($prevMon < $threshold ? $staticPortion : 0.0);

        return round($increment, 4);
    }

    /** @return array{0: CounterSubject, 1: int}|null */
    private function resolveTarget(PenaltyRule $rule, CounterSubject $subjectType, int $subjectId): ?array
    {
        if ($rule->target_item_id === null) {
            return [$subjectType, $subjectId];
        }
        $fl = $this->resolveFunctionalLocation($subjectType, $subjectId);
        if ($fl === null) {
            return null;
        }
        $match = $fl->allInstalledEquipment()->firstWhere('item_id', (int) $rule->target_item_id);

        return $match === null ? null : [CounterSubject::Equipment, (int) $match->id];
    }

    private function loadMonitoringCounter(CounterSubject $type, int $id, int $counterRefId): FunctionalLocationCounter|EquipmentCounter
    {
        if ($type === CounterSubject::FunctionalLocation) {
            return FunctionalLocationCounter::query()
                ->where('functional_location_id', $id)->where('counter_ref_id', $counterRefId)
                ->lockForUpdate()->first()
                ?? FunctionalLocationCounter::create(['functional_location_id' => $id, 'counter_ref_id' => $counterRefId]);
        }

        return EquipmentCounter::query()
            ->where('equipment_id', $id)->where('counter_ref_id', $counterRefId)
            ->lockForUpdate()->first()
            ?? EquipmentCounter::create(['equipment_id' => $id, 'counter_ref_id' => $counterRefId]);
    }

    private function resolveAircraftTypeId(CounterSubject $type, int $id): ?int
    {
        $fl = $this->resolveFunctionalLocation($type, $id);

        return $fl?->aircraft_type_id !== null ? (int) $fl->aircraft_type_id : null;
    }

    private function resolveFunctionalLocation(CounterSubject $type, int $id): ?FunctionalLocation
    {
        if ($type === CounterSubject::FunctionalLocation) {
            return FunctionalLocation::find($id);
        }
        // walk up the installed-base tree to the root, then its FL
        $equipment = Equipment::find($id);
        $hops = 0;
        while ($equipment !== null && $hops < 20) {
            if ($equipment->functional_location_id !== null) {
                return FunctionalLocation::find($equipment->functional_location_id);
            }
            if ($equipment->parent_equipment_id === null) {
                break;
            }
            $equipment = Equipment::find($equipment->parent_equipment_id);
            $hops++;
        }

        return null;
    }

    private function writeHistory(CounterSubject $type, int $id, int $refId, float $prev, float $new, float $applied, ?string $sourceRef, PenaltyRule $rule): void
    {
        \App\Models\CounterHistory::create([
            'subject_type' => $type,
            'subject_id' => $id,
            'counter_ref_id' => $refId,
            'prev_value_dec' => $prev,
            'new_value_dec' => $new,
            'delta_dec' => $applied,
            'source_type' => CounterSourceType::PenaltyCascade,
            'source_ref' => $sourceRef ?? ('penalty_rule:'.$rule->id),
            'note' => 'Penalty: '.$rule->penalty?->code,
        ]);
    }
}
