<?php

namespace App\Services\Mro;

use App\Models\FunctionalLocation;
use App\Models\WorkPackage;
use App\Models\WorkPackageTask;
use App\Support\NextCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Builds a Work Package + one task per selected due item. Tasks carry the MRO status
 * vocabulary directly (fork M1) so a Fleet-built WP passes the first MRO save. Code-gen
 * is collision-safe: create in a transaction, retry on the unique('code') violation.
 */
class WorkPackageBuilder
{
    /**
     * @param  array<int,array{item_id:int,counter_ref_id:?int,status?:string,remaining?:float}>  $dueItems
     * @param  array{type?:string,prepared_by?:int}  $header
     */
    public function build(FunctionalLocation $fl, array $dueItems, array $header = []): WorkPackage
    {
        if ($dueItems === []) {
            throw new \InvalidArgumentException('Cannot build a Work Package with no items.');
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($fl, $dueItems, $header) {
                    $wp = WorkPackage::create([
                        'code' => NextCode::sequential(WorkPackage::class, 'WP-', 3),
                        'functional_location_id' => $fl->id,
                        'work_package_type' => $header['type'] ?? 'Base Maintenance',
                        'status' => 'Planned',
                        'prepared_by' => $header['prepared_by'] ?? null,
                    ]);

                    foreach ($dueItems as $i => $item) {
                        WorkPackageTask::create([
                            'work_package_id' => $wp->id,
                            'maintenance_program_item_id' => $item['item_id'],
                            'counter_ref_id' => $item['counter_ref_id'] ?? null,
                            'status' => $item['status'] ?? 'OK',   // OK | Due Soon | Overdue (MRO enum)
                            'sort_order' => $i,
                        ]);
                    }

                    return $wp;
                });
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
                // a concurrent build took our code — recompute and retry
            }
        }

        throw new \RuntimeException('Could not allocate a unique Work Package code after retries.');
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // precise: only the code/message of a UNIQUE violation — NOT a generic 23000
        // (which also covers FK violations we must not retry).
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'unique') || str_contains($msg, 'duplicate')
            || (($e->errorInfo[1] ?? null) === 1062);   // MySQL duplicate-entry
    }
}
