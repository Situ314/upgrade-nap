<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomsOut extends Model
{
    public $timestamps = false;

    protected $table = 'hotel_rooms_out';

    protected $primaryKey = 'room_out_id';

    protected $fillable = [
        'room_id',
        'hotel_id',
        'status',
        'hk_reasons_id',
        'start_date',
        'end_date',
        'comment',
        'is_active',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
    ];

    protected $hidden = [
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
    ];
}
