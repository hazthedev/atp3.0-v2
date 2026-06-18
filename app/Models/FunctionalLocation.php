<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class FunctionalLocation extends Model
{
    protected $fillable = [
        'registration', 'code', 'aircraft_type_id', 'item_id',
        'operator_code', 'owner_code', 'date_of_manufacture', 'entry_into_service',
    ];

    public function aircraftType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(FunctionalLocationCounter::class);
    }

    public function maintenancePrograms(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MaintenanceProgram::class, 'maintenance_program_functional_location')
            ->withPivot(['date_assigned', 'date_unassigned', 'approval_status'])
            ->withTimestamps();
    }

    public function configurationVariants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ConfigurationVariant::class, 'configuration_variant_functional_location')
            ->withTimestamps();
    }

    /** Only top-of-tree equipment links directly to the aircraft FL. */
    public function topEquipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * FL's installed base = top equipment + all descendants by parent_equipment_id (L1>L2>L3).
     * Breadth-first, de-duplicated; the counter cascade walks this without duplicates.
     */
    public function allInstalledEquipment(): Collection
    {
        $all = collect();
        $seen = [];
        $frontier = Equipment::query()
            ->where('functional_location_id', $this->id)
            ->pluck('id')->all();

        while ($frontier !== []) {
            foreach (Equipment::query()->whereIn('id', $frontier)->get() as $equipment) {
                if (! isset($seen[$equipment->id])) {
                    $seen[$equipment->id] = true;
                    $all->push($equipment);
                }
            }
            $frontier = Equipment::query()->whereIn('parent_equipment_id', $frontier)->pluck('id')->all();
        }

        return $all;
    }
}
