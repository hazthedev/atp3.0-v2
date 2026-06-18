<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyFlightLog extends Model
{
    protected $fillable = [
        'functional_location_id', 'log_date',
        'ac_hours_before_minutes', 'ac_hours_daily_minutes', 'ac_hours_after_minutes',
        'ac_cycle_before', 'ac_cycle_daily', 'ac_cycle_after',
        'tech_log_open', 'tech_log_closed',
    ];

    protected $casts = [
        'log_date' => 'date',
        'ac_hours_before_minutes' => 'decimal:4',
        'ac_hours_daily_minutes' => 'decimal:4',
        'ac_hours_after_minutes' => 'decimal:4',
        'ac_cycle_before' => 'integer',
        'ac_cycle_daily' => 'integer',
        'ac_cycle_after' => 'integer',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class);
    }
}
