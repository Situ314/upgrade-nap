<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingCleanings extends Model
{
    public $timestamps = false;

    protected $table = 'housekeeping_cleanings';

    protected $primaryKey = 'cleaning_id';

    protected $fillable = [
        'hotel_id',
        'room_id',
        'count_by_hotel_id',
        'housekeeper_id',
        'supervisor_id',
        'cleaning_order',
        'guest_id',
        'assigned_date',
        'started_on',
        'ended_on',
        'hk_status',
        'front_desk_status',
        'shift',
        'is_active',
        'room_status',
        'room_status_on',
        'in_queue',
        'comeback_time',
        'num_people',
        'comments',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
    ];

    protected $hidden = [
        'room_id',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
    ];

    public function Room()
    {
        return $this->hasOne('App\Models\HotelRoom', 'room_id', 'room_id');
    }
}
