<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationsRoomStayntouch extends Model
{
    public $timestamps = false;

    protected $table = 'integrations_room_stayntouch';

    protected $fillable = [
        'room_id',
        'integration_room_id',
        'hotel_id',
    ];

    protected $hidden = [];
}
