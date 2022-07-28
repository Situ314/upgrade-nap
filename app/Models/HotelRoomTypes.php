<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomTypes extends Model
{
    public $timestamps = false;

    protected $table = 'hotel_room_types';

    protected $primaryKey = 'room_type_id';

    protected $fillable = [
        'hotel_id',
        'name_type',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
        'is_active',
    ];

    protected $hidden = [
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
    ];
}
