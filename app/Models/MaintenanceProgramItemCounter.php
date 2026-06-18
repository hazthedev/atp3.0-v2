<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceProgramItemCounter extends Model
{
    protected $fillable = [
        'maintenance_program_item_id', 'counter_ref_id', 'threshold', 'interval', 'alarm', 'is_relative',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
        'interval' => 'decimal:2',
        'alarm' => 'decimal:2',
        'is_relative' => 'boolean',
    ];
}
