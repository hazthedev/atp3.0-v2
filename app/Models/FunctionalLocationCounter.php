<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionalLocationCounter extends Model
{
    protected $fillable = [
        'functional_location_id', 'counter_ref_id', 'value_dec', 'value_hhmm',
        'max_dec', 'max_hhmm', 'remaining', 'residual', 'reading_date', 'reading_hour',
        'info_source', 'propagate', 'is_used', 'lock_version',
    ];

    protected $casts = [
        'value_dec' => 'decimal:4',
        'max_dec' => 'decimal:4',
        'remaining' => 'decimal:4',
        'residual' => 'decimal:4',
        'reading_date' => 'date',
        'propagate' => 'boolean',
        'is_used' => 'boolean',
        'lock_version' => 'integer',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class);
    }

    public function counterRef(): BelongsTo
    {
        return $this->belongsTo(CounterRef::class);
    }
}
