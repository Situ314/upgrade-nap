<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingEvents extends Model
{
    public $timestamps = false;

    protected $table = 'housekeeping_events';

    protected $primaryKey = 'hk_event_id';

    protected $fillable = [
        'hotel_id',
        'cleaning_id',
        'event_id',
        'is_pickup',
        'is_active',
    ];

    protected $hidden = [
    ];
}
