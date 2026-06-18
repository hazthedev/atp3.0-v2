<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigurationVariant extends Model
{
    protected $fillable = ['applicable_configuration_id', 'code', 'name'];

    public function applicableConfiguration(): BelongsTo
    {
        return $this->belongsTo(ApplicableConfiguration::class);
    }
}
