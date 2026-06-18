<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationCompliance extends Model
{
    public const SATISFIED_STATUSES = ['Embodied', 'Waived'];
    public const OUTSTANDING_STATUSES = ['Open', 'Pending'];

    protected $fillable = [
        'functional_location_id', 'technical_publication_id', 'compliance_status',
        'action_date', 'removal_date', 'utilization_snapshot', 'remarks',
    ];

    protected $casts = [
        'action_date' => 'date',
        'removal_date' => 'date',
        'utilization_snapshot' => 'array',
    ];
}
