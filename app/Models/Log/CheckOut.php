<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class CheckOut extends Model
{
    protected $connection = 'integrationsLogs';

    protected $table = 'CheckOut';

    public $timestamps = false;

    protected $fillable = [
        'HotelId',
        'GuestName',
        'LastName',
        'FirstName',
        'Salutation',
        'ZipCode',
        'Country',
        'EmailAddress',
        'Cell',
        'GuestCellNumber',
        'Language',
        'Vip',
        'AccountNumber',
        'EmailOptOut',
        'RegularMailOptOut',
        'ReservationNumber',
        'ReservationNumberKey',
        'ArrivalDate',
        'DepartureDate',
        'BookingDate',
        'ReservationLastModifyDate',
        'BuildingCode',
        'RoomCode',
        'RoomTypeCode',
        'RoomTypeDescription',
        'GroupReservation',
        'GroupName',
        'Created_on_Date',
        'Created_on_Time',
        'xml',
    ];
}
