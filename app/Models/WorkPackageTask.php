<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPackageTask extends Model
{
    protected $fillable = [
        'work_package_id', 'maintenance_program_item_id', 'counter_ref_id', 'work_order_id',
        'remaining_fh', 'remaining_fc', 'status', 'sort_order', 'lock_version',
    ];

    protected $casts = [
        'remaining_fh' => 'decimal:2',
        'remaining_fc' => 'decimal:2',
        'sort_order' => 'integer',
        'lock_version' => 'integer',
    ];

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }
}
