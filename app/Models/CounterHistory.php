<?php

namespace App\Models;

use App\Enums\CounterSourceType;
use App\Enums\CounterSubject;
use Illuminate\Database\Eloquent\Model;

class CounterHistory extends Model
{
    protected $table = 'counter_history';

    protected $fillable = [
        'subject_type', 'subject_id', 'counter_ref_id',
        'prev_value_dec', 'prev_value_hhmm', 'new_value_dec', 'new_value_hhmm', 'delta_dec',
        'reading_date', 'reading_hour', 'info_source', 'source_type', 'source_ref', 'user_id', 'note',
    ];

    protected $casts = [
        'subject_type' => CounterSubject::class,
        'source_type' => CounterSourceType::class,
        'prev_value_dec' => 'decimal:4',
        'new_value_dec' => 'decimal:4',
        'delta_dec' => 'decimal:4',
        'reading_date' => 'date',
    ];
}
