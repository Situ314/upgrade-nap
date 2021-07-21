<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestCheckinDetails extends Model
{
    public $timestamps = false;
    protected $table = 'guest_checkin_details';
    protected $primaryKey = 'sno';

    protected $fillable = [
        'guest_id',
        'hotel_id',
        'room_no',
        'check_in',
        'check_out',
        'comment',
        'status',
        'main_guest', //default 0
        'reservation_status', //default 0
        'reservation_number' //default ''
    ];
    protected $hidden = [
        //'sno',
        //'guest_id', ==> no agregar se utiliza en Event
        //'room_no' ==> no agregar se utiliza en Event
    ];

    public function Room()
    {
        return $this->hasOne('App\Models\HotelRoom','room_id','room_no');
    }

    public function Guest() {
        return $this->hasOne('App\Models\GuestRegistration','guest_id','guest_id');
    }

    public function GuestPms() {
        return $this->hasOne('App\Models\IntegrationsGuestInformation', 'guest_id', 'guest_id');
    }
}
