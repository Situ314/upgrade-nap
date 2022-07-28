<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class HousekeepingStatus extends Model
{
    protected $connection = 'integrationsLogs';

    protected $table = 'HousekeepingStatus';

    public $timestamps = false;

    protected $fillable = [
        'HotelId',
        'BuildingCode',
        'RoomCode',
        'RoomType',
        'RoomStatus',
        'HousekeepingStatus',
        'HousekeepingStatusDescription',
        'Created_on_Date',
        'Created_on_Time',
        'xml',
    ];
}
