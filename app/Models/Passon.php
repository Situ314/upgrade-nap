<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passon extends Model
{
    public $timestamps = false;

    protected $table = 'passon';

    protected $primaryKey = 'pid';

    protected $fillable = [
        'departures',
        'arrivals',
        'occupied_rooms',
        'occupancy',
        'general_info',
        'special_arrival',
        'hotel_id',
        'field1',       // null
        'field2',       // null
        'field3',       // null
        'field4',       // null
        'updated_by',   // null
        'updated_on',   // null
        'created_by',
        'created_on',
        'history',      // 0
        'is_cleane',     // 0
    ];

    protected $hidden = [
    ];
}
