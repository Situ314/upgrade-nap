<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class Offmarket extends Model
{
    protected $connection = 'integrationsLogs';

    protected $table = 'Offmarket';

    public $timestamps = false;

    protected $fillable = [
        'HotelId',
        'OffmarketRes',
        'OffmarketKey',
        'BuildingCode',
        'RoomType',
        'RoomTypeCode',
        'StartDate',
        'EndDate',
        'OffmarketFlag',
        'OutOfInventoryFlag',
        'OffmarketText',
        'Created_on_Date',
        'Created_on_Time',
        'xml',
    ];
}
