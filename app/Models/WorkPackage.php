<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkPackage extends Model
{
    public const CLOSED_STATUSES = ['Completed', 'Cancelled'];

    protected $fillable = [
        'code', 'functional_location_id', 'work_package_type', 'status',
        'progress_percent', 'prepared_by', 'prepared_date', 'lock_version',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'prepared_date' => 'date',
        'lock_version' => 'integer',
    ];

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkPackageTask::class);
    }
}
