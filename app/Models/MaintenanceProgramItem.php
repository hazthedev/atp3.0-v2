<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceProgramItem extends Model
{
    protected $fillable = [
        'maintenance_program_id', 'item_type', 'code', 'label', 'apply_one_time', 'link_to_component',
    ];

    protected $casts = ['apply_one_time' => 'boolean'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(MaintenanceProgram::class, 'maintenance_program_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(MaintenanceProgramItemCounter::class);
    }
}
