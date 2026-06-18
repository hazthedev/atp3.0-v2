<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicableConfigurationItem extends Model
{
    protected $fillable = [
        'applicable_configuration_id', 'parent_id', 'ata_code', 'item_name',
        'allowable_part_number', 'expected_quantity', 'requirement_type', 'sort_order',
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'sort_order' => 'integer',
    ];
}
