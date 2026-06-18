<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flight extends Model
{
    protected $fillable = [
        'functional_location_id', 'flight_no', 'scheduled_date', 'status',
        'ac_hours_after_minutes', 'ac_cycle_after',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'ac_hours_after_minutes' => 'decimal:4',
        'ac_cycle_after' => 'integer',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class);
    }
}
