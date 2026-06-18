<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationType extends Model
{
    protected $fillable = ['code', 'label', 'active'];

    protected $casts = ['active' => 'boolean'];
}
