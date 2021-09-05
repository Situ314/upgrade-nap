<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class OracleHousekeeping extends Model{
    protected $connection   = 'integrationsLogs';
    protected $table        = 'Oracle_housekeeping';
    public $timestamps      = false;
    protected $fillable     = [
        'resortId',
        'RoomNumber',
        'RoomStatus',    
        'RoomType',
        'created_at',
        'xml',
        'MessageID'
    ];
}