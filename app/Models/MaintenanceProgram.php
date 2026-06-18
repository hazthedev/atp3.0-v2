<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceProgram extends Model
{
    protected $fillable = ['code', 'title', 'status', 'revision'];

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceProgramItem::class);
    }
}
