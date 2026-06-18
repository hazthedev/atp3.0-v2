<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceProgramCompliance extends Model
{
    protected $table = 'maintenance_program_compliance';

    protected $fillable = [
        'functional_location_id', 'maintenance_program_item_id', 'counter_ref_id',
        'reading_at_completion', 'completed_date', 'work_reference',
    ];

    protected $casts = [
        'reading_at_completion' => 'decimal:4',
        'completed_date' => 'date',
    ];
}
