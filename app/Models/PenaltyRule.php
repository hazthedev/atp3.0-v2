<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltyRule extends Model
{
    protected $fillable = [
        'penalty_id', 'aircraft_type_id', 'aircraft_id',
        'monitoring_counter_ref_id', 'rate_counter_ref_id', 'static_counter_ref_id',
        'rate_value', 'static_value', 'is_relative', 'threshold_value',
        'target_item_id', 'is_active',
    ];

    protected $casts = [
        'rate_value' => 'decimal:4',
        'static_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'is_relative' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function penalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class);
    }

    public function aircraftType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class);
    }
}
