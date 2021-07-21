<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class OracleReservation extends Model{
    protected $connection   = 'integrationsLogs';
    protected $table        = 'Oracle_reservation';
    public $timestamps      = false;
    protected $fillable     = [
        'resortId',
        'ReservationID',
        'reservationStatus',    
        'roomNumber',
        'UniqueID',
        'checkInDate',
        'checkOutDate',
        'ageQualifyingCode',
        'GuestCount',
        'state',
        'created_at'
    ];
}