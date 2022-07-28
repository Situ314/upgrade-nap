<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoom extends Model
{
    public $timestamps = false;

    protected $table = 'hotel_rooms';

    protected $primaryKey = 'room_id';

    protected $fillable = [
        'hotel_id',
        'location',
        'created_by',
        'updated_by',
        'created_on',
        'updated_on',
        'active',
        'angel_view',
        'device_token',
        'room_type_id',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_on',
        'updated_on',
        'active',
        'angel_view',
        'device_token',
    ];
}
