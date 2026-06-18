<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'functional_location_id', 'parent_equipment_id', 'item_id',
        'hierarchy_level', 'serial_no',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_equipment_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_equipment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(EquipmentCounter::class);
    }
}
