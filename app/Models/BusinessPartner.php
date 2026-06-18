<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPartner extends Model
{
    protected $fillable = ['code', 'name', 'partner_type', 'contact_name', 'email', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
