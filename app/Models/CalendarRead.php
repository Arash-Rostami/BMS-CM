<?php

namespace App\Models;

use App\Models\Traits\CalendarRead\HasScopes;
use App\Models\Traits\CalendarRead\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarRead extends Model
{
    use HasFactory,
        HasScopes,
        Relationships;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'calendar_hit_id',
        'event_date',
        'read_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'read_at' => 'datetime',
    ];
}
