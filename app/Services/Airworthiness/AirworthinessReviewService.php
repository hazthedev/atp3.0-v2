<?php

namespace App\Services\Airworthiness;

use App\Models\Defect;
use App\Models\FunctionalLocation;
use App\Models\PublicationCompliance;
use App\Models\TechnicalPublication;
use App\Models\WorkPackage;
use App\Services\Configuration\ConfigurationComparisonService;
use App\Services\Maintenance\MaintenanceDueService;
use Carbon\CarbonImmutable;

/**
 * Aggregates five criteria as-of a date into an overall verdict. Conservative and
 * "never fake-green": a criterion with no data to judge returns NOT EVALUATED rather
 * than PASS, and overall is AIRWORTHY only when all five are evaluated and pass.
 */
class AirworthinessReviewService
{
    public const PASS = 'PASS';
    public const FAIL = 'FAIL';
    public const NOT_EVALUATED = 'NOT EVALUATED';

    public const AIRWORTHY = 'AIRWORTHY';
    public const NOT_AIRWORTHY = 'NOT AIRWORTHY';
    public const REVIEW_INCOMPLETE = 'REVIEW INCOMPLETE';

    public function __construct(
        private MaintenanceDueService $due,
        private ConfigurationComparisonService $config,
    ) {}

    public function getReview(string $registration, ?string $asOf = null): array
    {
        $fl = FunctionalLocation::query()
            ->where('registration', $registration)->orWhere('code', $registration)->first();
        $asOfDate = $this->parseAsOf($asOf);

        $criteria = [
            'work_packages' => $this->workPackagesCriterion($fl),
            'amp' => $this->ampCriterion($fl),
            'technical_publications' => $this->technicalPublicationsCriterion($fl),
            'defects' => $this->defectsCriterion($fl, $asOfDate),
            'configuration' => $this->configurationCriterion($fl),
        ];

        $results = array_column($criteria, 'result');
        $verdict = match (true) {
            in_array(self::FAIL, $results, true) => self::NOT_AIRWORTHY,
            in_array(self::NOT_EVALUATED, $results, true) => self::REVIEW_INCOMPLETE,
            default => self::AIRWORTHY,
        };

        $reasons = [];
        foreach ($criteria as $key => $c) {
            if ($c['result'] === self::FAIL) {
                $reasons[] = $c['reason'] ?? $key;
            }
        }

        return ['verdict' => $verdict, 'criteria' => $criteria, 'reasons' => $reasons];
    }

    private function workPackagesCriterion(?FunctionalLocation $fl): array
    {
        if ($fl === null) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No aircraft record'];
        }
        $open = WorkPackage::query()
            ->where('functional_location_id', $fl->id)
            ->whereNotIn('status', WorkPackage::CLOSED_STATUSES)
            ->count();

        return $open === 0
            ? ['result' => self::PASS, 'outstanding' => 0]
            : ['result' => self::FAIL, 'outstanding' => $open, 'reason' => "$open open work package(s)"];
    }

    private function ampCriterion(?FunctionalLocation $fl): array
    {
        if ($fl === null) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No aircraft record'];
        }
        $due = $this->due->dueItemsForLocation($fl);
        if (! $due['has_program']) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No Approved maintenance programme assigned'];
        }

        return $due['overdue'] === 0
            ? ['result' => self::PASS, 'outstanding' => 0]
            : ['result' => self::FAIL, 'outstanding' => $due['overdue'], 'reason' => "{$due['overdue']} overdue maintenance item(s)"];
    }

    private function technicalPublicationsCriterion(?FunctionalLocation $fl): array
    {
        if ($fl === null) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No aircraft record'];
        }
        $applicable = TechnicalPublication::query()
            ->whereHas('publicationType', fn ($q) => $q->where('code', 'AD'))
            ->where('status', 'Applicable')
            ->where(fn ($q) => $q->whereNull('applicable_aircraft_type_id')
                ->orWhere('applicable_aircraft_type_id', $fl->aircraft_type_id))
            ->pluck('id');

        if ($applicable->isEmpty()) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No applicable mandatory publications on record'];
        }

        $satisfied = PublicationCompliance::query()
            ->where('functional_location_id', $fl->id)
            ->whereIn('technical_publication_id', $applicable)
            ->whereIn('compliance_status', PublicationCompliance::SATISFIED_STATUSES)
            ->pluck('technical_publication_id');

        $outstanding = $applicable->diff($satisfied)->count();

        return $outstanding === 0
            ? ['result' => self::PASS, 'outstanding' => 0]
            : ['result' => self::FAIL, 'outstanding' => $outstanding, 'reason' => "$outstanding outstanding AD(s)"];
    }

    private function defectsCriterion(?FunctionalLocation $fl, CarbonImmutable $asOf): array
    {
        if ($fl === null) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No aircraft record'];
        }
        $open = Defect::query()->where('functional_location_id', $fl->id)->where('defect_status', 'Open')->count();
        $overdueDeferred = Defect::query()
            ->where('functional_location_id', $fl->id)
            ->where('defect_status', 'Deferred')
            ->whereNotNull('mel_expiry_date')
            ->whereDate('mel_expiry_date', '<', $asOf)
            ->count();
        $bad = $open + $overdueDeferred;

        return $bad === 0
            ? ['result' => self::PASS, 'outstanding' => 0]
            : ['result' => self::FAIL, 'outstanding' => $bad, 'reason' => "$open open + $overdueDeferred MEL-expired defect(s)"];
    }

    private function configurationCriterion(?FunctionalLocation $fl): array
    {
        if ($fl === null) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No aircraft record'];
        }
        $cfg = $this->config->statusForLocation($fl);
        if (! $cfg['has_config']) {
            return ['result' => self::NOT_EVALUATED, 'reason' => 'No applicable configuration assigned'];
        }

        return $cfg['mismatch'] === 0
            ? ['result' => self::PASS, 'outstanding' => 0]
            : ['result' => self::FAIL, 'outstanding' => $cfg['mismatch'], 'reason' => "{$cfg['mismatch']} configuration mismatch(es)"];
    }

    private function parseAsOf(?string $asOf): CarbonImmutable
    {
        if ($asOf === null || $asOf === '') {
            return CarbonImmutable::now();
        }
        try {
            return CarbonImmutable::parse($asOf);
        } catch (\Throwable) {
            return CarbonImmutable::now();   // never throw
        }
    }
}
