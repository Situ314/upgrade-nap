<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlexaDevice extends Model
{
    public $timestamps = false;

    protected $table = 'alexa_device';

    protected $primaryKey = 'id_alexa';

    protected $fillable = [
        'device_alexa_id',
        'room_id',
        'hotel_id',
    ];

    protected $hidden = [
        'hotel_id',
    ];

    public function Room()
    {
        return $this->hasOne('App\Models\HotelRoom', 'room_id', 'room_id');
    }
}
