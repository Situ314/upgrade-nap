<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class SMSReservation extends Model{
    protected $connection   = 'integrationsLogs';
    protected $table        = 'SMS_Reservation';
    public $timestamps      = false;
    protected $fillable     = [
        "guestnum",
        "phone",
        "phone2",
        "fax",
        "email",
        "city",
        "state",
        "zip",
        "last",
        "first",
        "unum",
        "resno",
        "arrival",
        "depart",
        "level",
        "primaryshare",
        "group",
        "utyp",
        "hotel_id",
        "type",
        "created_at",
    ];
}