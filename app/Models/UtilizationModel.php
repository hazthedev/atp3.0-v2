<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilizationModel extends Model
{
    protected $fillable = ['code', 'functional_location_id', 'name'];

    public function rates(): HasMany
    {
        return $this->hasMany(UtilizationModelRate::class);
    }
}
