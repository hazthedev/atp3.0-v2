<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalPublication extends Model
{
    protected $fillable = [
        'reference', 'publication_type_id', 'status', 'applicable_aircraft_type_id', 'title',
    ];

    public function publicationType(): BelongsTo
    {
        return $this->belongsTo(PublicationType::class);
    }
}
