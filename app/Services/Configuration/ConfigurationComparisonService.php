<?php

namespace App\Services\Configuration;

use App\Models\ApplicableConfigurationItem;
use App\Models\FunctionalLocation;

/**
 * Diffs the aircraft's installed base against its applicable configuration, by part
 * number: installed >= expected => In Sync; installed 0 => Missing; else Missing Qty.
 */
class ConfigurationComparisonService
{
    public function statusForLocation(FunctionalLocation $fl): array
    {
        $variant = $fl->configurationVariants()->with('applicableConfiguration')->first();
        if ($variant === null || $variant->applicableConfiguration === null) {
            return ['has_config' => false, 'in_sync' => 0, 'mismatch' => 0, 'total' => 0];
        }

        // expected quantities grouped by part number
        $expected = [];
        $items = ApplicableConfigurationItem::query()
            ->where('applicable_configuration_id', $variant->applicable_configuration_id)
            ->whereNotNull('allowable_part_number')
            ->get();
        foreach ($items as $item) {
            $pn = $item->allowable_part_number;
            $expected[$pn] = ($expected[$pn] ?? 0) + max(1, (int) $item->expected_quantity);
        }

        // installed quantities grouped by part number (item.code)
        $installed = [];
        foreach ($fl->allInstalledEquipment() as $equipment) {
            $pn = $equipment->item?->code;
            if ($pn !== null) {
                $installed[$pn] = ($installed[$pn] ?? 0) + 1;
            }
        }

        $inSync = $mismatch = 0;
        foreach ($expected as $pn => $expectedQty) {
            $installedQty = $installed[$pn] ?? 0;
            if ($installedQty >= $expectedQty) {
                $inSync++;
            } else {
                $mismatch++;   // Missing (0) or Missing Qty (partial)
            }
        }

        return [
            'has_config' => true,
            'in_sync' => $inSync,
            'mismatch' => $mismatch,
            'total' => count($expected),
        ];
    }
}
