<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Defect extends Model
{
    public const ACTIVE_STATUSES = ['Open', 'Deferred'];

    protected $fillable = [
        'code', 'functional_location_id', 'defect_status', 'deferred', 'short_title', 'description',
        'mel_reference_no', 'mel_category', 'mel_expiry_date', 'deferral_category',
        'closed_date', 'closed_time', 'part_on', 'part_off', 'lock_version',
    ];

    protected $casts = [
        'deferred' => 'boolean',
        'mel_expiry_date' => 'date',
        'closed_date' => 'date',
        'part_on' => 'array',
        'part_off' => 'array',
        'lock_version' => 'integer',
    ];
}
