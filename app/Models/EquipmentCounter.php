<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentCounter extends Model
{
    protected $fillable = [
        'equipment_id', 'counter_ref_id', 'value_dec', 'value_hhmm',
        'max_dec', 'remaining', 'residual', 'reading_date', 'reading_hour',
        'info_source', 'is_used', 'lock_version',
    ];

    protected $casts = [
        'value_dec' => 'decimal:4',
        'max_dec' => 'decimal:4',
        'remaining' => 'decimal:4',
        'residual' => 'decimal:4',
        'reading_date' => 'date',
        'is_used' => 'boolean',
        'lock_version' => 'integer',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function counterRef(): BelongsTo
    {
        return $this->belongsTo(CounterRef::class);
    }
}
