<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class QueueRoom extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'QueueRoom';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'ResortID',
        'Action',
        'ArrivalDate',
        'GuestNameID',
        'Name',
        'QueueTime',
        'ReservationStatus',
        'RoomType',
        'RoomNumber',
    ];

    protected $hidden = [];
}
