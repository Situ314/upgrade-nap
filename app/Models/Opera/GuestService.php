<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class GuestService extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'GuestService';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'ReservationID',
        'ProfileUniqueID',
        'checkInDate',
        'checkOutDate',
        'resortId',
        'RateAmount',
    ];

    protected $hidden = [];

    public function Reservation()
    {
        return $this->hasOne('App\Models\Opera\Reservation', 'ReservationID', 'ReservationID');
    }

    public function Profile()
    {
        return $this->hasOne('App\Models\Opera\Profile', 'UniqueID', 'ProfileUniqueID');
    }
}
