<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'Reservation';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'FirsName',
        'LastName',
        'Direction',
        'TransportDate',
        'TransporType',
        'ReservationID',
        'ConfirmationNO',
        'ProfileID',
        'ProfileInfo',
        'ArrivaleDate',
        'DepartureDate',
        'ShortRateCode',
        'ShortRoomType',
        'ResortID',
        'MarketSegmentField',
        'SourceCodeField',
        'NoPostFlag',
        'ReservationStatus',
    ];

    protected $hidden = [];

    public function Profile()
    {
        return $this->hasOne('App\Models\Opera\Profile', 'UniqueID', 'ProfileID');
    }
}
