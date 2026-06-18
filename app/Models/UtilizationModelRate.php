<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilizationModelRate extends Model
{
    protected $fillable = [
        'utilization_model_id', 'measure_unit_id',
        'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
    ];

    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }

    /** Average of the 12 monthly accrual figures. */
    public function monthlyAverage(): float
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $sum = 0.0;
        foreach ($months as $m) {
            $sum += (float) $this->{$m};
        }

        return $sum / 12;
    }
}
