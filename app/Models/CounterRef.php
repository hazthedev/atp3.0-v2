<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterRef extends Model
{
    protected $fillable = [
        'code', 'counter_code', 'description', 'measure_unit_id',
        'incr_decr', 'allow_incr_decr', 'min_value', 'max_value', 'initial_value',
        'parent_counter_id', 'propagation_from_parent', 'propagation_flag',
        'used_for_residual_calc', 'orange_light_limit',
    ];

    protected $casts = [
        'incr_decr' => 'integer',
        'allow_incr_decr' => 'boolean',
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'initial_value' => 'decimal:4',
        'propagation_from_parent' => 'boolean',
        'propagation_flag' => 'boolean',
        'used_for_residual_calc' => 'boolean',
        'orange_light_limit' => 'integer',
    ];

    public function parentCounter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_counter_id');
    }

    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }
}
