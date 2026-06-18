<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AircraftType extends Model
{
    protected $fillable = ['code', 'name'];

    public function functionalLocations(): HasMany
    {
        return $this->hasMany(FunctionalLocation::class);
    }
}
